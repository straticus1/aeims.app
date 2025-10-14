<?php
/**
 * Chat Room Interface
 * Real-time multi-user chat room
 */

session_start();

// Check authentication
require_once __DIR__ . '/../../includes/CustomerAuth.php';
$auth = new CustomerAuth();
$auth->requireLogin();

// Load services
require_once __DIR__ . '/../../services/SiteManager.php';
require_once __DIR__ . '/../../services/ChatRoomManager.php';
require_once __DIR__ . '/../../services/OperatorManager.php';

$siteManager = new \AEIMS\Services\SiteManager();
$roomManager = new \AEIMS\Services\ChatRoomManager();
$operatorManager = new \AEIMS\Services\OperatorManager();

$hostname = preg_replace('/^www\./', '', $_SERVER['HTTP_HOST'] ?? 'flirts.nyc');
$hostname = preg_replace('/:\d+$/', '', $hostname);
$site = $siteManager->getSite($hostname);
$customer = $auth->getCurrentCustomer();

// Get room ID
$roomId = $_GET['room_id'] ?? null;
$pin = $_GET['pin'] ?? null;

if (!$roomId) {
    header('Location: /rooms.php');
    exit;
}

// Try to join the room
$joinResult = $roomManager->joinRoom($roomId, $customer['customer_id'], $pin);

if (!$joinResult['success']) {
    $_SESSION['error_message'] = $joinResult['message'];
    header('Location: /rooms.php');
    exit;
}

