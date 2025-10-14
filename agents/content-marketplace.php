<?php
/**
 * Operator Content Marketplace
 * Create and manage content items for sale
 */

session_start();

if (!isset($_SESSION['operator_id'])) {
    header('Location: /agents/login.php');
    exit;
}

require_once '../services/OperatorManager.php';
require_once '../services/ContentMarketplaceManager.php';

try {
    $operatorManager = new \AEIMS\Services\OperatorManager();
    $contentManager = new \AEIMS\Services\ContentMarketplaceManager();

    $operator = $operatorManager->getOperator($_SESSION['operator_id']);

    if (!$operator) {
        session_destroy();
        header('Location: /agents/login.php');
        exit;
    }

    // Handle item creation
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_item'])) {
        $type = $_POST['type'];
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $price = (float)$_POST['price'];
        $fileUrl = $_POST['file_url']; // In production, this would be uploaded
        $thumbnailUrl = $_POST['thumbnail_url'] ?? null;
        $tags = isset($_POST['tags']) ? explode(',', $_POST['tags']) : [];

        try {
            $item = $contentManager->createItem(
                $_SESSION['operator_id'],
                $type,
                $title,
                $description,
                $price,
                $fileUrl,
                $thumbnailUrl,
                array_map('trim', $tags)
            );

            $_SESSION['content_success'] = 'Content item created successfully!';
            header('Location: /agents/content-marketplace.php');
            exit;
        } catch (Exception $e) {
            $_SESSION['content_error'] = $e->getMessage();
        }
    }

    // Handle item deletion
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_item'])) {
        $itemId = $_POST['item_id'];

        try {
            $contentManager->deleteItem($itemId, $_SESSION['operator_id']);
            $_SESSION['content_success'] = 'Item removed successfully!';
            header('Location: /agents/content-marketplace.php');
            exit;
        } catch (Exception $e) {
            $_SESSION['content_error'] = $e->getMessage();
        }
    }

    // Get operator's items
    $myItems = $contentManager->getOperatorItems($_SESSION['operator_id'], 'active');
    $sales = $contentManager->getOperatorSales($_SESSION['operator_id']);
    $totalEarnings = $contentManager->getOperatorEarnings($_SESSION['operator_id']);

} catch (Exception $e) {
    error_log("Content marketplace error: " . $e->getMessage());
    $_SESSION['content_error'] = "An error occurred. Please try again.";
}

