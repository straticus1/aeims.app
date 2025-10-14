<?php
/**
 * Customer Chat Interface
 * Simple file-based chat system
 */

// Check if session already started (from SSO middleware)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check customer authentication
if (!isset($_SESSION['customer_id'])) {
    header('Location: /login.php');
    exit;
}

// Load site configuration
require_once __DIR__ . '/../../services/SiteManager.php';
require_once __DIR__ . '/../../data/data_helper.php';

try {
    $siteManager = new \AEIMS\Services\SiteManager();

    // Get current site
    $hostname = $_SERVER['HTTP_HOST'] ?? 'flirts.nyc';
    $hostname = preg_replace('/^www\./', '', $hostname);
    $hostname = preg_replace('/:\d+$/', '', $hostname);

    $site = $siteManager->getSite($hostname);
    if (!$site || !$site['active']) {
        http_response_code(503);
        die('Site temporarily unavailable');
    }
} catch (Exception $e) {
    error_log("Chat error: " . $e->getMessage());
    http_response_code(500);
    die('Chat unavailable');
}

$customerId = $_SESSION['customer_id'];
$customerUsername = $_SESSION['customer_data']['username'] ?? 'Customer';

// Get operator from URL
$operatorId = $_GET['operator_id'] ?? '';

if (empty($operatorId)) {
    header('Location: /search-operators.php');
    exit;
}

// Load operator data
$operatorsFile = __DIR__ . '/../../agents/data/operators.json';
$operatorsData = json_decode(file_get_contents($operatorsFile), true);
$operator = $operatorsData[$operatorId] ?? null;

if (!$operator) {
    header('Location: /search-operators.php');
    exit;
}

// Get operator display name for current site
$operatorName = $operator['profile']['display_names'][$hostname] ??
                $operator['profile']['display_names']['default'] ??
                $operator['username'] ??
                'Operator';

// Load or create conversation
$conversationsFile = __DIR__ . '/../../data/conversations.json';
if (!file_exists($conversationsFile)) {
    file_put_contents($conversationsFile, json_encode([]));
}

$conversations = json_decode(file_get_contents($conversationsFile), true);
if (!$conversations) {
    $conversations = [];
}

// Find or create conversation
$conversationKey = "{$hostname}_{$customerId}_{$operatorId}";
if (!isset($conversations[$conversationKey])) {
    $conversations[$conversationKey] = [
        'conversation_id' => $conversationKey,
        'site' => $hostname,
        'customer_id' => $customerId,
        'operator_id' => $operatorId,
        'started_at' => date('Y-m-d H:i:s'),
        'last_message_at' => date('Y-m-d H:i:s'),
    ];
    file_put_contents($conversationsFile, json_encode($conversations, JSON_PRETTY_PRINT));
}

// Load messages
$messagesFile = __DIR__ . '/../../data/messages.json';
if (!file_exists($messagesFile)) {
    file_put_contents($messagesFile, json_encode([]));
}

$allMessages = json_decode(file_get_contents($messagesFile), true);
if (!$allMessages) {
    $allMessages = [];
}

// Get messages for this conversation
$conversationMessages = array_filter($allMessages, function($msg) use ($conversationKey) {
    return $msg['conversation_id'] === $conversationKey;
});

// Sort by timestamp
usort($conversationMessages, function($a, $b) {
    return strtotime($a['timestamp']) - strtotime($b['timestamp']);
});

