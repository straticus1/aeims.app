<?php
/**
 * Operator Room Management
 * Create and manage chat rooms
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check operator authentication
if (!isset($_SESSION['operator_id'])) {
    header('Location: /agents/login.php');
    exit;
}

require_once __DIR__ . '/../services/ChatRoomManager.php';

$roomManager = new \AEIMS\Services\ChatRoomManager();
$operatorId = $_SESSION['operator_id'];

// Handle form submission
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $roomName = $_POST['room_name'] ?? '';
        $description = $_POST['description'] ?? '';
        $pinCode = $_POST['pin_code'] ?? null;
        $entryFee = floatval($_POST['entry_fee'] ?? 0);
        $perMinuteRate = floatval($_POST['per_minute_rate'] ?? 0);

        if (empty($roomName) || empty($description)) {
            $message = 'Room name and description are required';
            $messageType = 'error';
        } else {
            try {
                $room = $roomManager->createRoom(
                    $operatorId,
                    $roomName,
                    $description,
                    $pinCode ?: null,
                    $entryFee,
                    $perMinuteRate
                );
                $message = 'Room created successfully!';
                $messageType = 'success';
            } catch (Exception $e) {
                $message = 'Error creating room: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    } elseif ($action === 'delete') {
        $roomId = $_POST['room_id'] ?? '';
        if ($roomId) {
            $roomManager->deleteRoom($roomId);
            $message = 'Room deactivated successfully';
            $messageType = 'success';
        }
    }
}

// Get operator's rooms
$myRooms = $roomManager->getRoomsByOperator($operatorId);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Chat Rooms - AEIMS Operator Portal</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            color: #ffffff;
            min-height: 100vh;
        }

        .header {
            background: rgba(0, 0, 0, 0.5);
            padding: 1rem 2rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: bold;
            color: #ef4444;
        }

        .nav-links {
            display: flex;
            gap: 1rem;
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

        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .btn-primary {
            background: linear-gradient(45deg, #ef4444, #dc2626);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(239, 68, 68, 0.3);
        }

        .btn-danger {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
            border: 1px solid #ef4444;
        }

        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .page-title {
            font-size: 2rem;
            margin-bottom: 2rem;
            background: linear-gradient(45deg, #ef4444, #f59e0b);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }

        .alert-success {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid #22c55e;
            color: #22c55e;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid #ef4444;
            color: #ef4444;
        }

        .card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .card-title {
            font-size: 1.3rem;
            margin-bottom: 1.5rem;
            color: #ef4444;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: rgba(255, 255, 255, 0.9);
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            background: rgba(0, 0, 0, 0.3);
            color: #fff;
            font-size: 1rem;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #ef4444;
            box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.2);
        }

        .form-hint {
            font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.6);
            margin-top: 0.3rem;
        }

        .rooms-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .room-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            padding: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .room-name {
            font-size: 1.2rem;
            font-weight: bold;
            color: #ef4444;
            margin-bottom: 0.5rem;
        }

        .room-description {
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 1rem;
            line-height: 1.5;
        }

        .room-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.5rem;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }

        .stat-label {
            color: rgba(255, 255, 255, 0.6);
        }

        .stat-value {
            color: #22c55e;
        }

        .room-actions {
            display: flex;
            gap: 0.5rem;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: rgba(255, 255, 255, 0.5);
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <div class="logo">AEIMS Operator Portal</div>
            <div class="nav-links">
                <a href="/agents/dashboard.php" class="btn btn-secondary">Dashboard</a>
                <a href="/agents/logout.php" class="btn btn-secondary">Logout</a>
            </div>
        </div>
    </header>

    <main class="container">
        <h1 class="page-title">Chat Room Management</h1>

        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <h2 class="card-title">Create New Room</h2>
            <form method="POST">
                <input type="hidden" name="action" value="create">

                <div class="form-group">
                    <label for="room_name">Room Name *</label>
                    <input type="text" id="room_name" name="room_name" required maxlength="100">
                    <div class="form-hint">Give your room an attractive name</div>
                </div>

                <div class="form-group">
                    <label for="description">Description *</label>
                    <textarea id="description" name="description" required maxlength="500"></textarea>
                    <div class="form-hint">Describe what customers can expect in this room</div>
                </div>

                <div class="form-group">
                    <label for="pin_code">PIN Code (Optional)</label>
                    <input type="text" id="pin_code" name="pin_code" maxlength="10">
                    <div class="form-hint">Set a PIN for private rooms (leave empty for public rooms)</div>
                </div>

                <div class="form-group">
                    <label for="entry_fee">Entry Fee ($)</label>
                    <input type="number" id="entry_fee" name="entry_fee" min="0" step="0.01" value="0">
                    <div class="form-hint">One-time fee charged when joining (0 for free entry)</div>
                </div>

                <div class="form-group">
                    <label for="per_minute_rate">Per-Minute Rate ($)</label>
                    <input type="number" id="per_minute_rate" name="per_minute_rate" min="0" step="0.01" value="0">
                    <div class="form-hint">Amount charged per minute (0 for free)</div>
                </div>

                <button type="submit" class="btn btn-primary">Create Room</button>
            </form>
        </div>

        <div class="card">
            <h2 class="card-title">My Rooms (<?= count($myRooms) ?>)</h2>

            <?php if (empty($myRooms)): ?>
                <div class="empty-state">
                    <p>You haven't created any rooms yet.</p>
                    <p>Create your first room above to start hosting multi-user chats!</p>
                </div>
            <?php else: ?>
                <div class="rooms-grid">
                    <?php foreach ($myRooms as $room): ?>
                        <div class="room-card">
                            <div class="room-name"><?= htmlspecialchars($room['name']) ?></div>
                            <div class="room-description"><?= htmlspecialchars($room['description']) ?></div>

                            <div class="room-stats">
                                <div>
                                    <div class="stat-label">Current Users:</div>
                                    <div class="stat-value"><?= $room['current_users'] ?></div>
                                </div>
                                <div>
                                    <div class="stat-label">Total Members:</div>
                                    <div class="stat-value"><?= $room['stats']['total_members'] ?></div>
                                </div>
                                <div>
                                    <div class="stat-label">Messages:</div>
                                    <div class="stat-value"><?= $room['stats']['total_messages'] ?></div>
                                </div>
                                <div>
                                    <div class="stat-label">Revenue:</div>
                                    <div class="stat-value">$<?= number_format($room['total_revenue'], 2) ?></div>
                                </div>
                            </div>

                            <div style="margin-bottom: 1rem; font-size: 0.9rem;">
                                <?php if ($room['entry_fee'] > 0): ?>
                                    <div>Entry: $<?= number_format($room['entry_fee'], 2) ?></div>
                                <?php endif; ?>
                                <?php if ($room['per_minute_rate'] > 0): ?>
                                    <div>Rate: $<?= number_format($room['per_minute_rate'], 2) ?>/min</div>
                                <?php endif; ?>
                                <?php if (!empty($room['pin_code'])): ?>
                                    <div>PIN: <?= htmlspecialchars($room['pin_code']) ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="room-actions">
                                <form method="POST" style="flex: 1;" onsubmit="return confirm('Are you sure you want to deactivate this room?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="room_id" value="<?= htmlspecialchars($room['room_id']) ?>">
                                    <button type="submit" class="btn btn-danger" style="width: 100%;">Deactivate</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
