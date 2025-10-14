<?php
/**
 * User Settings
 * Manage notifications and preferences
 */

session_start();

// Check authentication
require_once __DIR__ . '/../../includes/CustomerAuth.php';
$auth = new CustomerAuth();
$auth->requireLogin();

// Load services
require_once __DIR__ . '/../../services/SiteManager.php';

$siteManager = new \AEIMS\Services\SiteManager();

$hostname = preg_replace('/^www\./', '', $_SERVER['HTTP_HOST'] ?? 'flirts.nyc');
$hostname = preg_replace('/:\d+$/', '', $hostname);
$site = $siteManager->getSite($hostname);
$customer = $auth->getCurrentCustomer();

$message = '';
$messageType = '';

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // In a real system, save these to the customer profile
    $message = 'Settings saved successfully! (Note: Settings are currently stored in browser localStorage)';
    $messageType = 'success';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - <?= htmlspecialchars($site['name']) ?></title>
    <script src="/assets/js/notifications.js"></script>
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
            max-width: 800px;
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
            color: <?= $site['theme']['primary_color'] ?>;
        }

        .setting-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .setting-row:last-child {
            border-bottom: none;
        }

        .setting-info h3 {
            font-size: 1rem;
            margin-bottom: 0.3rem;
        }

        .setting-info p {
            font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.6);
        }

        .toggle-switch {
            position: relative;
            width: 60px;
            height: 30px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(255, 255, 255, 0.2);
            transition: 0.3s;
            border-radius: 30px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 22px;
            width: 22px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: 0.3s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background-color: <?= $site['theme']['primary_color'] ?>;
        }

        input:checked + .slider:before {
            transform: translateX(30px);
        }

        .test-notification {
            margin-top: 1rem;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="nav-container">
            <a href="/" class="logo"><?= htmlspecialchars($site['name']) ?></a>
            <div class="nav-links">
                <a href="/rooms.php" class="btn btn-secondary">Rooms</a>
                <a href="/messages.php" class="btn btn-secondary">Messages</a>
                <a href="/" class="btn btn-secondary">Home</a>
            </div>
        </div>
    </header>

    <main class="main-content">
        <div class="page-header">
            <h1>Settings</h1>
            <p style="color: rgba(255, 255, 255, 0.7);">Manage your notification preferences</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <h2 class="card-title">Notifications</h2>

            <div class="setting-row">
                <div class="setting-info">
                    <h3>Enable Notifications</h3>
                    <p>Show real-time toast notifications</p>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" id="toggle-enabled" checked>
                    <span class="slider"></span>
                </label>
            </div>

            <div class="setting-row">
                <div class="setting-info">
                    <h3>Notification Sound</h3>
                    <p>Play sound when notifications arrive</p>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" id="toggle-sound" checked>
                    <span class="slider"></span>
                </label>
            </div>
        </div>

        <div class="card">
            <h2 class="card-title">Notification Types</h2>

            <div class="setting-row">
                <div class="setting-info">
                    <h3>Chat Messages</h3>
                    <p>Notifications for new chat messages</p>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" id="toggle-chat" checked>
                    <span class="slider"></span>
                </label>
            </div>

            <div class="setting-row">
                <div class="setting-info">
                    <h3>Room Invites</h3>
                    <p>Notifications for chat room invitations</p>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" id="toggle-room_invite" checked>
                    <span class="slider"></span>
                </label>
            </div>

            <div class="setting-row">
                <div class="setting-info">
                    <h3>Mail Messages</h3>
                    <p>Notifications for new mail messages</p>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" id="toggle-mail" checked>
                    <span class="slider"></span>
                </label>
            </div>

            <div class="setting-row">
                <div class="setting-info">
                    <h3>Message Sent Confirmations</h3>
                    <p>Notifications when your messages are sent</p>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" id="toggle-message_sent" checked>
                    <span class="slider"></span>
                </label>
            </div>

            <div class="setting-row">
                <div class="setting-info">
                    <h3>System Notifications</h3>
                    <p>Important system messages and updates</p>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" id="toggle-system" checked>
                    <span class="slider"></span>
                </label>
            </div>
        </div>

        <div class="card">
            <h2 class="card-title">Test Notifications</h2>
            <p style="margin-bottom: 1rem; color: rgba(255, 255, 255, 0.7);">
                Click a button to see a sample notification:
            </p>
            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                <button class="btn btn-primary" onclick="testNotification('chat')">Test Chat</button>
                <button class="btn btn-primary" onclick="testNotification('room_invite')">Test Room Invite</button>
                <button class="btn btn-primary" onclick="testNotification('mail')">Test Mail</button>
                <button class="btn btn-primary" onclick="testNotification('message_sent')">Test Message</button>
                <button class="btn btn-primary" onclick="testNotification('system')">Test System</button>
            </div>
        </div>
    </main>

    <script>
        // Load current settings when page loads
        window.addEventListener('DOMContentLoaded', () => {
            // Wait for notification system to initialize
            setTimeout(() => {
                if (window.notificationSystem) {
                    const settings = window.notificationSystem.getSettings();

                    document.getElementById('toggle-enabled').checked = settings.enabled;
                    document.getElementById('toggle-sound').checked = settings.sound;
                    document.getElementById('toggle-chat').checked = settings.types.chat;
                    document.getElementById('toggle-room_invite').checked = settings.types.room_invite;
                    document.getElementById('toggle-mail').checked = settings.types.mail;
                    document.getElementById('toggle-message_sent').checked = settings.types.message_sent;
                    document.getElementById('toggle-system').checked = settings.types.system;
                }
            }, 100);
        });

        // Handle toggle changes
        document.getElementById('toggle-enabled').addEventListener('change', function() {
            window.notificationSystem.updateSettings({ enabled: this.checked });
        });

        document.getElementById('toggle-sound').addEventListener('change', function() {
            window.notificationSystem.updateSettings({ sound: this.checked });
        });

        document.getElementById('toggle-chat').addEventListener('change', function() {
            const settings = window.notificationSystem.getSettings();
            settings.types.chat = this.checked;
            window.notificationSystem.updateSettings({ types: settings.types });
        });

        document.getElementById('toggle-room_invite').addEventListener('change', function() {
            const settings = window.notificationSystem.getSettings();
            settings.types.room_invite = this.checked;
            window.notificationSystem.updateSettings({ types: settings.types });
        });

        document.getElementById('toggle-mail').addEventListener('change', function() {
            const settings = window.notificationSystem.getSettings();
            settings.types.mail = this.checked;
            window.notificationSystem.updateSettings({ types: settings.types });
        });

        document.getElementById('toggle-message_sent').addEventListener('change', function() {
            const settings = window.notificationSystem.getSettings();
            settings.types.message_sent = this.checked;
            window.notificationSystem.updateSettings({ types: settings.types });
        });

        document.getElementById('toggle-system').addEventListener('change', function() {
            const settings = window.notificationSystem.getSettings();
            settings.types.system = this.checked;
            window.notificationSystem.updateSettings({ types: settings.types });
        });

        // Test notification function
        function testNotification(type) {
            const messages = {
                chat: { title: 'New Chat Message', message: 'Sarah: Hey! How are you doing?' },
                room_invite: { title: 'Room Invitation', message: 'You\'ve been invited to "VIP Lounge"' },
                mail: { title: 'New Mail', message: 'You have a new message from Jessica' },
                message_sent: { title: 'Message Sent', message: 'Your message was delivered successfully' },
                system: { title: 'System Update', message: 'Platform maintenance scheduled for tonight' }
            };

            const testData = messages[type];
            window.notificationSystem.handleNotification({
                notification_id: 'test_' + Date.now(),
                type: type,
                title: testData.title,
                message: testData.message,
                link: null,
                timestamp: new Date().toISOString()
            });
        }
    </script>
</body>
</html>
