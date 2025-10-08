<?php
/**
 * Customer Chat Interface - New Microservice Architecture
 * Integrates with admin-service per-site chat and billing
 */

session_start();

// Check customer authentication
if (!isset($_SESSION['customer_id'])) {
    header('Location: /');
    exit;
}

// Current site domain - automatically detected
$currentDomain = $_SERVER['HTTP_HOST'] ?? 'nycflirts.com';

// Load the microservice client for chat operations
require_once '../../lib/MicroserviceClient.php';

$operatorId = $_GET['operator_id'] ?? '';
$conversationId = $_GET['conversation_id'] ?? '';

if (!$operatorId && !$conversationId) {
    header('Location: /dashboard.php');
    exit;
}

try {
    $chatClient = new \AEIMS\Lib\MicroserviceClient('admin-service', 8000);

    // Get site details and configuration
    $site = $chatClient->request('GET', "/api/admin/sites/{$currentDomain}");
    $customer = $chatClient->request('GET', "/api/admin/sites/{$currentDomain}/customers/{$_SESSION['customer_id']}");

    if (!$site || !$site['active'] || !$customer) {
        header('Location: /dashboard.php');
        exit;
    }

    // Get or create conversation
    if ($conversationId) {
        $conversation = $chatClient->request('GET', "/api/admin/sites/{$currentDomain}/chats/{$conversationId}");
        if (!$conversation || $conversation['user_id'] !== $_SESSION['customer_id']) {
            header('Location: /dashboard.php');
            exit;
        }
        $operator = $chatClient->request('GET', "/api/admin/sites/{$currentDomain}/operators/{$conversation['operator_id']}");
    } else {
        $operator = $chatClient->request('GET', "/api/admin/sites/{$currentDomain}/operators/{$operatorId}");
        if (!$operator) {
            header('Location: /dashboard.php');
            exit;
        }

        // Start new conversation via the chat service
        $newChat = $chatClient->request('POST', "/api/admin/sites/{$currentDomain}/chats", [
            'user_id' => $_SESSION['customer_id'],
            'operator_id' => $operatorId,
            'chat_type' => 'customer_operator'
        ]);

        $conversationId = $newChat['chat_id'];
        $conversation = $newChat;
    }

    // Get messages and billing info
    $messages = $chatClient->request('GET', "/api/admin/sites/{$currentDomain}/chats/{$conversationId}/messages", [
        'limit' => 50,
        'order' => 'asc'
    ]);

    $billingInfo = $chatClient->request('GET', "/api/admin/sites/{$currentDomain}/billing/user/{$_SESSION['customer_id']}");

} catch (Exception $e) {
    error_log("Chat microservice error: " . $e->getMessage());
    header('Location: /dashboard.php');
    exit;
}

