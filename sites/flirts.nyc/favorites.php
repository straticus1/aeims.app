<?php
/**
 * Favorites Management Page
 * View and manage favorite operators
 */

session_start();

if (!isset($_SESSION['customer_id'])) {
    header('Location: /');
    exit;
}

require_once 'services/SiteManager.php';
require_once 'services/CustomerManager.php';
require_once 'services/OperatorManager.php';
require_once __DIR__ . '/../../includes/DataLayer.php';

try {
    $siteManager = new \AEIMS\Services\SiteManager();
    $customerManager = new \AEIMS\Services\CustomerManager();
    $operatorManager = new \AEIMS\Services\OperatorManager();
    $dataLayer = getDataLayer();

    $hostname = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $hostname = preg_replace('/^www\./', '', $hostname);
    $hostname = preg_replace('/:\d+$/', '', $hostname);

    $site = $siteManager->getSite($hostname);
    $customer = $customerManager->getCustomer($_SESSION['customer_id']);

    if (!$site || !$site['active'] || !$customer) {
        session_destroy();
        header('Location: /');
        exit;
    }

    // Load favorites using DataLayer
    $customerId = $_SESSION['customer_id'];
    $customerFavorites = $dataLayer->getFavorites($customerId);

    // Handle actions
    if (isset($_GET['action'])) {
        if ($_GET['action'] === 'add' && isset($_GET['operator_id'])) {
            $operatorId = $_GET['operator_id'];
            if (!in_array($operatorId, $customerFavorites)) {
                $dataLayer->addFavorite($customerId, $operatorId);
                $_SESSION['message'] = 'Operator added to favorites!';
            }
            header('Location: /favorites.php');
            exit;
        } elseif ($_GET['action'] === 'remove' && isset($_GET['operator_id'])) {
            $operatorId = $_GET['operator_id'];
            $dataLayer->removeFavorite($customerId, $operatorId);
            $_SESSION['message'] = 'Operator removed from favorites.';
            header('Location: /favorites.php');
            exit;
        }
    }

    // Get favorite operators
    $favoriteOperators = [];
    foreach ($customerFavorites as $operatorId) {
        $operator = $operatorManager->getOperator($operatorId);
        if ($operator) {
            $favoriteOperators[] = $operator;
        }
    }

    $message = $_SESSION['message'] ?? null;
    unset($_SESSION['message']);

} catch (Exception $e) {
    error_log("Favorites error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Favorites - <?= htmlspecialchars($site['name']) ?></title>
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
            padding: 1rem 0;
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(239, 68, 68, 0.3);
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
            font-size: 1.8rem;
            font-weight: bold;
            color: <?= $site['theme']['primary_color'] ?>;
            text-decoration: none;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 2rem;
        }

        .credits-display {
            background: linear-gradient(45deg, <?= $site['theme']['primary_color'] ?>, <?= $site['theme']['secondary_color'] ?>);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 500;
        }

        .btn {
            padding: 0.7rem 1.5rem;
            border: none;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            cursor: pointer;
            display: inline-block;
        }

        .btn-primary {
            background: linear-gradient(45deg, <?= $site['theme']['primary_color'] ?>, <?= $site['theme']['secondary_color'] ?>);
            color: white;
        }

        .btn-secondary {
            background: transparent;
            color: <?= $site['theme']['primary_color'] ?>;
            border: 1px solid <?= $site['theme']['primary_color'] ?>;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(239, 68, 68, 0.3);
        }

        .main-content {
            margin-top: 80px;
            padding: 2rem;
            max-width: 1400px;
            margin-left: auto;
            margin-right: auto;
        }

        .page-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .page-header h1 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            background: linear-gradient(45deg, <?= $site['theme']['primary_color'] ?>, <?= $site['theme']['accent_color'] ?>);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .page-header p {
            color: #ffffff;
        }

        .operators-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 2rem;
        }

        .operator-card {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 1.5rem;
            border: 1px solid rgba(239, 68, 68, 0.2);
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }

        .operator-card:hover {
            transform: translateY(-5px);
            border-color: <?= $site['theme']['primary_color'] ?>;
            box-shadow: 0 10px 30px rgba(239, 68, 68, 0.3);
        }

        .operator-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .operator-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(45deg, <?= $site['theme']['primary_color'] ?>, <?= $site['theme']['accent_color'] ?>);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: bold;
            color: white;
        }

        .operator-info h3 {
            color: <?= $site['theme']['primary_color'] ?>;
            margin-bottom: 0.25rem;
        }

        .rating {
            color: #fbbf24;
            font-size: 0.9rem;
        }

        .operator-bio {
            margin: 1rem 0;
            color: #ffffff;
            font-size: 0.9rem;
        }

        .operator-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .btn-small {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
            flex: 1;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #ffffff;
        }

        .empty-state h3 {
            font-size: 1.8rem;
            margin-bottom: 1rem;
            color: <?= $site['theme']['primary_color'] ?>;
        }

        .alert {
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: 10px;
            text-align: center;
            background: rgba(34, 197, 94, 0.2);
            border: 1px solid rgba(34, 197, 94, 0.5);
            color: #86efac;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="nav-container">
            <a href="/" class="logo"><?= htmlspecialchars($site['name']) ?></a>

            <div class="user-info">
                <div class="credits-display">
                    Credits: $<?= number_format($customer['billing']['credits'], 2) ?>
                </div>
                <a href="/search-operators.php" class="btn btn-secondary">Search</a>
                <a href="/messages.php" class="btn btn-secondary">Messages</a>
                <a href="/profile.php" class="btn btn-secondary">Profile</a>
                <a href="/logout.php" class="btn btn-secondary">Logout</a>
            </div>
        </div>
    </header>

    <main class="main-content">
        <div class="page-header">
            <h1>My Favorites</h1>
            <p>Quick access to your favorite operators</p>
        </div>

        <?php if ($message): ?>
            <div class="alert"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php if (empty($favoriteOperators)): ?>
            <div class="empty-state">
                <h3>No Favorites Yet</h3>
                <p>Browse operators and add them to your favorites for quick access.</p>
                <a href="/search-operators.php" class="btn btn-primary" style="margin-top: 1rem;">Browse Operators</a>
            </div>
        <?php else: ?>
            <div class="operators-grid">
                <?php foreach ($favoriteOperators as $operator): ?>
                    <?php
                    $displayName = $operator['profile']['display_names'][$hostname] ?? $operator['name'];
                    $bio = $operator['profile']['bios'][$hostname] ?? 'Experienced operator';
                    $callRate = $operator['settings']['services'][$hostname]['calls']['rate_per_minute'] ?? 0;
                    $rating = $operator['stats']['today']['rating'] ?? 0;
                    ?>
                    <div class="operator-card">
                        <div class="operator-header">
                            <div class="operator-avatar">
                                <?= strtoupper(substr($displayName, 0, 2)) ?>
                            </div>
                            <div class="operator-info">
                                <h3><?= htmlspecialchars($displayName) ?></h3>
                                <div class="rating">★ <?= number_format($rating, 1) ?></div>
                            </div>
                        </div>

                        <div class="operator-bio">
                            <?= htmlspecialchars($bio) ?>
                        </div>

                        <div class="operator-actions">
                            <a href="/operator-profile.php?id=<?= $operator['id'] ?>" class="btn btn-primary btn-small">
                                View Profile
                            </a>
                            <a href="/messages.php?operator_id=<?= $operator['id'] ?>" class="btn btn-secondary btn-small">
                                Message
                            </a>
                        </div>
                        <div style="margin-top: 0.5rem;">
                            <a href="/favorites.php?action=remove&operator_id=<?= $operator['id'] ?>"
                               class="btn btn-secondary btn-small"
                               style="width: 100%; background: rgba(239, 68, 68, 0.2); border-color: #ef4444;">
                                Remove from Favorites
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
