<?php
/**
 * Customer Messaging Interface
 * View and send messages to operators
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['customer_id'])) {
    header('Location: /');
    exit;
}

require_once 'services/SiteManager.php';
require_once 'services/CustomerManager.php';
require_once 'services/OperatorManager.php';
require_once 'services/MessagingManager.php';

try {
    $siteManager = new \AEIMS\Services\SiteManager();
    $customerManager = new \AEIMS\Services\CustomerManager();
    $operatorManager = new \AEIMS\Services\OperatorManager();
    $messagingManager = new \AEIMS\Services\MessagingManager();

    $hostname = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $hostname = preg_replace('/^www\./', '', $hostname);
    $hostname = preg_replace('/:\d+$/', '', $hostname);

    $site = $siteManager->getSite($hostname);
    $customer = $customerManager->getCustomer($_SESSION['customer_id']);

    if (!$site || !$site['active'] || !$customer) {
        session_destroy();
        header('Location: /');
        exit;
    }

    // Handle message sending
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
        $conversationId = $_POST['conversation_id'];
        $content = trim($_POST['content']);

        if (!empty($content)) {
            try {
                $message = $messagingManager->sendMessage(
                    $conversationId,
                    $_SESSION['customer_id'],
                    $content,
                    'customer'
                );

                // Reload customer to get updated credits
                $customer = $customerManager->getCustomer($_SESSION['customer_id']);

                // Set success message
                $_SESSION['message_success'] = 'Message sent successfully!';
            } catch (Exception $e) {
                $_SESSION['message_error'] = $e->getMessage();
            }
        }

        // Redirect to prevent form resubmission
        header('Location: /messages.php?conversation_id=' . urlencode($conversationId));
        exit;
    }

    // Handle new conversation start
    if (isset($_GET['operator_id']) && !isset($_GET['conversation_id'])) {
        $operatorId = $_GET['operator_id'];

        // Check if conversation already exists
        $existingConversation = null;
        $allConversations = $messagingManager->getCustomerConversations($_SESSION['customer_id']);

        foreach ($allConversations as $conv) {
            if ($conv['operator_id'] === $operatorId && $conv['status'] === 'active') {
                $existingConversation = $conv['conversation_id'];
                break;
            }
        }

        if ($existingConversation) {
            header('Location: /messages.php?conversation_id=' . urlencode($existingConversation));
            exit;
        }

        // Start new conversation
        $conversationId = $messagingManager->startConversation(
            $_SESSION['customer_id'],
            $operatorId,
            $hostname
        );

        header('Location: /messages.php?conversation_id=' . urlencode($conversationId));
        exit;
    }

    // Get conversations for customer
    $conversations = $messagingManager->getCustomerConversations($_SESSION['customer_id']);

    // Get current conversation
    $currentConversation = null;
    $messages = [];
    $operator = null;

    if (isset($_GET['conversation_id'])) {
        $currentConversation = $messagingManager->getConversation($_GET['conversation_id']);

        if ($currentConversation && $currentConversation['customer_id'] === $_SESSION['customer_id']) {
            $messages = $messagingManager->getConversationMessages($_GET['conversation_id']);
            $operator = $operatorManager->getOperator($currentConversation['operator_id']);
        }
    } elseif (!empty($conversations)) {
        // Default to first conversation
        $currentConversation = $conversations[0];
        $messages = $messagingManager->getConversationMessages($currentConversation['conversation_id']);
        $operator = $operatorManager->getOperator($currentConversation['operator_id']);
    }

} catch (Exception $e) {
    error_log("Messages error: " . $e->getMessage());
    $_SESSION['message_error'] = "An error occurred. Please try again.";
}

$messageSuccess = $_SESSION['message_success'] ?? null;
$messageError = $_SESSION['message_error'] ?? null;
unset($_SESSION['message_success'], $_SESSION['message_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - <?= htmlspecialchars($site['name']) ?></title>
    <link rel="icon" href="<?= htmlspecialchars($site['theme']['favicon_url']) ?>">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: <?= $site['theme']['font_family'] ?>;
            background: linear-gradient(135deg, #1a1a1a 0%, #2d1b3d 100%);
            color: <?= $site['theme']['text_color'] ?>;
            height: 100vh;
            overflow: hidden;
        }

        .header {
            background: rgba(0, 0, 0, 0.9);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(239, 68, 68, 0.3);
        }

        .logo {
            font-size: 1.5rem;
            font-weight: bold;
            color: <?= $site['theme']['primary_color'] ?>;
            text-decoration: none;
        }

        .header-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .credits-display {
            background: linear-gradient(45deg, <?= $site['theme']['primary_color'] ?>, <?= $site['theme']['secondary_color'] ?>);
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
            color: <?= $site['theme']['primary_color'] ?>;
            border: 1px solid <?= $site['theme']['primary_color'] ?>;
        }

        .main-container {
            display: flex;
            height: calc(100vh - 70px);
        }

        .conversations-sidebar {
            width: 320px;
            background: rgba(0, 0, 0, 0.5);
            border-right: 1px solid rgba(239, 68, 68, 0.3);
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(239, 68, 68, 0.3);
        }

        .sidebar-header h2 {
            color: <?= $site['theme']['primary_color'] ?>;
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
            background: rgba(239, 68, 68, 0.1);
        }

        .conversation-item.active {
            background: rgba(239, 68, 68, 0.2);
            border-left: 3px solid <?= $site['theme']['primary_color'] ?>;
        }

        .conversation-operator {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .conversation-preview {
            font-size: 0.85rem;
            color: #9ca3af;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .messages-main {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .messages-header {
            padding: 1.5rem;
            background: rgba(0, 0, 0, 0.5);
            border-bottom: 1px solid rgba(239, 68, 68, 0.3);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .operator-info-header {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .operator-avatar-small {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(45deg, <?= $site['theme']['primary_color'] ?>, <?= $site['theme']['accent_color'] ?>);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
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
            align-self: flex-end;
            background: linear-gradient(45deg, <?= $site['theme']['primary_color'] ?>, <?= $site['theme']['secondary_color'] ?>);
            color: white;
            border-bottom-right-radius: 5px;
        }

        .message-operator {
            align-self: flex-start;
            background: rgba(255, 255, 255, 0.1);
            border-bottom-left-radius: 5px;
        }

        .message-meta {
            font-size: 0.75rem;
            opacity: 0.7;
            margin-top: 0.5rem;
        }

        .message-billing {
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
            border-top: 1px solid rgba(239, 68, 68, 0.3);
        }

        .billing-info {
            margin-bottom: 1rem;
            padding: 0.75rem;
            background: rgba(239, 68, 68, 0.1);
            border-radius: 10px;
            font-size: 0.9rem;
        }

        .message-form {
            display: flex;
            gap: 1rem;
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
            border-color: <?= $site['theme']['primary_color'] ?>;
        }

        .btn-send {
            background: linear-gradient(45deg, <?= $site['theme']['primary_color'] ?>, <?= $site['theme']['secondary_color'] ?>);
            color: white;
            padding: 1rem 2rem;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            transition: transform 0.2s;
        }

        .btn-send:hover:not(:disabled) {
            transform: scale(1.05);
        }

        .btn-send:disabled {
            opacity: 0.5;
            cursor: not-allowed;
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

        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
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
    </style>
</head>
<body>
    <header class="header">
        <a href="/dashboard.php" class="logo"><?= htmlspecialchars($site['name']) ?></a>

        <div class="header-actions">
            <div class="credits-display">
                Credits: $<?= number_format($customer['billing']['credits'], 2) ?>
            </div>
            <a href="/dashboard.php" class="btn btn-secondary">Dashboard</a>
            <a href="/search-operators.php" class="btn btn-secondary">Find Operators</a>
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
                    <p style="margin-top: 1rem;"><a href="/dashboard.php" class="btn btn-secondary">Browse Operators</a></p>
                </div>
            <?php else: ?>
                <div class="conversation-list">
                    <?php foreach ($conversations as $conv): ?>
                        <?php
                        $convOperator = $operatorManager->getOperator($conv['operator_id']);
                        $operatorName = $convOperator['profile']['display_names'][$hostname] ?? $convOperator['name'] ?? 'Unknown';
                        $isActive = $currentConversation && $currentConversation['conversation_id'] === $conv['conversation_id'];
                        ?>
                        <a href="/messages.php?conversation_id=<?= urlencode($conv['conversation_id']) ?>"
                           class="conversation-item <?= $isActive ? 'active' : '' ?>">
                            <div class="conversation-operator"><?= htmlspecialchars($operatorName) ?></div>
                            <div class="conversation-preview">
                                <?= $conv['billing_info']['message_count'] ?> messages •
                                Free msgs: <?= $conv['billing_info']['customer_free_messages'] ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </aside>

        <main class="messages-main">
            <?php if ($currentConversation && $operator): ?>
                <div class="messages-header">
                    <div class="operator-info-header">
                        <div class="operator-avatar-small">
                            <?= strtoupper(substr($operator['profile']['display_names'][$hostname] ?? $operator['name'], 0, 2)) ?>
                        </div>
                        <div>
                            <div style="font-weight: 600;">
                                <?= htmlspecialchars($operator['profile']['display_names'][$hostname] ?? $operator['name']) ?>
                            </div>
                            <div style="font-size: 0.85rem; color: #9ca3af;">
                                <?= $currentConversation['billing_info']['message_count'] ?> messages
                            </div>
                        </div>
                    </div>

                    <a href="/operator-profile.php?id=<?= $operator['id'] ?>" class="btn btn-secondary">
                        View Profile
                    </a>
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
                            <h3>Start the conversation!</h3>
                            <p>Send your first message below.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($messages as $message): ?>
                            <div class="message message-<?= $message['sender_type'] ?>">
                                <div><?= nl2br(htmlspecialchars($message['content'])) ?></div>

                                <div class="message-meta">
                                    <?= date('g:i A', strtotime($message['sent_at'])) ?>

                                    <?php if ($message['sender_type'] === 'customer' && $message['billing']['cost'] > 0): ?>
                                        <div class="message-billing">
                                            💳 Charged: $<?= number_format($message['billing']['cost'], 2) ?>
                                        </div>
                                    <?php elseif ($message['sender_type'] === 'customer' && $message['billing']['used_free_message']): ?>
                                        <div class="message-billing">
                                            🎁 Free message
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="message-input-container">
                    <div class="billing-info">
                        💰 Message cost: $0.50 each
                        <?php if ($currentConversation['billing_info']['customer_free_messages'] > 0): ?>
                            | 🎁 You have <?= $currentConversation['billing_info']['customer_free_messages'] ?> free message(s)
                        <?php endif; ?>
                    </div>

                    <form method="POST" class="message-form">
                        <input type="hidden" name="conversation_id" value="<?= htmlspecialchars($currentConversation['conversation_id']) ?>">
                        <textarea
                            name="content"
                            class="message-input"
                            placeholder="Type your message..."
                            rows="1"
                            required
                            <?= $customer['billing']['credits'] < 0.50 && $currentConversation['billing_info']['customer_free_messages'] === 0 ? 'disabled' : '' ?>
                        ></textarea>
                        <button
                            type="submit"
                            name="send_message"
                            class="btn-send"
                            <?= $customer['billing']['credits'] < 0.50 && $currentConversation['billing_info']['customer_free_messages'] === 0 ? 'disabled' : '' ?>
                        >
                            <?php if ($currentConversation['billing_info']['customer_free_messages'] > 0): ?>
                                Send Free
                            <?php else: ?>
                                Send ($0.50)
                            <?php endif; ?>
                        </button>
                    </form>

                    <?php if ($customer['billing']['credits'] < 0.50 && $currentConversation['billing_info']['customer_free_messages'] === 0): ?>
                        <div class="alert alert-error" style="margin-top: 1rem;">
                            Insufficient credits. <a href="/payment.php" style="color: <?= $site['theme']['primary_color'] ?>; text-decoration: underline;">Add credits</a> to continue messaging.
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <h3>No Conversation Selected</h3>
                    <p>Select a conversation from the sidebar or start a new one.</p>
                    <a href="/dashboard.php" class="btn btn-secondary" style="margin-top: 1rem;">Browse Operators</a>
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

        // Auto-scroll to bottom (latest messages)
        const messagesContainer = document.querySelector('.messages-container');
        if (messagesContainer) {
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }
    </script>
</body>
</html>
