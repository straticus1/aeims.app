<?php
/**
 * Chat Rooms - Browse and Join
 * Flirts NYC Multi-User Chat Rooms
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

// Get all active rooms
$rooms = $roomManager->getAllRooms();

// Load operator data for each room
foreach ($rooms as &$room) {
    $operator = $operatorManager->getOperatorById($room['operator_id']);
    $room['operator_name'] = $operator['name'] ?? 'Unknown';
}
unset($room);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat Rooms - <?= htmlspecialchars($site['name']) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: <?= $site['theme']['font_family'] ?>;
            background: linear-gradient(135deg, #1a1a1a 0%, #2d1b3d 100%);
            color: #ffffff;
            min-height: 100vh;
        }

        .header {
            background: rgba(0, 0, 0, 0.9);
            padding: 1rem 0;
            border-bottom: 1px solid rgba(239, 68, 68, 0.3);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: bold;
            color: <?= $site['theme']['primary_color'] ?>;
            text-decoration: none;
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
            background: linear-gradient(45deg, <?= $site['theme']['primary_color'] ?>, <?= $site['theme']['secondary_color'] ?>);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(239, 68, 68, 0.3);
        }

        .main-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .page-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .page-header h1 {
            font-size: 2.5rem;
            background: linear-gradient(45deg, <?= $site['theme']['primary_color'] ?>, <?= $site['theme']['accent_color'] ?>);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
        }

        .search-bar {
            margin-bottom: 2rem;
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .search-input {
            flex: 1;
            padding: 1rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            background: rgba(0, 0, 0, 0.3);
            color: #fff;
            font-size: 1rem;
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
            border: 1px solid rgba(239, 68, 68, 0.2);
            transition: all 0.3s;
        }

        .room-card:hover {
            border-color: <?= $site['theme']['primary_color'] ?>;
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(239, 68, 68, 0.2);
        }

        .room-header {
            margin-bottom: 1rem;
        }

        .room-name {
            font-size: 1.3rem;
            font-weight: bold;
            color: <?= $site['theme']['primary_color'] ?>;
            margin-bottom: 0.5rem;
        }

        .room-operator {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.7);
        }

        .room-description {
            margin-bottom: 1rem;
            color: rgba(255, 255, 255, 0.8);
            line-height: 1.5;
        }

        .room-stats {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }

        .stat {
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        .room-pricing {
            margin-bottom: 1rem;
            padding: 0.5rem;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 6px;
        }

        .price-item {
            font-size: 0.9rem;
            margin-bottom: 0.3rem;
        }

        .room-actions {
            display: flex;
            gap: 0.5rem;
        }

        .badge {
            display: inline-block;
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: bold;
        }

        .badge-private {
            background: rgba(239, 68, 68, 0.2);
            color: <?= $site['theme']['primary_color'] ?>;
        }

        .badge-free {
            background: rgba(34, 197, 94, 0.2);
            color: #22c55e;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: rgba(255, 255, 255, 0.6);
        }

        .empty-state h2 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: linear-gradient(135deg, #2a2a2a 0%, #1a1a1a 100%);
            padding: 2rem;
            border-radius: 15px;
            border: 1px solid rgba(239, 68, 68, 0.3);
            max-width: 400px;
            width: 90%;
        }

        .modal h2 {
            color: <?= $site['theme']['primary_color'] ?>;
            margin-bottom: 1.5rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }

        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            background: rgba(0, 0, 0, 0.3);
            color: #fff;
        }

        .modal-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1.5rem;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="nav-container">
            <a href="/" class="logo"><?= htmlspecialchars($site['name']) ?></a>
            <div class="nav-links">
                <a href="/search-operators.php" class="btn btn-secondary">Search</a>
                <a href="/messages.php" class="btn btn-secondary">Messages</a>
                <a href="/chat.php" class="btn btn-secondary">Chat</a>
                <a href="/" class="btn btn-secondary">Home</a>
            </div>
        </div>
    </header>

    <main class="main-content">
        <div class="page-header">
            <h1>Chat Rooms</h1>
            <p style="color: rgba(255, 255, 255, 0.7);">Join live multi-user chat rooms hosted by your favorite operators</p>
        </div>

        <div class="search-bar">
            <input type="text" id="searchInput" class="search-input" placeholder="Search rooms by name or operator...">
        </div>

        <?php if (empty($rooms)): ?>
            <div class="empty-state">
                <h2>No Rooms Available</h2>
                <p>Check back soon! Operators will be creating chat rooms shortly.</p>
            </div>
        <?php else: ?>
            <div class="rooms-grid" id="roomsGrid">
                <?php foreach ($rooms as $room): ?>
                    <div class="room-card" data-room-name="<?= strtolower(htmlspecialchars($room['name'])) ?>" data-operator-name="<?= strtolower(htmlspecialchars($room['operator_name'])) ?>">
                        <div class="room-header">
                            <div class="room-name"><?= htmlspecialchars($room['name']) ?></div>
                            <div class="room-operator">Hosted by <?= htmlspecialchars($room['operator_name']) ?></div>
                        </div>

                        <div class="room-description">
                            <?= htmlspecialchars($room['description']) ?>
                        </div>

                        <div class="room-stats">
                            <div class="stat">
                                <span>👥</span>
                                <span><?= $room['current_users'] ?> online</span>
                            </div>
                            <div class="stat">
                                <span>💬</span>
                                <span><?= $room['stats']['total_messages'] ?> messages</span>
                            </div>
                        </div>

                        <div class="room-pricing">
                            <?php if ($room['entry_fee'] > 0): ?>
                                <div class="price-item">Entry: $<?= number_format($room['entry_fee'], 2) ?></div>
                            <?php else: ?>
                                <div class="price-item">Entry: <span class="badge badge-free">FREE</span></div>
                            <?php endif; ?>

                            <?php if ($room['per_minute_rate'] > 0): ?>
                                <div class="price-item">$<?= number_format($room['per_minute_rate'], 2) ?>/min</div>
                            <?php endif; ?>

                            <?php if (!empty($room['pin_code'])): ?>
                                <span class="badge badge-private">PIN Required</span>
                            <?php endif; ?>
                        </div>

                        <div class="room-actions">
                            <button class="btn btn-primary" style="flex: 1;" onclick="joinRoom('<?= htmlspecialchars($room['room_id']) ?>', <?= !empty($room['pin_code']) ? 'true' : 'false' ?>)">
                                Join Room
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <!-- PIN Modal -->
    <div id="pinModal" class="modal">
        <div class="modal-content">
            <h2>Enter Room PIN</h2>
            <form id="pinForm" onsubmit="submitPin(event)">
                <input type="hidden" id="modalRoomId" value="">
                <div class="form-group">
                    <label for="pinInput">PIN Code:</label>
                    <input type="text" id="pinInput" name="pin" required autocomplete="off">
                </div>
                <div class="modal-actions">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">Enter Room</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const cards = document.querySelectorAll('.room-card');

            cards.forEach(card => {
                const roomName = card.dataset.roomName;
                const operatorName = card.dataset.operatorName;

                if (roomName.includes(searchTerm) || operatorName.includes(searchTerm)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });

        function joinRoom(roomId, requiresPin) {
            if (requiresPin) {
                document.getElementById('modalRoomId').value = roomId;
                document.getElementById('pinModal').style.display = 'flex';
                document.getElementById('pinInput').focus();
            } else {
                window.location.href = `/room-chat.php?room_id=${roomId}`;
            }
        }

        function closeModal() {
            document.getElementById('pinModal').style.display = 'none';
            document.getElementById('pinInput').value = '';
        }

        function submitPin(event) {
            event.preventDefault();
            const roomId = document.getElementById('modalRoomId').value;
            const pin = document.getElementById('pinInput').value;
            window.location.href = `/room-chat.php?room_id=${roomId}&pin=${encodeURIComponent(pin)}`;
        }

        // Close modal on outside click
        document.getElementById('pinModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>
</html>
