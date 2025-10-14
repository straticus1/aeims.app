<?php
/**
 * Customer Operator Requests Interface
 * View and respond to operator chat/call requests
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
require_once __DIR__ . '/../../services/OperatorRequestManager.php';
require_once __DIR__ . '/../../services/OperatorManager.php';

try {
    $siteManager = new \AEIMS\Services\SiteManager();
    $requestManager = new \AEIMS\Services\OperatorRequestManager();
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
        $requestId = $_POST['request_id'] ?? '';
        $action = $_POST['action'] ?? '';

        try {
            if ($action === 'accept') {
                $request = $requestManager->acceptRequest($requestId, $customerId);
                $_SESSION['request_success'] = 'Request accepted! You can now start chatting.';

                // Redirect to chat if it's a chat request
                if ($request['type'] === 'chat') {
                    header('Location: /chat.php?operator_id=' . urlencode($request['operator_id']));
                    exit;
                }
            } elseif ($action === 'decline') {
                $requestManager->declineRequest($requestId, $customerId);
                $_SESSION['request_success'] = 'Request declined.';
            }

            header('Location: /operator-requests.php');
            exit;
        } catch (Exception $e) {
            $_SESSION['request_error'] = $e->getMessage();
        }
    }

    // Expire old requests
    $requestManager->expireOldRequests();

    // Get customer's requests
    $pendingRequests = $requestManager->getCustomerRequests($customerId, 'pending');
    $pastRequests = $requestManager->getCustomerRequests($customerId);
    $pastRequests = array_filter($pastRequests, function($req) {
        return $req['status'] !== 'pending';
    });

} catch (Exception $e) {
    error_log("Operator requests error: " . $e->getMessage());
    http_response_code(500);
    die('System error');
}

$requestSuccess = $_SESSION['request_success'] ?? null;
$requestError = $_SESSION['request_error'] ?? null;
unset($_SESSION['request_success'], $_SESSION['request_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Operator Requests - <?= htmlspecialchars($site['name']) ?></title>
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

        .section {
            margin-bottom: 3rem;
        }

        .section-title {
            font-size: 1.5rem;
            color: <?= $site['theme']['primary_color'] ?>;
            margin-bottom: 1rem;
        }

        .request-grid {
            display: grid;
            gap: 1rem;
        }

        .request-card {
            background: rgba(0, 0, 0, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 1.5rem;
            display: flex;
            gap: 1.5rem;
            align-items: center;
            transition: all 0.3s ease;
        }

        .request-card:hover {
            border-color: rgba(239, 68, 68, 0.5);
            transform: translateY(-2px);
        }

        .request-icon {
            font-size: 3rem;
            width: 80px;
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(45deg, <?= $site['theme']['primary_color'] ?>, <?= $site['theme']['secondary_color'] ?>);
            border-radius: 50%;
        }

        .request-content {
            flex: 1;
        }

        .request-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .operator-name {
            font-size: 1.3rem;
            font-weight: 600;
            color: <?= $site['theme']['primary_color'] ?>;
        }

        .request-type {
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.85rem;
            font-weight: 600;
            background: rgba(239, 68, 68, 0.3);
            color: <?= $site['theme']['primary_color'] ?>;
        }

        .request-message {
            margin: 0.75rem 0;
            color: #e5e7eb;
            line-height: 1.5;
        }

        .request-meta {
            font-size: 0.85rem;
            color: #9ca3af;
            margin-bottom: 1rem;
        }

        .request-actions {
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

        .request-status {
            padding: 0.5rem 1rem;
            border-radius: 15px;
            font-size: 0.9rem;
            font-weight: 600;
            display: inline-block;
        }

        .status-accepted {
            background: rgba(34, 197, 94, 0.3);
            color: #22c55e;
        }

        .status-declined {
            background: rgba(239, 68, 68, 0.3);
            color: #ef4444;
        }

        .status-expired {
            background: rgba(107, 114, 128, 0.3);
            color: #9ca3af;
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
            .request-card {
                flex-direction: column;
                text-align: center;
            }

            .request-header {
                flex-direction: column;
                gap: 0.5rem;
            }

            .request-actions {
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
            <li><a href="/chat.php">💬 Chat</a></li>
            <li><a href="/rooms.php">🏠 Rooms</a></li>
            <li><a href="/operator-requests.php">📤 Requests</a></li>
            <li><a href="/logout.php">🚪 Logout</a></li>
        </ul>
    </header>

    <div class="container">
        <h1 class="page-title">📤 Operator Requests</h1>

        <?php if ($requestSuccess): ?>
            <div class="alert alert-success"><?= htmlspecialchars($requestSuccess) ?></div>
        <?php endif; ?>

        <?php if ($requestError): ?>
            <div class="alert alert-error"><?= htmlspecialchars($requestError) ?></div>
        <?php endif; ?>

        <div class="section">
            <h2 class="section-title">📥 Pending Requests (<?= count($pendingRequests) ?>)</h2>

            <?php if (empty($pendingRequests)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📭</div>
                    <h3>No pending requests</h3>
                    <p>When operators send you chat or call requests, they'll appear here.</p>
                </div>
            <?php else: ?>
                <div class="request-grid">
                    <?php foreach ($pendingRequests as $request): ?>
                        <?php
                        $operator = $operatorManager->getOperator($request['operator_id']);
                        $operatorName = $operator['profile']['display_names'][$hostname] ?? $operator['name'] ?? 'Unknown';
                        $requestIcon = $request['type'] === 'call' ? '📞' : '💬';
                        $requestLabel = $request['type'] === 'call' ? 'Call Request' : 'Chat Request';
                        ?>
                        <div class="request-card">
                            <div class="request-icon"><?= $requestIcon ?></div>

                            <div class="request-content">
                                <div class="request-header">
                                    <div class="operator-name"><?= htmlspecialchars($operatorName) ?></div>
                                    <div class="request-type"><?= $requestLabel ?></div>
                                </div>

                                <div class="request-message">
                                    "<?= htmlspecialchars($request['message']) ?>"
                                </div>

                                <div class="request-meta">
                                    Sent <?= date('M j, Y @ g:i A', strtotime($request['created_at'])) ?>
                                    <?php if ($request['type'] === 'call' && $request['duration']): ?>
                                        • Duration: <?= $request['duration'] ?> minutes
                                    <?php endif; ?>
                                    <?php if ($request['price']): ?>
                                        • Price: $<?= number_format($request['price'], 2) ?>
                                    <?php endif; ?>
                                </div>

                                <div class="request-actions">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="request_id" value="<?= htmlspecialchars($request['request_id']) ?>">
                                        <input type="hidden" name="action" value="accept">
                                        <button type="submit" class="btn btn-accept">
                                            ✅ Accept Request
                                        </button>
                                    </form>

                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="request_id" value="<?= htmlspecialchars($request['request_id']) ?>">
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
            <h2 class="section-title">📋 Past Requests</h2>

            <?php if (empty($pastRequests)): ?>
                <div class="empty-state">
                    <p>No past requests.</p>
                </div>
            <?php else: ?>
                <div class="request-grid">
                    <?php foreach (array_slice($pastRequests, 0, 10) as $request): ?>
                        <?php
                        $operator = $operatorManager->getOperator($request['operator_id']);
                        $operatorName = $operator['profile']['display_names'][$hostname] ?? $operator['name'] ?? 'Unknown';
                        $requestIcon = $request['type'] === 'call' ? '📞' : '💬';
                        $requestLabel = $request['type'] === 'call' ? 'Call Request' : 'Chat Request';
                        ?>
                        <div class="request-card">
                            <div class="request-icon"><?= $requestIcon ?></div>

                            <div class="request-content">
                                <div class="request-header">
                                    <div class="operator-name"><?= htmlspecialchars($operatorName) ?></div>
                                    <div class="request-type"><?= $requestLabel ?></div>
                                </div>

                                <div class="request-message">
                                    "<?= htmlspecialchars($request['message']) ?>"
                                </div>

                                <div class="request-meta">
                                    Sent <?= date('M j, Y @ g:i A', strtotime($request['created_at'])) ?>
                                </div>

                                <div class="request-status status-<?= htmlspecialchars($request['status']) ?>">
                                    <?= ucfirst($request['status']) ?>
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