$contentSuccess = $_SESSION['content_success'] ?? null;
$contentError = $_SESSION['content_error'] ?? null;
unset($_SESSION['content_success'], $_SESSION['content_error']);

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
    <title>Content Marketplace - Operator Dashboard</title>
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

        .btn-danger {
            background: linear-gradient(45deg, #ef4444, #dc2626);
            color: white;
            font-size: 0.85rem;
            padding: 0.5rem 1rem;
        }

        .container {
            max-width: 1400px;
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: rgba(0, 0, 0, 0.5);
            padding: 1.5rem;
            border-radius: 15px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .stat-label {
            color: #9ca3af;
            font-size: 0.85rem;
            margin-bottom: 0.5rem;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #667eea;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 400px 1fr;
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
            min-height: 80px;
        }

        .items-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
        }

        .item-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            overflow: hidden;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .item-card:hover {
            border-color: #667eea;
            transform: translateY(-5px);
        }

        .item-thumbnail {
            width: 100%;
            height: 200px;
            background: linear-gradient(135deg, #667eea, #764ba2);
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
            background: rgba(102, 126, 234, 0.3);
            color: #667eea;
        }

        .item-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: white;
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
            color: #667eea;
        }

        .item-meta {
            font-size: 0.75rem;
            color: #9ca3af;
        }

        .item-actions {
            margin-top: 1rem;
            display: flex;
            gap: 0.5rem;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #9ca3af;
        }

        @media (max-width: 1024px) {
            .grid-2 {
                grid-template-columns: 1fr;
            }
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
            <a href="/agents/operator-messages.php" class="btn btn-secondary">Messages</a>
            <a href="/agents/logout.php" class="btn btn-secondary">Logout</a>
        </div>
    </header>

    <div class="container">
        <h1 class="page-title">🎨 Content Marketplace</h1>

        <?php if ($contentSuccess): ?>
            <div class="alert alert-success"><?= htmlspecialchars($contentSuccess) ?></div>
        <?php endif; ?>

        <?php if ($contentError): ?>
            <div class="alert alert-error"><?= htmlspecialchars($contentError) ?></div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Items</div>
                <div class="stat-value"><?= count($myItems) ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Total Sales</div>
                <div class="stat-value"><?= count($sales) ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Total Earnings</div>
                <div class="stat-value">$<?= number_format($totalEarnings, 2) ?></div>
            </div>
        </div>

        <div class="grid-2">
            <div class="card">
                <h2>🎨 Create New Item</h2>

                <form method="POST">
                    <div class="form-group">
                        <label>Content Type</label>
                        <select name="type" class="form-control" required>
                            <option value="photo">📷 Photo</option>
                            <option value="video">🎥 Video</option>
                            <option value="audio">🎵 Audio</option>
                            <option value="document">📄 Document</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Title</label>
                        <input type="text" name="title" class="form-control" placeholder="e.g., Exclusive Photo Set" required>
                    </div>

                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" class="form-control" placeholder="Describe your content..." required></textarea>
                    </div>

                    <div class="form-group">
                        <label>Price ($)</label>
                        <input type="number" name="price" class="form-control" min="0" step="0.01" value="0.00" required>
                        <small style="color: #9ca3af;">Set to 0 for free content</small>
                    </div>

                    <div class="form-group">
                        <label>File URL</label>
                        <input type="text" name="file_url" class="form-control" placeholder="/uploads/content/file.jpg" required>
                        <small style="color: #9ca3af;">Path to uploaded file</small>
                    </div>

                    <div class="form-group">
                        <label>Thumbnail URL (optional)</label>
                        <input type="text" name="thumbnail_url" class="form-control" placeholder="/uploads/thumbnails/thumb.jpg">
                    </div>

                    <div class="form-group">
                        <label>Tags (comma-separated)</label>
                        <input type="text" name="tags" class="form-control" placeholder="exclusive, photos, premium">
                    </div>

                    <button type="submit" name="create_item" class="btn btn-primary" style="width: 100%;">
                        🎨 Create Item
                    </button>
                </form>
            </div>

            <div class="card">
                <h2>📦 My Content Items (<?= count($myItems) ?>)</h2>

                <?php if (empty($myItems)): ?>
                    <div class="empty-state">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">📦</div>
                        <h3>No items yet</h3>
                        <p>Create your first content item to start selling!</p>
                    </div>
                <?php else: ?>
                    <div class="items-grid">
                        <?php foreach ($myItems as $item): ?>
                            <div class="item-card">
                                <div class="item-thumbnail">
                                    <?php
                                    $typeIcons = [
                                        'photo' => '📷',
                                        'video' => '🎥',
                                        'audio' => '🎵',
                                        'document' => '📄'
                                    ];
                                    echo $typeIcons[$item['type']] ?? '📦';
                                    ?>
                                </div>

                                <div class="item-content">
                                    <div class="item-type-badge">
                                        <?= strtoupper($item['type']) ?>
                                    </div>

                                    <div class="item-title">
                                        <?= htmlspecialchars($item['title']) ?>
                                    </div>

                                    <div class="item-description">
                                        <?= htmlspecialchars(substr($item['description'], 0, 100)) ?>
                                        <?= strlen($item['description']) > 100 ? '...' : '' ?>
                                    </div>

                                    <div class="item-stats">
                                        <div class="item-price <?= $item['price'] == 0 ? 'free' : '' ?>">
                                            <?= $item['price'] == 0 ? 'FREE' : '$' . number_format($item['price'], 2) ?>
                                        </div>
                                        <div class="item-meta">
                                            <?= $item['views'] ?> views<br>
                                            <?= $item['purchases'] ?> purchases
                                        </div>
                                    </div>

                                    <div class="item-actions">
                                        <form method="POST" style="flex: 1;">
                                            <input type="hidden" name="item_id" value="<?= htmlspecialchars($item['item_id']) ?>">
                                            <button type="submit" name="delete_item" class="btn btn-danger" style="width: 100%;"
                                                    onclick="return confirm('Remove this item?')">
                                                🗑️ Remove
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