// Handle message sending
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_message') {
    try {
        $content = trim($_POST['message_content']);

        if (!empty($content)) {
            $messageResult = $chatClient->request('POST', "/api/admin/sites/{$currentDomain}/chats/{$conversationId}/messages", [
                'sender_id' => $_SESSION['customer_id'],
                'content' => $content,
                'sender_type' => 'user'
            ]);

            // Refresh customer billing data
            $billingInfo = $chatClient->request('GET', "/api/admin/sites/{$currentDomain}/billing/user/{$_SESSION['customer_id']}");

            // Return JSON for AJAX
            if (isset($_POST['ajax'])) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => $messageResult,
                    'new_credits' => $billingInfo['credits'],
                    'free_messages' => $billingInfo['free_chat_messages']
                ]);
                exit;
            }

            // Redirect to prevent form resubmission
            header('Location: /chat.php?conversation_id=' . $conversationId);
            exit;
        }
    } catch (Exception $e) {
        if (isset($_POST['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
            exit;
        }
        $error = $e->getMessage();
    }
}

// Set default billing rates for JavaScript
$defaultBillingRates = [
    'message_rate' => 0.75
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat with <?= htmlspecialchars($operator['name'] ?? $operator['username'] ?? 'Operator') ?> - <?= htmlspecialchars($site['name'] ?? $currentDomain) ?></title>
    <link rel="icon" href="<?= htmlspecialchars($site['theme']['favicon_url'] ?? '/favicon.ico') ?>">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: <?= $site['theme']['font_family'] ?? "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif" ?>;
            background: linear-gradient(135deg, #1a1a1a 0%, #2d1b3d 100%);
            color: <?= $site['theme']['text_color'] ?? '#ffffff' ?>;
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
            background: linear-gradient(45deg, <?= $site['theme']['primary_color'] ?? '#ef4444' ?>, <?= $site['theme']['accent_color'] ?? '#f59e0b' ?>);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            font-weight: bold;
            color: white;
        }

        .operator-details h2 {
            color: <?= $site['theme']['primary_color'] ?? '#ef4444' ?>;
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

        .billing-info {
            text-align: right;
            font-size: 0.9rem;
        }

        .credits-display {
            background: linear-gradient(45deg, <?= $site['theme']['primary_color'] ?? '#ef4444' ?>, <?= $site['theme']['secondary_color'] ?? '#f59e0b' ?>);
            padding: 0.5rem 1rem;
            border-radius: 15px;
            margin-bottom: 0.5rem;
        }

        .free-messages {
            color: #22c55e;
            font-weight: 500;
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

        .message.user {
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

        .message.user .message-bubble {
            background: linear-gradient(45deg, <?= $site['theme']['primary_color'] ?? '#ef4444' ?>, <?= $site['theme']['secondary_color'] ?? '#f59e0b' ?>);
            color: white;
            border-bottom-right-radius: 5px;
        }

        .message.operator .message-bubble {
            background: rgba(255, 255, 255, 0.1);
            color: <?= $site['theme']['text_color'] ?? '#ffffff' ?>;
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
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .billing-indicator {
            background: rgba(0, 0, 0, 0.3);
            padding: 0.2rem 0.5rem;
            border-radius: 10px;
            font-size: 0.7rem;
        }

        .billing-indicator.paid {
            color: #fbbf24;
        }

        .billing-indicator.free {
            color: #22c55e;
        }

        .message-input-area {
            padding: 1rem 2rem;
            background: rgba(0, 0, 0, 0.3);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .input-container {
            display: flex;
            gap: 1rem;
            align-items: flex-end;
        }

        .message-input {
            flex: 1;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 25px;
            padding: 1rem 1.5rem;
            color: <?= $site['theme']['text_color'] ?? '#ffffff' ?>;
            resize: none;
            min-height: 50px;
            max-height: 150px;
            overflow-y: auto;
        }

        .message-input:focus {
            outline: none;
            border-color: <?= $site['theme']['primary_color'] ?? '#ef4444' ?>;
            box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.2);
        }

        .send-button {
            background: linear-gradient(45deg, <?= $site['theme']['primary_color'] ?? '#ef4444' ?>, <?= $site['theme']['secondary_color'] ?? '#f59e0b' ?>);
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

        .cost-preview {
            font-size: 0.8rem;
            color: #fbbf24;
            margin-top: 0.5rem;
            text-align: center;
        }

        .back-button {
            background: transparent;
            color: <?= $site['theme']['primary_color'] ?? '#ef4444' ?>;
            border: 1px solid <?= $site['theme']['primary_color'] ?? '#ef4444' ?>;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .back-button:hover {
            background: <?= $site['theme']['primary_color'] ?? '#ef4444' ?>;
            color: white;
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

            .billing-info {
                text-align: left;
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
                <?= strtoupper(substr($operator['name'] ?? $operator['username'] ?? 'OP', 0, 2)) ?>
            </div>
            <div class="operator-details">
                <h2><?= htmlspecialchars($operator['name'] ?? $operator['username'] ?? 'Operator') ?></h2>
                <div class="online-status">
                    <div class="status-dot"></div>
                    Online Now
                </div>
            </div>
        </div>

        <div class="billing-info">
            <div class="credits-display">
                Credits: $<span id="current-credits"><?= number_format($billingInfo['credits'] ?? 0, 2) ?></span>
            </div>
            <div class="free-messages">
                Free messages: <span id="free-messages"><?= $billingInfo['free_chat_messages'] ?? 0 ?></span>
            </div>
            <a href="/dashboard.php" class="back-button">← Back</a>
        </div>
    </header>

    <div class="chat-container">
        <div class="messages-area" id="messages-area">
            <?php if (empty($messages)): ?>
                <div style="text-align: center; padding: 2rem; color: #9ca3af;">
                    <h3>Start your conversation with <?= htmlspecialchars($operator['name'] ?? $operator['username'] ?? 'this operator') ?></h3>
                    <p>Send your first message to begin chatting!</p>
                </div>
            <?php else: ?>
                <?php foreach ($messages as $message): ?>
                    <div class="message <?= $message['sender_type'] ?>">
                        <div class="message-bubble">
                            <div class="message-content">
                                <?= nl2br(htmlspecialchars($message['content'])) ?>
                            </div>
                            <div class="message-meta">
                                <span><?= date('g:i A', strtotime($message['created_at'])) ?></span>
                                <?php if ($message['sender_type'] === 'user'): ?>
                                    <span class="billing-indicator <?= ($message['billing_amount'] ?? 0) > 0 ? 'paid' : 'free' ?>">
                                        <?php if (($message['billing_amount'] ?? 0) > 0): ?>
                                            -$<?= number_format($message['billing_amount'], 2) ?>
                                        <?php else: ?>
                                            Free
                                        <?php endif; ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="message-input-area">
            <form id="message-form" method="POST">
                <input type="hidden" name="action" value="send_message">
                <input type="hidden" name="ajax" value="1">

                <div class="input-container">
                    <textarea
                        id="message-input"
                        name="message_content"
                        class="message-input"
                        placeholder="Type your message..."
                        required
                    ></textarea>
                    <button type="submit" class="send-button" id="send-button">
                        📤
                    </button>
                </div>

                <div class="cost-preview" id="cost-preview"></div>
            </form>
        </div>
    </div>

    <script>
        const conversationId = '<?= $conversationId ?>';
        const billingRates = <?= json_encode($defaultBillingRates) ?>;
        const currentDomain = '<?= $currentDomain ?>';
        let currentCredits = <?= $billingInfo['credits'] ?? 0 ?>;
        let freeMessages = <?= $billingInfo['free_chat_messages'] ?? 0 ?>;

        // Auto-scroll to bottom
        function scrollToBottom() {
            const messagesArea = document.getElementById('messages-area');
            messagesArea.scrollTop = messagesArea.scrollHeight;
        }

        // Update cost preview
        function updateCostPreview() {
            const messageInput = document.getElementById('message-input');
            const costPreview = document.getElementById('cost-preview');
            const sendButton = document.getElementById('send-button');

            const hasContent = messageInput.value.trim().length > 0;

            if (!hasContent) {
                costPreview.textContent = '';
                return;
            }

            if (freeMessages > 0) {
                costPreview.textContent = 'This message will use 1 free message';
                costPreview.style.color = '#22c55e';
            } else {
                const cost = billingRates.message_rate;
                costPreview.textContent = `This message will cost $${cost.toFixed(2)}`;
                costPreview.style.color = '#fbbf24';

                if (currentCredits < cost) {
                    costPreview.textContent = 'Insufficient credits! Add more to continue chatting.';
                    costPreview.style.color = '#ef4444';
                    sendButton.disabled = true;
                    return;
                }
            }

            sendButton.disabled = false;
        }

        // Handle form submission
        document.getElementById('message-form').addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const messageInput = document.getElementById('message-input');
            const sendButton = document.getElementById('send-button');

            if (!messageInput.value.trim()) {
                return;
            }

            sendButton.disabled = true;
            sendButton.textContent = '⏳';

            fetch('/chat.php?conversation_id=' + conversationId, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Add message to chat
                    addMessageToChat(data.message);

                    // Update credits and free messages
                    currentCredits = data.new_credits;
                    document.getElementById('current-credits').textContent = currentCredits.toFixed(2);

                    freeMessages = data.free_messages;
                    document.getElementById('free-messages').textContent = freeMessages;

                    // Clear input
                    messageInput.value = '';
                    updateCostPreview();

                    // Scroll to bottom
                    scrollToBottom();
                } else {
                    alert('Failed to send message: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to send message. Please try again.');
            })
            .finally(() => {
                sendButton.disabled = false;
                sendButton.textContent = '📤';
            });
        });

        // Add message to chat display
        function addMessageToChat(message) {
            const messagesArea = document.getElementById('messages-area');
            const messageDiv = document.createElement('div');
            messageDiv.className = 'message ' + message.sender_type;

            const billingText = (message.billing_amount && message.billing_amount > 0) ?
                `-$${message.billing_amount.toFixed(2)}` : 'Free';

            const billingClass = (message.billing_amount && message.billing_amount > 0) ? 'paid' : 'free';

            messageDiv.innerHTML = `
                <div class="message-bubble">
                    <div class="message-content">${message.content.replace(/\n/g, '<br>')}</div>
                    <div class="message-meta">
                        <span>Just now</span>
                        ${message.sender_type === 'user' ?
                            `<span class="billing-indicator ${billingClass}">${billingText}</span>` : ''}
                    </div>
                </div>
            `;

            messagesArea.appendChild(messageDiv);
        }

        // Event listeners
        document.getElementById('message-input').addEventListener('input', updateCostPreview);
        document.getElementById('message-input').addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                document.getElementById('message-form').dispatchEvent(new Event('submit'));
            }
        });

        // Initial setup
        updateCostPreview();
        scrollToBottom();

        // Auto-scroll on window resize
        window.addEventListener('resize', scrollToBottom);
    </script>
</body>
</html>