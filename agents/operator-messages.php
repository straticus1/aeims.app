<?php
/**
 * Operator Messaging Interface
 * Allows operators to send free, paid, and marketing messages
 */

session_start();

if (!isset($_SESSION['operator_id'])) {
    header('Location: /agents/login.php');
    exit;
}

require_once '../services/OperatorManager.php';
require_once '../services/CustomerManager.php';
require_once '../services/MessagingManager.php';
require_once '../services/ActivityLogger.php';

try {
    $operatorManager = new \AEIMS\Services\OperatorManager();
    $customerManager = new \AEIMS\Services\CustomerManager();
    $messagingManager = new \AEIMS\Services\MessagingManager();
    $activityLogger = new \AEIMS\Services\ActivityLogger();

    $operator = $operatorManager->getOperator($_SESSION['operator_id']);

    if (!$operator) {
        session_destroy();
        header('Location: /agents/login.php');
        exit;
    }

    // Handle message sending
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
        $conversationId = $_POST['conversation_id'];
        $content = trim($_POST['content']);
        $messageType = $_POST['message_type'] ?? 'free'; // free, paid, marketing

        if (!empty($content)) {
            try {
                $conversation = $messagingManager->getConversation($conversationId);

                if ($messageType === 'free') {
                    // Standard free operator reply
                    $message = $messagingManager->sendMessage(
                        $conversationId,
                        $_SESSION['operator_id'],
                        $content,
                        'operator'
                    );
                    $_SESSION['message_success'] = 'Free message sent! Customer earned 1 free reply.';
                } elseif ($messageType === 'paid') {
                    // Paid message - charges customer immediately
                    $message = $messagingManager->sendPaidOperatorMessage(
                        $conversationId,
                        $_SESSION['operator_id'],
                        $content,
                        1.99 // Paid message rate
                    );
                    $_SESSION['message_success'] = 'Paid message sent! Customer was charged $1.99.';
                } elseif ($messageType === 'marketing') {
                    // Marketing blast message
                    $message = $messagingManager->sendMarketingMessage(
                        $conversationId,
                        $_SESSION['operator_id'],
                        $content
                    );
                    $_SESSION['message_success'] = 'Marketing message sent!';
                }
            } catch (Exception $e) {
                $_SESSION['message_error'] = $e->getMessage();
            }
        }

        header('Location: /agents/operator-messages.php?conversation_id=' . urlencode($conversationId));
        exit;
    }

    // Get conversations for operator
    $conversations = $messagingManager->getOperatorConversations($_SESSION['operator_id']);

    // Get current conversation
    $currentConversation = null;
    $messages = [];
    $customer = null;

    if (isset($_GET['conversation_id'])) {
        $currentConversation = $messagingManager->getConversation($_GET['conversation_id']);

        if ($currentConversation && $currentConversation['operator_id'] === $_SESSION['operator_id']) {
            $messages = $messagingManager->getConversationMessages($_GET['conversation_id']);
            $customer = $customerManager->getCustomer($currentConversation['customer_id']);
        }
    } elseif (!empty($conversations)) {
        $currentConversation = $conversations[0];
        $messages = $messagingManager->getConversationMessages($currentConversation['conversation_id']);
        $customer = $customerManager->getCustomer($currentConversation['customer_id']);
    }

} catch (Exception $e) {
    error_log("Operator messages error: " . $e->getMessage());
    $_SESSION['message_error'] = "An error occurred. Please try again.";
}

$messageSuccess = $_SESSION['message_success'] ?? null;
$messageError = $_SESSION['message_error'] ?? null;
unset($_SESSION['message_success'], $_SESSION['message_error']);