$room = $joinResult['room'];
$operator = $operatorManager->getOperatorById($room['operator_id']);

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    if ($action === 'send_message') {
        $content = $_POST['content'] ?? '';
        if (!empty($content)) {
            $result = $roomManager->sendMessage($roomId, $customer['customer_id'], 'customer', $content);
            echo json_encode($result);
        } else {
            echo json_encode(['success' => false, 'message' => 'Empty message']);
        }
        exit;
    }

    if ($action === 'get_messages') {
        $messages = $roomManager->getRoomMessages($roomId, 50);
        echo json_encode(['success' => true, 'messages' => $messages]);
        exit;
    }

    if ($action === 'leave_room') {
        $roomManager->leaveRoom($roomId, $customer['customer_id']);
        echo json_encode(['success' => true]);
        exit;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($room['name']) ?> - Chat Room</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: <?= $site['theme']['font_family'] ?>;
            background: linear-gradient(135deg, #1a1a1a 0%, #2d1b3d 100%);
            color: #ffffff;
            height: 100vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .header {
            background: rgba(0, 0, 0, 0.9);
            padding: 1rem;
            border-bottom: 1px solid rgba(239, 68, 68, 0.3);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .room-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .room-title {
            font-size: 1.3rem;
            font-weight: bold;
            color: <?= $site['theme']['primary_color'] ?>;
        }

        .room-subtitle {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.7);
        }

        .header-actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
            font-size: 0.9rem;
        }

        .btn-danger {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
            border: 1px solid #ef4444;
        }

        .btn-danger:hover {
            background: rgba(239, 68, 68, 0.3);
        }

        .chat-container {
            flex: 1;
            display: flex;
            overflow: hidden;
        }

        .members-sidebar {
            width: 200px;
            background: rgba(0, 0, 0, 0.5);
            border-right: 1px solid rgba(255, 255, 255, 0.1);
            padding: 1rem;
            overflow-y: auto;
        }

        .sidebar-title {
            font-size: 0.9rem;
            font-weight: bold;
            margin-bottom: 1rem;
            color: <?= $site['theme']['primary_color'] ?>;
        }

        .member-item {
            padding: 0.5rem;
            margin-bottom: 0.5rem;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 6px;
            font-size: 0.9rem;
        }

        .messages-area {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .messages-container {
            flex: 1;
            padding: 1.5rem;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .message {
            display: flex;
            gap: 0.75rem;
            align-items: flex-start;
        }

        .message-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(45deg, <?= $site['theme']['primary_color'] ?>, <?= $site['theme']['accent_color'] ?>);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            flex-shrink: 0;
        }

        .message-content {
            flex: 1;
        }

        .message-header {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            margin-bottom: 0.3rem;
        }

        .message-sender {
            font-weight: bold;
            color: <?= $site['theme']['primary_color'] ?>;
        }

        .message-time {
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.5);
        }

        .message-text {
            background: rgba(255, 255, 255, 0.05);
            padding: 0.75rem;
            border-radius: 8px;
            line-height: 1.5;
        }

        .message.operator .message-avatar {
            background: linear-gradient(45deg, #f39c12, #e74c3c);
        }

        .message.operator .message-sender {
            color: #f39c12;
        }

        .input-area {
            padding: 1rem;
            background: rgba(0, 0, 0, 0.5);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .input-container {
            display: flex;
            gap: 0.5rem;
        }

        .message-input {
            flex: 1;
            padding: 0.75rem 1rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            background: rgba(0, 0, 0, 0.3);
            color: #fff;
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
            padding: 0.75rem 1.5rem;
        }

        .btn-send:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(239, 68, 68, 0.3);
        }

        .billing-notice {
            background: rgba(239, 68, 68, 0.1);
            padding: 0.75rem;
            text-align: center;
            font-size: 0.9rem;
            border-top: 1px solid rgba(239, 68, 68, 0.3);
        }

        @media (max-width: 768px) {
            .members-sidebar {
                display: none;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="room-info">
            <div>
                <div class="room-title"><?= htmlspecialchars($room['name']) ?></div>
                <div class="room-subtitle">Hosted by <?= htmlspecialchars($operator['name'] ?? 'Unknown') ?></div>
            </div>
        </div>
        <div class="header-actions">
            <button class="btn btn-danger" onclick="leaveRoom()">Leave Room</button>
        </div>
    </header>

    <?php if ($room['entry_fee'] > 0 && $joinResult['entry_fee'] > 0): ?>
        <div class="billing-notice">
            You have been charged $<?= number_format($joinResult['entry_fee'], 2) ?> entry fee
        </div>
    <?php endif; ?>

    <?php if ($room['per_minute_rate'] > 0): ?>
        <div class="billing-notice">
            Billing at $<?= number_format($room['per_minute_rate'], 2) ?>/minute
        </div>
    <?php endif; ?>

    <div class="chat-container">
        <aside class="members-sidebar">
            <div class="sidebar-title">Members (<?= $room['current_users'] ?>)</div>
            <?php foreach ($room['members'] as $member): ?>
                <div class="member-item">User<?= substr($member['customer_id'], -4) ?></div>
            <?php endforeach; ?>
        </aside>

        <div class="messages-area">
            <div class="messages-container" id="messagesContainer">
                <div style="text-align: center; color: rgba(255, 255, 255, 0.5); padding: 2rem;">
                    Loading messages...
                </div>
            </div>

            <div class="input-area">
                <form id="messageForm" onsubmit="sendMessage(event)">
                    <div class="input-container">
                        <textarea
                            id="messageInput"
                            class="message-input"
                            placeholder="Type your message..."
                            rows="1"
                            maxlength="1000"
                        ></textarea>
                        <button type="submit" class="btn btn-send">Send</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        const roomId = '<?= htmlspecialchars($roomId) ?>';
        const customerId = '<?= htmlspecialchars($customer['customer_id']) ?>';
        const customerName = 'User<?= substr($customer['customer_id'], -4) ?>';
        let lastMessageId = null;
        let refreshInterval;

        // Load messages on page load
        loadMessages();

        // Poll for new messages every 2 seconds
        refreshInterval = setInterval(loadMessages, 2000);

        // Track time for billing
        const joinTime = Date.now();
        setInterval(() => {
            const minutesElapsed = Math.floor((Date.now() - joinTime) / 60000);
            // In a real system, you'd send this to the server for billing
        }, 60000); // Every minute

        function loadMessages() {
            fetch(window.location.href, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=get_messages'
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    renderMessages(data.messages);
                }
            })
            .catch(err => console.error('Error loading messages:', err));
        }

        function renderMessages(messages) {
            const container = document.getElementById('messagesContainer');
            const wasAtBottom = container.scrollHeight - container.scrollTop === container.clientHeight;

            // Clear container
            container.innerHTML = '';

            if (messages.length === 0) {
                container.innerHTML = '<div style="text-align: center; color: rgba(255, 255, 255, 0.5); padding: 2rem;">No messages yet. Be the first to say hello!</div>';
                return;
            }

            messages.forEach(msg => {
                const messageDiv = document.createElement('div');
                messageDiv.className = `message ${msg.sender_type}`;

                const initial = msg.sender_type === 'operator' ? 'O' : 'U';
                const senderName = msg.sender_type === 'operator' ? '<?= htmlspecialchars($operator['name'] ?? 'Operator') ?>' : `User${msg.sender_id.slice(-4)}`;
                const time = new Date(msg.timestamp).toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'});

                messageDiv.innerHTML = `
                    <div class="message-avatar">${initial}</div>
                    <div class="message-content">
                        <div class="message-header">
                            <span class="message-sender">${senderName}</span>
                            <span class="message-time">${time}</span>
                        </div>
                        <div class="message-text">${escapeHtml(msg.content)}</div>
                    </div>
                `;

                container.appendChild(messageDiv);
            });

            // Auto-scroll to bottom if user was already at bottom
            if (wasAtBottom || messages.length === 1) {
                container.scrollTop = container.scrollHeight;
            }
        }

        function sendMessage(event) {
            event.preventDefault();

            const input = document.getElementById('messageInput');
            const content = input.value.trim();

            if (!content) return;

            fetch(window.location.href, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=send_message&content=${encodeURIComponent(content)}`
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    input.value = '';
                    loadMessages();
                } else {
                    alert('Failed to send message: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(err => {
                console.error('Error sending message:', err);
                alert('Failed to send message');
            });
        }

        function leaveRoom() {
            if (confirm('Are you sure you want to leave this room?')) {
                clearInterval(refreshInterval);

                fetch(window.location.href, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'action=leave_room'
                })
                .then(() => {
                    window.location.href = '/rooms.php';
                })
                .catch(() => {
                    window.location.href = '/rooms.php';
                });
            }
        }

        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        }

        // Leave room on page unload
        window.addEventListener('beforeunload', () => {
            clearInterval(refreshInterval);
            navigator.sendBeacon(window.location.href, 'action=leave_room');
        });

        // Auto-resize textarea
        document.getElementById('messageInput').addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 150) + 'px';
        });

        // Submit on Enter (but allow Shift+Enter for newlines)
        document.getElementById('messageInput').addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                document.getElementById('messageForm').dispatchEvent(new Event('submit'));
            }
        });
    </script>
</body>
</html>