// Handle message sending
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $messageContent = trim($_POST['message']);

    if (!empty($messageContent)) {
        $newMessage = [
            'message_id' => uniqid('msg_', true),
            'conversation_id' => $conversationKey,
            'sender_id' => $customerId,
            'sender_type' => 'customer',
            'sender_name' => $customerUsername,
            'content' => $messageContent,
            'timestamp' => date('Y-m-d H:i:s'),
            'read' => false,
        ];

        $allMessages[] = $newMessage;
        file_put_contents($messagesFile, json_encode($allMessages, JSON_PRETTY_PRINT));

        // Update conversation last message time
        $conversations[$conversationKey]['last_message_at'] = date('Y-m-d H:i:s');
        file_put_contents($conversationsFile, json_encode($conversations, JSON_PRETTY_PRINT));

        // For AJAX requests
        if (isset($_POST['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => $newMessage]);
            exit;
        }

        // Redirect to prevent form resubmission
        header("Location: /chat.php?operator_id={$operatorId}");
        exit;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat with <?= htmlspecialchars($operatorName) ?> - <?= htmlspecialchars($site['name']) ?></title>
    <link rel="icon" href="<?= htmlspecialchars($site['theme']['favicon_url']) ?>">
    <script src="/assets/js/emoji-picker.js"></script>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: <?= $site['theme']['font_family'] ?>;
            background: linear-gradient(135deg, #1a1a1a 0%, #2d1b3d 100%);
            color: #ffffff;
            height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .chat-header {
            background: rgba(0, 0, 0, 0.9);
            padding: 1rem 2rem;
            border-bottom: 1px solid rgba(239, 68, 68, 0.3);
            display: flex;
            justify-content: space-between;
            align-items: center;
            backdrop-filter: blur(10px);
        }

        .operator-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .operator-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(45deg, <?= $site['theme']['primary_color'] ?>, <?= $site['theme']['accent_color'] ?>);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            font-weight: bold;
            color: white;
        }

        .operator-details h2 {
            color: <?= $site['theme']['primary_color'] ?>;
            font-size: 1.3rem;
        }

        .online-status {
            color: #22c55e;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            background: #22c55e;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        .back-button {
            background: transparent;
            color: <?= $site['theme']['primary_color'] ?>;
            border: 1px solid <?= $site['theme']['primary_color'] ?>;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .back-button:hover {
            background: <?= $site['theme']['primary_color'] ?>;
            color: white;
        }

        .chat-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            max-height: calc(100vh - 80px);
        }

        .messages-area {
            flex: 1;
            padding: 1rem;
            overflow-y: auto;
            background: rgba(0, 0, 0, 0.1);
        }

        .message {
            margin-bottom: 1rem;
            display: flex;
            animation: fadeIn 0.3s ease;
        }

        .message.customer {
            justify-content: flex-end;
        }

        .message.operator {
            justify-content: flex-start;
        }

        .message-bubble {
            max-width: 70%;
            padding: 1rem 1.5rem;
            border-radius: 20px;
            position: relative;
        }

        .message.customer .message-bubble {
            background: linear-gradient(45deg, <?= $site['theme']['primary_color'] ?>, <?= $site['theme']['secondary_color'] ?>);
            color: white;
            border-bottom-right-radius: 5px;
        }

        .message.operator .message-bubble {
            background: rgba(255, 255, 255, 0.1);
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-bottom-left-radius: 5px;
        }

        .message-content {
            margin-bottom: 0.5rem;
            line-height: 1.4;
        }

        .message-meta {
            font-size: 0.8rem;
            opacity: 0.7;
        }

        .message-input-area {
            padding: 1rem 2rem;
            background: rgba(0, 0, 0, 0.3);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .input-container {
            display: flex;
            gap: 0.5rem;
            align-items: flex-end;
        }

        .emoji-button {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            width: 50px;
            height: 50px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .emoji-button:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: scale(1.1);
        }

        .message-input {
            flex: 1;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 25px;
            padding: 1rem 1.5rem;
            color: #ffffff;
            resize: none;
            min-height: 50px;
            max-height: 150px;
            overflow-y: auto;
        }

        .message-input:focus {
            outline: none;
            border-color: <?= $site['theme']['primary_color'] ?>;
            box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.2);
        }

        .send-button {
            background: linear-gradient(45deg, <?= $site['theme']['primary_color'] ?>, <?= $site['theme']['secondary_color'] ?>);
            color: white;
            border: none;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

        .send-button:hover {
            transform: scale(1.1);
            box-shadow: 0 5px 20px rgba(239, 68, 68, 0.4);
        }

        .send-button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        .empty-state {
            text-align: center;
            padding: 2rem;
            color: #9ca3af;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }

        @media (max-width: 768px) {
            .chat-header {
                padding: 1rem;
            }

            .operator-info {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }

            .message-bubble {
                max-width: 90%;
            }

            .message-input-area {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <header class="chat-header">
        <div class="operator-info">
            <div class="operator-avatar">
                <?= strtoupper(substr($operatorName, 0, 2)) ?>
            </div>
            <div class="operator-details">
                <h2><?= htmlspecialchars($operatorName) ?></h2>
                <div class="online-status">
                    <div class="status-dot"></div>
                    Available
                </div>
            </div>
        </div>

        <div>
            <a href="/search-operators.php" class="back-button">← Back to Search</a>
        </div>
    </header>

    <div class="chat-container">
        <div class="messages-area" id="messages-area">
            <?php if (empty($conversationMessages)): ?>
                <div class="empty-state">
                    <h3>Start your conversation with <?= htmlspecialchars($operatorName) ?></h3>
                    <p>Send your first message to begin chatting!</p>
                </div>
            <?php else: ?>
                <?php foreach ($conversationMessages as $message): ?>
                    <div class="message <?= $message['sender_type'] ?>">
                        <div class="message-bubble">
                            <div class="message-content">
                                <?= nl2br(htmlspecialchars($message['content'])) ?>
                            </div>
                            <div class="message-meta">
                                <?= date('g:i A', strtotime($message['timestamp'])) ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="message-input-area">
            <form id="message-form" method="POST">
                <div class="input-container">
                    <button type="button" class="emoji-button" id="emoji-trigger">
                        😀
                    </button>
                    <textarea
                        id="message-input"
                        name="message"
                        class="message-input"
                        placeholder="Type your message..."
                        required
                    ></textarea>
                    <button type="submit" class="send-button" id="send-button">
                        📤
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Auto-scroll to bottom
        function scrollToBottom() {
            const messagesArea = document.getElementById('messages-area');
            messagesArea.scrollTop = messagesArea.scrollHeight;
        }

        // Handle enter key
        document.getElementById('message-input').addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                document.getElementById('message-form').submit();
            }
        });

        // Initial setup
        scrollToBottom();

        // Auto-scroll on window resize
        window.addEventListener('resize', scrollToBottom);

        // Initialize emoji picker
        const emojiPicker = new EmojiPicker();
        emojiPicker.init('#emoji-trigger', '#message-input');
    </script>
</body>
</html>