// Get operator domain-specific name
$hostname = $_SERVER['HTTP_HOST'] ?? 'localhost';
$hostname = preg_replace('/^www\./', '', $hostname);
$hostname = preg_replace('/:\d+$/', '', $hostname);
$operatorDisplayName = $operator['profile']['display_names'][$hostname] ?? $operator['name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - Operator Dashboard</title>
    <script src="/assets/js/emoji-picker.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            height: 100vh;
            overflow: hidden;
        }

        .header {
            background: rgba(0, 0, 0, 0.9);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .logo {
            font-size: 1.5rem;
            font-weight: bold;
            color: #667eea;
        }

        .header-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .operator-badge {
            background: linear-gradient(45deg, #667eea, #764ba2);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 500;
        }

        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 20px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .btn-secondary {
            background: transparent;
            color: #667eea;
            border: 1px solid #667eea;
        }

        .main-container {
            display: flex;
            height: calc(100vh - 70px);
        }

        .conversations-sidebar {
            width: 320px;
            background: rgba(0, 0, 0, 0.5);
            border-right: 1px solid rgba(255, 255, 255, 0.1);
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-header h2 {
            color: #667eea;
            font-size: 1.3rem;
        }

        .conversation-list {
            list-style: none;
        }

        .conversation-item {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            cursor: pointer;
            transition: background 0.2s;
            text-decoration: none;
            display: block;
            color: inherit;
        }

        .conversation-item:hover {
            background: rgba(102, 126, 234, 0.2);
        }

        .conversation-item.active {
            background: rgba(102, 126, 234, 0.3);
            border-left: 3px solid #667eea;
        }

        .conversation-customer {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .conversation-preview {
            font-size: 0.85rem;
            color: #9ca3af;
        }

        .messages-main {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .messages-header {
            padding: 1.5rem;
            background: rgba(0, 0, 0, 0.5);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .customer-info-header {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .customer-avatar-small {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(45deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        .messages-container {
            flex: 1;
            overflow-y: auto;
            padding: 2rem;
            display: flex;
            flex-direction: column-reverse;
        }

        .message {
            max-width: 70%;
            margin-bottom: 1rem;
            padding: 1rem;
            border-radius: 15px;
            word-wrap: break-word;
        }

        .message-customer {
            align-self: flex-start;
            background: rgba(255, 255, 255, 0.1);
            border-bottom-left-radius: 5px;
        }

        .message-operator {
            align-self: flex-end;
            background: linear-gradient(45deg, #667eea, #764ba2);
            border-bottom-right-radius: 5px;
        }

        .message-paid {
            background: linear-gradient(45deg, #f59e0b, #d97706);
        }

        .message-marketing {
            background: linear-gradient(45deg, #10b981, #059669);
        }

        .message-meta {
            font-size: 0.75rem;
            opacity: 0.7;
            margin-top: 0.5rem;
        }

        .message-type-badge {
            font-size: 0.7rem;
            margin-top: 0.25rem;
            padding: 0.25rem 0.5rem;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 5px;
            display: inline-block;
        }

        .message-input-container {
            padding: 1.5rem;
            background: rgba(0, 0, 0, 0.5);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .message-type-selector {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
        }

        .message-type-option {
            flex: 1;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .message-type-option input[type="radio"] {
            width: 18px;
            height: 18px;
        }

        .message-type-option label {
            cursor: pointer;
            display: flex;
            flex-direction: column;
        }

        .type-label {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .type-description {
            font-size: 0.75rem;
            color: #9ca3af;
        }

        .message-form {
            display: flex;
            gap: 0.5rem;
        }

        .emoji-trigger-btn {
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
            color: white;
        }

        .emoji-trigger-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: scale(1.1);
        }

        .message-input {
            flex: 1;
            padding: 1rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 25px;
            background: rgba(0, 0, 0, 0.3);
            color: white;
            font-size: 1rem;
            resize: none;
        }

        .message-input:focus {
            outline: none;
            border-color: #667eea;
        }

        .btn-send {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            padding: 1rem 2rem;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            transition: transform 0.2s;
        }

        .btn-send:hover {
            transform: scale(1.05);
        }

        .empty-state {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #9ca3af;
            padding: 2rem;
            text-align: center;
        }

        .alert {
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 10px;
        }

        .alert-success {
            background: rgba(34, 197, 94, 0.2);
            border: 1px solid rgba(34, 197, 94, 0.5);
            color: #86efac;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.2);
            border: 1px solid rgba(239, 68, 68, 0.5);
            color: #fca5a5;
        }

        .earnings-info {
            background: rgba(16, 185, 129, 0.2);
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="logo">Operator Dashboard</div>

        <div class="header-actions">
            <div class="operator-badge">
                <?= htmlspecialchars($operatorDisplayName) ?>
            </div>
            <a href="/agents/dashboard.php" class="btn btn-secondary">Dashboard</a>
            <a href="/agents/earnings.php" class="btn btn-secondary">Earnings</a>
            <a href="/agents/logout.php" class="btn btn-secondary">Logout</a>
        </div>
    </header>

    <div class="main-container">
        <aside class="conversations-sidebar">
            <div class="sidebar-header">
                <h2>Conversations</h2>
            </div>

            <?php if (empty($conversations)): ?>
                <div style="padding: 2rem; text-align: center; color: #9ca3af;">
                    <p>No conversations yet.</p>
                </div>
            <?php else: ?>
                <div class="conversation-list">
                    <?php foreach ($conversations as $conv): ?>
                        <?php
                        $convCustomer = $customerManager->getCustomer($conv['customer_id']);
                        $customerName = $convCustomer['username'] ?? 'Unknown';
                        $isActive = $currentConversation && $currentConversation['conversation_id'] === $conv['conversation_id'];
                        ?>
                        <a href="/agents/operator-messages.php?conversation_id=<?= urlencode($conv['conversation_id']) ?>"
                           class="conversation-item <?= $isActive ? 'active' : '' ?>">
                            <div class="conversation-customer"><?= htmlspecialchars($customerName) ?></div>
                            <div class="conversation-preview">
                                <?= $conv['billing_info']['message_count'] ?> messages •
                                Earned: $<?= number_format($conv['billing_info']['total_operator_earned'], 2) ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </aside>

        <main class="messages-main">
            <?php if ($currentConversation && $customer): ?>
                <div class="messages-header">
                    <div class="customer-info-header">
                        <div class="customer-avatar-small">
                            <?= strtoupper(substr($customer['username'], 0, 2)) ?>
                        </div>
                        <div>
                            <div style="font-weight: 600;">
                                <?= htmlspecialchars($customer['username']) ?>
                            </div>
                            <div style="font-size: 0.85rem; color: #9ca3af;">
                                <?= $currentConversation['billing_info']['message_count'] ?> messages •
                                You earned: $<?= number_format($currentConversation['billing_info']['total_operator_earned'], 2) ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="messages-container">
                    <?php if ($messageSuccess): ?>
                        <div class="alert alert-success"><?= htmlspecialchars($messageSuccess) ?></div>
                    <?php endif; ?>

                    <?php if ($messageError): ?>
                        <div class="alert alert-error"><?= htmlspecialchars($messageError) ?></div>
                    <?php endif; ?>

                    <?php if (empty($messages)): ?>
                        <div class="empty-state">
                            <h3>No messages yet</h3>
                            <p>Start the conversation with <?= htmlspecialchars($customer['username']) ?>!</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($messages as $message): ?>
                            <?php
                            $msgClass = 'message-' . $message['sender_type'];
                            if (isset($message['message_type'])) {
                                $msgClass .= ' message-' . $message['message_type'];
                            }
                            ?>
                            <div class="message <?= $msgClass ?>">
                                <div><?= nl2br(htmlspecialchars($message['content'])) ?></div>

                                <div class="message-meta">
                                    <?= date('g:i A', strtotime($message['sent_at'])) ?>

                                    <?php if ($message['sender_type'] === 'operator' && isset($message['message_type'])): ?>
                                        <div class="message-type-badge">
                                            <?php
                                            $typeLabels = [
                                                'free' => '🎁 Free Reply',
                                                'paid' => '💰 Paid ($1.99)',
                                                'marketing' => '📢 Marketing'
                                            ];
                                            echo $typeLabels[$message['message_type']] ?? '';
                                            ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="message-input-container">
                    <div class="earnings-info">
                        💰 Your earnings: $<?= number_format($currentConversation['billing_info']['total_operator_earned'], 2) ?> from this conversation
                    </div>

                    <form method="POST" id="messageForm">
                        <input type="hidden" name="conversation_id" value="<?= htmlspecialchars($currentConversation['conversation_id']) ?>">

                        <div class="message-type-selector">
                            <div class="message-type-option">
                                <input type="radio" name="message_type" value="free" id="type_free" checked>
                                <label for="type_free">
                                    <span class="type-label">🎁 Free Reply</span>
                                    <span class="type-description">Standard reply, gives customer 1 free message</span>
                                </label>
                            </div>

                            <div class="message-type-option">
                                <input type="radio" name="message_type" value="paid" id="type_paid">
                                <label for="type_paid">
                                    <span class="type-label">💰 Paid Message</span>
                                    <span class="type-description">Charges customer $1.99, you earn $1.29</span>
                                </label>
                            </div>

                            <div class="message-type-option">
                                <input type="radio" name="message_type" value="marketing" id="type_marketing">
                                <label for="type_marketing">
                                    <span class="type-label">📢 Marketing</span>
                                    <span class="type-description">Promotional message, free for customer</span>
                                </label>
                            </div>
                        </div>

                        <div class="message-form">
                            <button type="button" class="emoji-trigger-btn" id="operator-emoji-trigger">
                                😀
                            </button>
                            <textarea
                                name="content"
                                id="operator-message-input"
                                class="message-input"
                                placeholder="Type your message..."
                                rows="1"
                                required
                            ></textarea>
                            <button type="submit" name="send_message" class="btn-send">
                                Send
                            </button>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <h3>No Conversation Selected</h3>
                    <p>Select a conversation from the sidebar to start messaging.</p>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        // Auto-resize textarea
        const textarea = document.querySelector('.message-input');
        if (textarea) {
            textarea.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = Math.min(this.scrollHeight, 150) + 'px';
            });
        }

        // Auto-scroll to bottom
        const messagesContainer = document.querySelector('.messages-container');
        if (messagesContainer) {
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }

        // Update button text based on message type
        const messageTypeInputs = document.querySelectorAll('input[name="message_type"]');
        const sendButton = document.querySelector('.btn-send');

        messageTypeInputs.forEach(input => {
            input.addEventListener('change', function() {
                const labels = {
                    'free': 'Send Free',
                    'paid': 'Send Paid ($1.99)',
                    'marketing': 'Send Marketing'
                };
                sendButton.textContent = labels[this.value] || 'Send';
            });
        });

        // Initialize emoji picker
        if (document.getElementById('operator-emoji-trigger')) {
            const emojiPicker = new EmojiPicker();
            emojiPicker.init('#operator-emoji-trigger', '#operator-message-input');
        }
    </script>
</body>
</html>
