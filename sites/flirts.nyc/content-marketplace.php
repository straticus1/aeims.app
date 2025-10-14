<?php
/**
 * Customer Content Marketplace
 * Browse and purchase operator content
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
require_once __DIR__ . '/../../services/ContentMarketplaceManager.php';
require_once __DIR__ . '/../../services/OperatorManager.php';

try {
    $siteManager = new \AEIMS\Services\SiteManager();
    $contentManager = new \AEIMS\Services\ContentMarketplaceManager();
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

    // Handle purchase
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['purchase'])) {
        $itemId = $_POST['item_id'];
        $item = $contentManager->getItem($itemId);

        try {
            $contentManager->purchaseItem($itemId, $customerId, $item['price']);
            $_SESSION['marketplace_success'] = 'Content purchased successfully!';
            header('Location: /content-marketplace.php?item_id=' . urlencode($itemId));
            exit;
        } catch (Exception $e) {
            $_SESSION['marketplace_error'] = $e->getMessage();
        }
    }

    // Get filter parameters
    $operatorFilter = $_GET['operator_id'] ?? null;
    $typeFilter = $_GET['type'] ?? null;

    // Get all active items
    $allItems = $contentManager->getAllActiveItems($operatorFilter, $typeFilter);

    // Get customer's purchases
    $myPurchases = $contentManager->getCustomerPurchases($customerId);

    // Get specific item if viewing
    $viewingItem = null;
    if (isset($_GET['item_id'])) {
        $viewingItem = $contentManager->getItem($_GET['item_id']);
        if ($viewingItem) {
            $contentManager->incrementViews($_GET['item_id']);
        }
    }

} catch (Exception $e) {
    error_log("Content marketplace error: " . $e->getMessage());
    http_response_code(500);
    die('System error');
}

$marketplaceSuccess = $_SESSION['marketplace_success'] ?? null;
$marketplaceError = $_SESSION['marketplace_error'] ?? null;
unset($_SESSION['marketplace_success'], $_SESSION['marketplace_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Content Marketplace - <?= htmlspecialchars($site['name']) ?></title>
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
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .page-title {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            background: linear-gradient(45deg, <?= $site['theme']['primary_color'] ?>, <?= $site['theme']['secondary_color'] ?>);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .page-subtitle {
            color: #9ca3af;
            margin-bottom: 2rem;
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

        .filters {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 0.5rem 1rem;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .filter-btn:hover, .filter-btn.active {
            background: linear-gradient(45deg, <?= $site['theme']['primary_color'] ?>, <?= $site['theme']['secondary_color'] ?>);
            border-color: transparent;
        }

        .items-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        .item-card {
            background: rgba(0, 0, 0, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            overflow: hidden;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .item-card:hover {
            border-color: rgba(239, 68, 68, 0.5);
            transform: translateY(-5px);
        }

        .item-thumbnail {
            width: 100%;
            height: 200px;
            background: linear-gradient(135deg, <?= $site['theme']['primary_color'] ?>, <?= $site['theme']['secondary_color'] ?>);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4rem;
        }

        .item-content {
            padding: 1.5rem;
        }

        .item-type-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-bottom: 0.75rem;
            background: rgba(239, 68, 68, 0.3);
            color: <?= $site['theme']['primary_color'] ?>;
        }

        .item-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: white;
        }

        .item-operator {
            font-size: 0.85rem;
            color: #9ca3af;
            margin-bottom: 0.75rem;
        }

        .item-description {
            font-size: 0.85rem;
            color: #9ca3af;
            margin-bottom: 1rem;
            line-height: 1.4;
        }

        .item-stats {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 1rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .item-price {
            font-size: 1.3rem;
            font-weight: bold;
            color: #22c55e;
        }

        .item-price.free {
            color: <?= $site['theme']['primary_color'] ?>;
        }

        .owned-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            background: linear-gradient(45deg, #22c55e, #16a34a);
            color: white;
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

        .btn-purchase {
            background: linear-gradient(45deg, <?= $site['theme']['primary_color'] ?>, <?= $site['theme']['secondary_color'] ?>);
            color: white;
            width: 100%;
            margin-top: 1rem;
        }

        .btn-purchase:hover {
            transform: scale(1.05);
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #9ca3af;
        }

        .section-title {
            font-size: 1.5rem;
            color: <?= $site['theme']['primary_color'] ?>;
            margin-bottom: 1rem;
            margin-top: 2rem;
        }

        @media (max-width: 768px) {
            .items-grid {
                grid-template-columns: 1fr;
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
            <li><a href="/content-marketplace.php">🎨 Content</a></li>
            <li><a href="/logout.php">🚪 Logout</a></li>
        </ul>
    </header>

    <div class="container">
        <h1 class="page-title">🎨 Content Marketplace</h1>
        <p class="page-subtitle">Exclusive content from your favorite operators</p>

        <?php if ($marketplaceSuccess): ?>
            <div class="alert alert-success"><?= htmlspecialchars($marketplaceSuccess) ?></div>
        <?php endif; ?>

        <?php if ($marketplaceError): ?>
            <div class="alert alert-error"><?= htmlspecialchars($marketplaceError) ?></div>
        <?php endif; ?>

        <div class="filters">
            <a href="/content-marketplace.php" class="filter-btn <?= !$typeFilter ? 'active' : '' ?>">
                📦 All Content
            </a>
            <a href="/content-marketplace.php?type=photo" class="filter-btn <?= $typeFilter === 'photo' ? 'active' : '' ?>">
                📷 Photos
            </a>
            <a href="/content-marketplace.php?type=video" class="filter-btn <?= $typeFilter === 'video' ? 'active' : '' ?>">
                🎥 Videos
            </a>
            <a href="/content-marketplace.php?type=audio" class="filter-btn <?= $typeFilter === 'audio' ? 'active' : '' ?>">
                🎵 Audio
            </a>
        </div>

        <?php if (empty($allItems)): ?>
            <div class="empty-state">
                <div style="font-size: 4rem; margin-bottom: 1rem;">🎨</div>
                <h3>No content available yet</h3>
                <p>Check back soon for exclusive content from operators!</p>
            </div>
        <?php else: ?>
            <div class="items-grid">
                <?php foreach ($allItems as $item): ?>
                    <?php
                    $operator = $operatorManager->getOperator($item['operator_id']);
                    $operatorName = $operator['profile']['display_names'][$hostname] ?? $operator['name'] ?? 'Unknown';
                    $owned = $contentManager->customerOwnsItem($customerId, $item['item_id']);
                    $typeIcons = [
                        'photo' => '📷',
                        'video' => '🎥',
                        'audio' => '🎵',
                        'document' => '📄'
                    ];
                    ?>
                    <div class="item-card" onclick="window.location.href='/content-marketplace.php?item_id=<?= urlencode($item['item_id']) ?>'">
                        <div class="item-thumbnail">
                            <?= $typeIcons[$item['type']] ?? '📦' ?>
                        </div>

                        <div class="item-content">
                            <div class="item-type-badge">
                                <?= strtoupper($item['type']) ?>
                            </div>

                            <div class="item-title">
                                <?= htmlspecialchars($item['title']) ?>
                            </div>

                            <div class="item-operator">
                                By <?= htmlspecialchars($operatorName) ?>
                            </div>

                            <div class="item-description">
                                <?= htmlspecialchars(substr($item['description'], 0, 100)) ?>
                                <?= strlen($item['description']) > 100 ? '...' : '' ?>
                            </div>

                            <div class="item-stats">
                                <?php if ($owned): ?>
                                    <div class="owned-badge">✅ Owned</div>
                                <?php else: ?>
                                    <div class="item-price <?= $item['price'] == 0 ? 'free' : '' ?>">
                                        <?= $item['price'] == 0 ? 'FREE' : '$' . number_format($item['price'], 2) ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php if (!$owned && $item['price'] > 0): ?>
                                <form method="POST" onclick="event.stopPropagation();">
                                    <input type="hidden" name="item_id" value="<?= htmlspecialchars($item['item_id']) ?>">
                                    <button type="submit" name="purchase" class="btn btn-purchase">
                                        💳 Purchase for $<?= number_format($item['price'], 2) ?>
                                    </button>
                                </form>
                            <?php elseif (!$owned && $item['price'] == 0): ?>
                                <form method="POST" onclick="event.stopPropagation();">
                                    <input type="hidden" name="item_id" value="<?= htmlspecialchars($item['item_id']) ?>">
                                    <button type="submit" name="purchase" class="btn btn-purchase">
                                        🎁 Get Free
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($myPurchases)): ?>
            <h2 class="section-title">📦 My Purchases (<?= count($myPurchases) ?>)</h2>
            <div class="items-grid">
                <?php foreach (array_slice($myPurchases, 0, 6) as $purchase): ?>
                    <?php
                    $item = $contentManager->getItem($purchase['item_id']);
                    if (!$item) continue;
                    $operator = $operatorManager->getOperator($item['operator_id']);
                    $operatorName = $operator['profile']['display_names'][$hostname] ?? $operator['name'] ?? 'Unknown';
                    $typeIcons = [
                        'photo' => '📷',
                        'video' => '🎥',
                        'audio' => '🎵',
                        'document' => '📄'
                    ];
                    ?>
                    <div class="item-card" onclick="window.location.href='/content-marketplace.php?item_id=<?= urlencode($item['item_id']) ?>'">
                        <div class="item-thumbnail">
                            <?= $typeIcons[$item['type']] ?? '📦' ?>
                        </div>

                        <div class="item-content">
                            <div class="item-type-badge">
                                <?= strtoupper($item['type']) ?>
                            </div>

                            <div class="item-title">
                                <?= htmlspecialchars($item['title']) ?>
                            </div>

                            <div class="item-operator">
                                By <?= htmlspecialchars($operatorName) ?>
                            </div>

                            <div class="item-stats">
                                <div class="owned-badge">✅ Owned</div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
