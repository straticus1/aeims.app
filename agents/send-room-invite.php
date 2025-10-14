<?php
/**
 * Operator Send Room Invite
 * Invite customers to rooms with free time
 */

session_start();

if (!isset($_SESSION['operator_id'])) {
    header('Location: /agents/login.php');
    exit;
}

require_once '../services/OperatorManager.php';
require_once '../services/CustomerManager.php';
require_once '../services/ChatRoomManager.php';
require_once '../services/RoomInviteManager.php';

try {
    $operatorManager = new \AEIMS\Services\OperatorManager();
    $customerManager = new \AEIMS\Services\CustomerManager();
    $roomManager = new \AEIMS\Services\ChatRoomManager();
    $inviteManager = new \AEIMS\Services\RoomInviteManager();

    $operator = $operatorManager->getOperator($_SESSION['operator_id']);

    if (!$operator) {
        session_destroy();
        header('Location: /agents/login.php');
        exit;
    }

    // Handle invite submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_invite'])) {
        $customerId = $_POST['customer_id'];
        $roomId = $_POST['room_id'];
        $freeMinutes = (int)$_POST['free_minutes'];
        $message = trim($_POST['message']);

        try {
            $invite = $inviteManager->createInvite(
                $_SESSION['operator_id'],
                $customerId,
                $roomId,
                $freeMinutes,
                $message
            );

            $_SESSION['invite_success'] = 'Room invite sent successfully!';
            header('Location: /agents/send-room-invite.php');
            exit;
        } catch (Exception $e) {
            $_SESSION['invite_error'] = $e->getMessage();
        }
    }

    // Get operator's rooms
    $myRooms = $roomManager->getOperatorRooms($_SESSION['operator_id']);

    // Get list of recent customers
    $conversationsFile = __DIR__ . '/../data/conversations.json';
    $customers = [];

    if (file_exists($conversationsFile)) {
        $conversations = json_decode(file_get_contents($conversationsFile), true);
        $customerIds = [];

        foreach ($conversations as $conv) {
            if ($conv['operator_id'] === $_SESSION['operator_id']) {
                if (!in_array($conv['customer_id'], $customerIds)) {
                    $customerIds[] = $conv['customer_id'];
                    $customer = $customerManager->getCustomer($conv['customer_id']);
                    if ($customer) {
                        $customers[] = $customer;
                    }
                }
            }
        }
    }

    // Get sent invites
    $sentInvites = $inviteManager->getOperatorInvites($_SESSION['operator_id']);

} catch (Exception $e) {
    error_log("Send room invite error: " . $e->getMessage());
    $_SESSION['invite_error'] = "An error occurred. Please try again.";
}

