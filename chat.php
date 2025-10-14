<?php
/**
 * Customer Chat Interface
 * Real-time chat between customers and operators
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication
if (!isset($_SESSION['customer_id'])) {
    header('Location: /');
    exit;
}

// Determine which site's chat interface to load
$host = $_SERVER['HTTP_HOST'] ?? '';
$host = preg_replace('/^www\./', '', $host);

// Route to site-specific chat if it exists
$siteChat = __DIR__ . '/sites/' . $host . '/chat.php';
if (file_exists($siteChat)) {
    require_once $siteChat;
    exit;
}

// Default chat interface
require_once 'services/SiteManager.php';
require_once 'services/CustomerManager.php';
require_once 'services/OperatorManager.php';

try {
    $siteManager = new \AEIMS\Services\SiteManager();
    $customerManager = new \AEIMS\Services\CustomerManager();
    $operatorManager = new \AEIMS\Services\OperatorManager();

    $site = $siteManager->getSite($host);
    $customer = $customerManager->getCustomer($_SESSION['customer_id']);
    $operators = $operatorManager->getActiveOperators();

    if (!$site || !$site['active'] || !$customer) {
        session_destroy();
        header('Location: /');
        exit;
    }
} catch (Exception $e) {
    error_log("Chat error: " . $e->getMessage());
    session_destroy();
    header('Location: /');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat - <?= htmlspecialchars($site['name']) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #1a1a1a 0%, #2d1b3d 100%);
            color: #fff;
            min-height: 100vh;
        }
        .header {
            background: rgba(0, 0, 0, 0.9);
            padding: 1rem;
            border-bottom: 1px solid rgba(239, 68, 68, 0.3);
        }
        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }
        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
        }
        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        .main-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        .chat-container {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            padding: 2rem;
            margin-top: 2rem;
        }
        .operator-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1rem;
            margin-top: 2rem;
        }
        .operator-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            padding: 1.5rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        .operator-card:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-2px);
        }
        .operator-name {
            font-size: 1.2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        .operator-status {
            color: #22c55e;
            font-size: 0.9rem;
        }
        .btn-primary {
            background: #ef4444;
            color: #fff;
            margin-top: 1rem;
        }
        .btn-primary:hover {
            background: #dc2626;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="nav-container">
            <a href="/dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
            <div>
                <a href="/messages.php" class="btn btn-secondary">Mail</a>
                <a href="/auth.php?action=logout" class="btn btn-secondary">Logout</a>
            </div>
        </div>
    </header>

    <main class="main-content">
        <div class="chat-container">
            <h1>Live Chat</h1>
            <p style="margin-top: 1rem; color: rgba(255,255,255,0.7);">Select an operator to start chatting</p>

            <div class="operator-list">
                <?php foreach ($operators as $operator): ?>
                    <div class="operator-card" onclick="startChat('<?= htmlspecialchars($operator['operator_id']) ?>')">
                        <div class="operator-name"><?= htmlspecialchars($operator['username']) ?></div>
                        <div class="operator-status">● Online</div>
                        <button class="btn btn-primary">Start Chat</button>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </main>

    <script>
        function startChat(operatorId) {
            // Redirect to messages with operator selected
            window.location.href = `/messages.php?operator=${operatorId}&start_chat=1`;
        }
    </script>
</body>
</html>
