<?php
/**
 * Customer Room Invites Interface
 * View and respond to room invites with free time
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
require_once __DIR__ . '/../../services/RoomInviteManager.php';
require_once __DIR__ . '/../../services/ChatRoomManager.php';
require_once __DIR__ . '/../../services/OperatorManager.php';

try {
    $siteManager = new \AEIMS\Services\SiteManager();
    $inviteManager = new \AEIMS\Services\RoomInviteManager();
    $roomManager = new \AEIMS\Services\ChatRoomManager();
    $operatorManager = new \AEIMS\Services\OperatorManager();

    // Get current site
    $hostname = $_SERVER['HTTP_HOST'] ?? 'flirts.nyc';
    $hostname = preg_replace('/^www\./', '', $hostname);
    $hostname = preg_replace('/:\d+$/', '', $hostname);

    $site = $siteManager->getSite($hostname);
    if (!$site || !$site['active']) {
        http_response_code(503);
        die('Site temporarily unavailable');
    }

    $customerId = $_SESSION['customer_id'];

    // Handle actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $inviteId = $_POST['invite_id'] ?? '';
        $action = $_POST['action'] ?? '';

        try {
            if ($action === 'accept') {
                $invite = $inviteManager->acceptInvite($inviteId, $customerId);
                $_SESSION['invite_success'] = 'Invite accepted! Join the room to use your free time.';

                // Redirect to room
                header('Location: /room-chat.php?room_id=' . urlencode($invite['room_id']));
                exit;
            } elseif ($action === 'decline') {
                $inviteManager->declineInvite($inviteId, $customerId);
                $_SESSION['invite_success'] = 'Invite declined.';
            }

            header('Location: /room-invites.php');
            exit;
        } catch (Exception $e) {
            $_SESSION['invite_error'] = $e->getMessage();
        }
    }

    // Expire old invites
    $inviteManager->expireOldInvites();

    // Get customer's invites
    $pendingInvites = $inviteManager->getCustomerInvites($customerId, 'pending');
    $acceptedInvites = $inviteManager->getCustomerInvites($customerId, 'accepted');
    $pastInvites = $inviteManager->getCustomerInvites($customerId);
    $pastInvites = array_filter($pastInvites, function($inv) {
        return !in_array($inv['status'], ['pending', 'accepted']);
    });

} catch (Exception $e) {
    error_log("Room invites error: " . $e->getMessage());
    http_response_code(500);
    die('System error');
}

$inviteSuccess = $_SESSION['invite_success'] ?? null;
$inviteError = $_SESSION['invite_error'] ?? null;
unset($_SESSION['invite_success'], $_SESSION['invite_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Room Invites - <?= htmlspecialchars($site['name']) ?></title>
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
            color: #ffffff;
            min-height: 100vh;
        }

        .header {
            background: rgba(0, 0, 0, 0.9);
            padding: 1rem 2rem;
            border-bottom: 1px solid rgba(239, 68, 68, 0.3);
            display: flex;
            justify-content: space-between;
            align-items: center;
            backdrop-filter: blur(10px);
        }

        .logo {
            font-size: 1.5rem;
            font-weight: bold;
            background: linear-gradient(45deg, <?= $site['theme']['primary_color'] ?>, <?= $site['theme']['secondary_color'] ?>);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .nav-menu {
            display: flex;
            gap: 1rem;
            list-style: none;
        }

        .nav-menu a {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            transition: all 0.3s ease;
        }

        .nav-menu a:hover {
            background: rgba(239, 68, 68, 0.2);
        }

        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .page-title {
            font-size: 2.5rem;
            margin-bottom: 2rem;
            background: linear-gradient(45deg, <?= $site['theme']['primary_color'] ?>, <?= $site['theme']['secondary_color'] ?>);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
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

        .section {
            margin-bottom: 3rem;
        }

        .section-title {
            font-size: 1.5rem;
            color: <?= $site['theme']['primary_color'] ?>;
            margin-bottom: 1rem;
        }

        .invite-grid {
            display: grid;
            gap: 1rem;
        }

        .invite-card {
            background: rgba(0, 0, 0, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 1.5rem;
            display: flex;
            gap: 1.5rem;
            align-items: center;
            transition: all 0.3s ease;
        }

        .invite-card:hover {
            border-color: rgba(239, 68, 68, 0.5);
            transform: translateY(-2px);
        }

        .invite-icon {
            font-size: 3rem;
            width: 80px;
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(45deg, <?= $site['theme']['primary_color'] ?>, <?= $site['theme']['secondary_color'] ?>);
            border-radius: 50%;
        }

        .invite-content {
            flex: 1;
        }

        .invite-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .room-name {
            font-size: 1.3rem;
            font-weight: 600;
            color: <?= $site['theme']['primary_color'] ?>;
        }

        .free-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.85rem;
            font-weight: 600;
            background: linear-gradient(45deg, #22c55e, #16a34a);
            color: white;
        }

        .operator-name {
            font-size: 0.95rem;
            color: #9ca3af;
            margin-bottom: 0.5rem;
        }

        .invite-message {
            margin: 0.75rem 0;
            color: #e5e7eb;
            line-height: 1.5;
        }

        .invite-meta {
            font-size: 0.85rem;
            color: #9ca3af;
            margin-bottom: 1rem;
        }

        .invite-actions {
            display: flex;
            gap: 0.75rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn-accept {
            background: linear-gradient(45deg, #22c55e, #16a34a);
            color: white;
        }

        .btn-accept:hover {
            transform: scale(1.05);
            box-shadow: 0 5px 20px rgba(34, 197, 94, 0.4);
        }

        .btn-join {
            background: linear-gradient(45deg, <?= $site['theme']['primary_color'] ?>, <?= $site['theme']['secondary_color'] ?>);
            color: white;
        }

        .btn-join:hover {
            transform: scale(1.05);
        }

        .btn-decline {
            background: transparent;
            color: #9ca3af;
            border: 1px solid #9ca3af;
        }

        .btn-decline:hover {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
            border-color: #ef4444;
        }

        .invite-status {
            padding: 0.5rem 1rem;
            border-radius: 15px;
            font-size: 0.9rem;
            font-weight: 600;
            display: inline-block;
        }

        .status-declined {
            background: rgba(239, 68, 68, 0.3);
            color: #ef4444;
        }

        .status-expired {
            background: rgba(107, 114, 128, 0.3);
            color: #9ca3af;
        }

        .status-used {
            background: rgba(107, 114, 128, 0.3);
            color: #9ca3af;
        }

        .progress-bar {
            width: 100%;
            height: 6px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            overflow: hidden;
            margin: 0.5rem 0;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(45deg, #22c55e, #16a34a);
            transition: width 0.3s ease;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #9ca3af;
        }

        .empty-state-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }

        @media (max-width: 768px) {
            .invite-card {
                flex-direction: column;
                text-align: center;
            }

            .invite-header {
                flex-direction: column;
                gap: 0.5rem;
            }

            .invite-actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="logo"><?= htmlspecialchars($site['name']) ?></div>

        <ul class="nav-menu">
            <li><a href="/search-operators.php">🔍 Search</a></li>
            <li><a href="/messages.php">✉️ Messages</a></li>
            <li><a href="/rooms.php">🏠 Rooms</a></li>
            <li><a href="/room-invites.php">🎁 Invites</a></li>
            <li><a href="/logout.php">🚪 Logout</a></li>
        </ul>
    </header>

    <div class="container">
        <h1 class="page-title">🎁 Room Invites</h1>

        <?php if ($inviteSuccess): ?>
            <div class="alert alert-success"><?= htmlspecialchars($inviteSuccess) ?></div>
        <?php endif; ?>

        <?php if ($inviteError): ?>
            <div class="alert alert-error"><?= htmlspecialchars($inviteError) ?></div>
        <?php endif; ?>

        <div class="section">
            <h2 class="section-title">📥 Pending Invites (<?= count($pendingInvites) ?>)</h2>

            <?php if (empty($pendingInvites)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📭</div>
                    <h3>No pending invites</h3>
                    <p>When operators invite you to private rooms with free time, they'll appear here.</p>
                </div>
            <?php else: ?>
                <div class="invite-grid">
                    <?php foreach ($pendingInvites as $invite): ?>
                        <?php
                        $operator = $operatorManager->getOperator($invite['operator_id']);
                        $operatorName = $operator['profile']['display_names'][$hostname] ?? $operator['name'] ?? 'Unknown';
                        $room = $roomManager->getRoom($invite['room_id']);
                        $roomName = $room['room_name'] ?? 'Unknown Room';
                        ?>
                        <div class="invite-card">
                            <div class="invite-icon">🎁</div>

                            <div class="invite-content">
                                <div class="invite-header">
                                    <div class="room-name"><?= htmlspecialchars($roomName) ?></div>
                                    <div class="free-badge"><?= $invite['free_minutes'] ?> min FREE!</div>
                                </div>

                                <div class="operator-name">From: <?= htmlspecialchars($operatorName) ?></div>

                                <div class="invite-message">
                                    "<?= htmlspecialchars($invite['message']) ?>"
                                </div>

                                <div class="invite-meta">
                                    Sent <?= date('M j, Y @ g:i A', strtotime($invite['created_at'])) ?>
                                </div>

                                <div class="invite-actions">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="invite_id" value="<?= htmlspecialchars($invite['invite_id']) ?>">
                                        <input type="hidden" name="action" value="accept">
                                        <button type="submit" class="btn btn-accept">
                                            ✅ Accept & Join Room
                                        </button>
                                    </form>

                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="invite_id" value="<?= htmlspecialchars($invite['invite_id']) ?>">
                                        <input type="hidden" name="action" value="decline">
                                        <button type="submit" class="btn btn-decline">
                                            ❌ Decline
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="section">
            <h2 class="section-title">✅ Accepted Invites (<?= count($acceptedInvites) ?>)</h2>

            <?php if (empty($acceptedInvites)): ?>
                <div class="empty-state">
                    <p>No active invites.</p>
                </div>
            <?php else: ?>
                <div class="invite-grid">
                    <?php foreach ($acceptedInvites as $invite): ?>
                        <?php
                        $operator = $operatorManager->getOperator($invite['operator_id']);
                        $operatorName = $operator['profile']['display_names'][$hostname] ?? $operator['name'] ?? 'Unknown';
                        $room = $roomManager->getRoom($invite['room_id']);
                        $roomName = $room['room_name'] ?? 'Unknown Room';
                        $remainingMinutes = $invite['free_minutes'] - $invite['minutes_used'];
                        $percentUsed = ($invite['minutes_used'] / $invite['free_minutes']) * 100;
                        ?>
                        <div class="invite-card">
                            <div class="invite-icon">🏠</div>

                            <div class="invite-content">
                                <div class="invite-header">
                                    <div class="room-name"><?= htmlspecialchars($roomName) ?></div>
                                    <div class="free-badge"><?= $remainingMinutes ?> min left</div>
                                </div>

                                <div class="operator-name">From: <?= htmlspecialchars($operatorName) ?></div>

                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?= $percentUsed ?>%"></div>
                                </div>

                                <div class="invite-meta">
                                    Used <?= $invite['minutes_used'] ?> of <?= $invite['free_minutes'] ?> minutes
                                </div>

                                <a href="/room-chat.php?room_id=<?= urlencode($invite['room_id']) ?>" class="btn btn-join">
                                    🏠 Join Room
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="section">
            <h2 class="section-title">📋 Past Invites</h2>

            <?php if (empty($pastInvites)): ?>
                <div class="empty-state">
                    <p>No past invites.</p>
                </div>
            <?php else: ?>
                <div class="invite-grid">
                    <?php foreach (array_slice($pastInvites, 0, 5) as $invite): ?>
                        <?php
                        $operator = $operatorManager->getOperator($invite['operator_id']);
                        $operatorName = $operator['profile']['display_names'][$hostname] ?? $operator['name'] ?? 'Unknown';
                        $room = $roomManager->getRoom($invite['room_id']);
                        $roomName = $room['room_name'] ?? 'Unknown Room';
                        ?>
                        <div class="invite-card">
                            <div class="invite-icon">📋</div>

                            <div class="invite-content">
                                <div class="invite-header">
                                    <div class="room-name"><?= htmlspecialchars($roomName) ?></div>
                                </div>

                                <div class="operator-name">From: <?= htmlspecialchars($operatorName) ?></div>

                                <div class="invite-meta">
                                    Sent <?= date('M j, Y @ g:i A', strtotime($invite['created_at'])) ?>
                                </div>

                                <div class="invite-status status-<?= htmlspecialchars($invite['status']) ?>">
                                    <?= ucfirst($invite['status']) ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