$inviteSuccess = $_SESSION['invite_success'] ?? null;
$inviteError = $_SESSION['invite_error'] ?? null;
unset($_SESSION['invite_success'], $_SESSION['invite_error']);

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
    <title>Send Room Invite - Operator Dashboard</title>
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
            min-height: 100vh;
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
            display: inline-block;
        }

        .btn-secondary {
            background: transparent;
            color: #667eea;
            border: 1px solid #667eea;
        }

        .btn-primary {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
        }

        .btn-primary:hover {
            transform: scale(1.05);
        }

        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .page-title {
            font-size: 2rem;
            margin-bottom: 2rem;
            color: #667eea;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }

        .alert {
            padding: 1rem;
            margin-bottom: 1.5rem;
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

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }

        .card {
            background: rgba(0, 0, 0, 0.5);
            border-radius: 15px;
            padding: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .card h2 {
            color: #667eea;
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #9ca3af;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            color: white;
            font-size: 1rem;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            background: rgba(255, 255, 255, 0.15);
        }

        select.form-control {
            cursor: pointer;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }

        .customer-list, .room-list {
            max-height: 250px;
            overflow-y: auto;
        }

        .selectable-item {
            background: rgba(255, 255, 255, 0.05);
            padding: 1rem;
            margin-bottom: 0.5rem;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .selectable-item:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .selectable-item.selected {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.2);
        }

        .selectable-item input[type="radio"] {
            margin-right: 0.5rem;
        }

        .invite-history {
            margin-top: 2rem;
        }

        .invite-item {
            background: rgba(255, 255, 255, 0.05);
            padding: 1rem;
            margin-bottom: 0.5rem;
            border-radius: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .invite-status {
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .status-pending {
            background: rgba(251, 191, 36, 0.3);
            color: #fbbf24;
        }

        .status-accepted {
            background: rgba(34, 197, 94, 0.3);
            color: #22c55e;
        }

        .status-declined {
            background: rgba(239, 68, 68, 0.3);
            color: #ef4444;
        }

        .status-used {
            background: rgba(107, 114, 128, 0.3);
            color: #9ca3af;
        }

        .room-rate {
            font-size: 0.85rem;
            color: #9ca3af;
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
            <a href="/agents/create-room.php" class="btn btn-secondary">My Rooms</a>
            <a href="/agents/logout.php" class="btn btn-secondary">Logout</a>
        </div>
    </header>

    <div class="container">
        <h1 class="page-title">🎁 Send Room Invite</h1>

        <?php if ($inviteSuccess): ?>
            <div class="alert alert-success"><?= htmlspecialchars($inviteSuccess) ?></div>
        <?php endif; ?>

        <?php if ($inviteError): ?>
            <div class="alert alert-error"><?= htmlspecialchars($inviteError) ?></div>
        <?php endif; ?>

        <div class="grid-2">
            <div class="card">
                <h2>Send New Invite</h2>

                <form method="POST" id="inviteForm">
                    <div class="form-group">
                        <label>Select Room</label>
                        <?php if (empty($myRooms)): ?>
                            <p style="color: #9ca3af;">No rooms created. <a href="/agents/create-room.php" style="color: #667eea;">Create a room first</a>.</p>
                        <?php else: ?>
                            <div class="room-list">
                                <?php foreach ($myRooms as $room): ?>
                                    <div class="selectable-item" onclick="selectRoom('<?= htmlspecialchars($room['room_id']) ?>', this)">
                                        <div>
                                            <input type="radio" name="room_id" value="<?= htmlspecialchars($room['room_id']) ?>" required>
                                            <?= htmlspecialchars($room['room_name']) ?>
                                            <div class="room-rate">
                                                $<?= number_format($room['per_minute_rate'], 2) ?>/min
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label>Select Customer</label>
                        <?php if (empty($customers)): ?>
                            <p style="color: #9ca3af;">No customers available. Start chatting with customers first!</p>
                        <?php else: ?>
                            <div class="customer-list">
                                <?php foreach ($customers as $customer): ?>
                                    <div class="selectable-item" onclick="selectCustomer('<?= htmlspecialchars($customer['customer_id']) ?>', this)">
                                        <input type="radio" name="customer_id" value="<?= htmlspecialchars($customer['customer_id']) ?>" required>
                                        <?= htmlspecialchars($customer['username']) ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label>Free Minutes</label>
                        <input type="number" name="free_minutes" class="form-control" min="5" max="60" value="15" required>
                        <small style="color: #9ca3af;">How many free minutes to offer (5-60)</small>
                    </div>

                    <div class="form-group">
                        <label>Personal Message</label>
                        <textarea name="message" class="form-control" placeholder="Add a personal invitation message..." required></textarea>
                    </div>

                    <button type="submit" name="send_invite" class="btn btn-primary" style="width: 100%;">
                        🎁 Send Invite
                    </button>
                </form>
            </div>

            <div class="card">
                <h2>Recent Invites</h2>

                <div class="invite-history">
                    <?php if (empty($sentInvites)): ?>
                        <p style="color: #9ca3af; text-align: center;">No invites sent yet.</p>
                    <?php else: ?>
                        <?php foreach (array_slice($sentInvites, 0, 10) as $invite): ?>
                            <?php
                            $customer = $customerManager->getCustomer($invite['customer_id']);
                            $customerName = $customer['username'] ?? 'Unknown';
                            $room = $roomManager->getRoom($invite['room_id']);
                            $roomName = $room['room_name'] ?? 'Unknown Room';
                            ?>
                            <div class="invite-item">
                                <div>
                                    <div style="font-weight: 600;">
                                        🏠 <?= htmlspecialchars($roomName) ?>
                                    </div>
                                    <div style="font-size: 0.85rem; color: #9ca3af;">
                                        To: <?= htmlspecialchars($customerName) ?> • <?= $invite['free_minutes'] ?> min free
                                    </div>
                                    <div style="font-size: 0.75rem; color: #9ca3af;">
                                        <?= date('M j, g:i A', strtotime($invite['created_at'])) ?>
                                    </div>
                                </div>
                                <div class="invite-status status-<?= htmlspecialchars($invite['status']) ?>">
                                    <?= ucfirst($invite['status']) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        function selectRoom(roomId, element) {
            document.querySelectorAll('.room-list .selectable-item').forEach(item => item.classList.remove('selected'));
            element.classList.add('selected');
            element.querySelector('input[type="radio"]').checked = true;
        }

        function selectCustomer(customerId, element) {
            document.querySelectorAll('.customer-list .selectable-item').forEach(item => item.classList.remove('selected'));
            element.classList.add('selected');
            element.querySelector('input[type="radio"]').checked = true;
        }
    </script>
</body>
</html>
