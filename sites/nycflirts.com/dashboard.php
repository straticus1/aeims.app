<?php
/**
 * Customer Dashboard
 * Main interface for logged-in customers to browse operators and manage account
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication
if (!isset($_SESSION['customer_id'])) {
    header('Location: /');
    exit;
}

// Load services
require_once '../../services/SiteManager.php';
require_once '../../services/CustomerManager.php';
require_once '../../services/OperatorManager.php';

try {
    $siteManager = new \AEIMS\Services\SiteManager();
    $customerManager = new \AEIMS\Services\CustomerManager();
    $operatorManager = new \AEIMS\Services\OperatorManager();

    // Dynamically determine site from HTTP_HOST
    $hostname = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'nycflirts.com';
    $hostname = preg_replace('/^www\./', '', $hostname); // Remove www.
    $hostname = preg_replace('/:\d+$/', '', $hostname);  // Remove port
    $site = $siteManager->getSite($hostname);
    $customer = $customerManager->getCustomer($_SESSION['customer_id']);
    $operators = $operatorManager->getActiveOperators();

    if (!$site || !$site['active'] || !$customer) {
        session_destroy();
        header('Location: /');
        exit;
    }
} catch (Exception $e) {
    error_log("Dashboard error: " . $e->getMessage());
    session_destroy();
    header('Location: /');
    exit;
}

$selectedCategory = $_GET['category'] ?? 'all';
$filteredOperators = [];

// Filter operators by category if specified
if ($selectedCategory === 'all') {
    $filteredOperators = $operators;
} else {
    foreach ($operators as $operator) {
        if (in_array($selectedCategory, $operator['profile']['specialties'] ?? [])) {
            $filteredOperators[] = $operator;
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?= htmlspecialchars($site['name']) ?></title>
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
            color: <?= $site['theme']['text_color'] ?>;
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
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
        }

        .dashboard-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .dashboard-header h1 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            background: linear-gradient(45deg, <?= $site['theme']['primary_color'] ?>, <?= $site['theme']['accent_color'] ?>);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .filters {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            justify-content: center;
        }

        .filter-btn {
            padding: 0.5rem 1rem;
            background: rgba(255, 255, 255, 0.1);
            color: <?= $site['theme']['text_color'] ?>;
            border: 1px solid rgba(239, 68, 68, 0.3);
            border-radius: 20px;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .filter-btn.active {
            background: <?= $site['theme']['primary_color'] ?>;
            color: white;
        }

        .filter-btn:hover {
            border-color: <?= $site['theme']['primary_color'] ?>;
            background: rgba(239, 68, 68, 0.2);
        }

        .operators-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
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

        .operator-status {
            padding: 0.25rem 0.75rem;
            border-radius: 10px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-online {
            background: #22c55e;
            color: white;
        }

        .status-offline {
            background: #6b7280;
            color: white;
        }

        .operator-bio {
            margin: 1rem 0;
            color: #d1d5db;
            font-size: 0.9rem;
        }

        .operator-specialties {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin: 1rem 0;
        }

        .specialty-tag {
            background: rgba(239, 68, 68, 0.2);
            color: <?= $site['theme']['primary_color'] ?>;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.8rem;
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

        .btn-primary {
            background: linear-gradient(45deg, <?= $site['theme']['primary_color'] ?>, <?= $site['theme']['secondary_color'] ?>);
            color: white;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #9ca3af;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        @media (max-width: 768px) {
            .nav-container {
                flex-direction: column;
                gap: 1rem;
            }

            .user-info {
                flex-direction: column;
                gap: 1rem;
            }

            .main-content {
                padding: 1rem;
            }

            .dashboard-header h1 {
                font-size: 2rem;
            }

            .operators-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="nav-container">
            <a href="/dashboard.php" class="logo"><?= htmlspecialchars($site['name']) ?></a>

            <div class="user-info">
                <div class="credits-display">
                    Credits: $<?= number_format($customer['billing']['credits'], 2) ?>
                </div>
                <a href="/search-operators.php" class="btn btn-secondary">Search</a>
                <a href="/messages.php" class="btn btn-secondary">Mail</a>
                <a href="/chat.php" class="btn btn-secondary">Chat</a>
                <a href="/activity-log.php" class="btn btn-secondary">Activity</a>
                <a href="/payment.php" class="btn btn-primary">Add Credits</a>
                <span>Welcome, <?= htmlspecialchars($customer['username']) ?></span>
                <a href="/auth.php?action=logout" class="btn btn-secondary">Logout</a>
            </div>
        </div>
    </header>

    <main class="main-content">
        <div class="dashboard-header">
            <h1>Explore Our Models</h1>
            <p>Connect with verified performers in your preferred categories</p>
        </div>

        <div class="filters">
            <a href="/dashboard.php?category=all" class="filter-btn <?= $selectedCategory === 'all' ? 'active' : '' ?>">
                All Categories
            </a>
            <?php foreach ($site['categories'] as $key => $category): ?>
                <?php if ($category['active']): ?>
                    <a href="/dashboard.php?category=<?= urlencode($key) ?>"
                       class="filter-btn <?= $selectedCategory === $key ? 'active' : '' ?>">
                        <?= htmlspecialchars($category['name']) ?>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <?php if (empty($filteredOperators)): ?>
            <div class="empty-state">
                <h3>No Models Available</h3>
                <p>There are currently no models available in this category. Try checking another category or come back later.</p>
            </div>
        <?php else: ?>
            <div class="operators-grid">
                <?php foreach ($filteredOperators as $operator): ?>
                    <div class="operator-card">
                        <div class="operator-header">
                            <div class="operator-avatar">
                                <?= strtoupper(substr($operator['username'], 0, 2)) ?>
                            </div>
                            <div class="operator-info">
                                <h3><?= htmlspecialchars($operator['username']) ?></h3>
                                <span class="operator-status status-online">Online</span>
                            </div>
                        </div>

                        <div class="operator-bio">
                            <?= htmlspecialchars($operator['profile']['bio'] ?? 'Experienced performer ready to entertain') ?>
                        </div>

                        <div class="operator-specialties">
                            <?php foreach ($operator['profile']['specialties'] ?? [] as $specialty): ?>
                                <span class="specialty-tag">
                                    <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $specialty))) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>

                        <div class="operator-actions">
                            <a href="/operator-profile.php?id=<?= $operator['operator_id'] ?>" class="btn btn-primary btn-small">
                                View Profile
                            </a>
                            <a href="/chat.php?operator_id=<?= $operator['operator_id'] ?>" class="btn btn-secondary btn-small">
                                Quick Chat
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <script>
        function startChat(operatorId) {
            // Check if customer has sufficient credits
            const credits = <?= $customer['billing']['credits'] ?>;
            if (credits < 0.50) {
                alert('Insufficient credits! Please add credits to your account.');
                return;
            }

            // For now, just show a placeholder message
            alert(`Starting chat with operator ${operatorId}. Chat system integration coming soon!`);
        }

        function startCall(operatorId) {
            // Check if customer has sufficient credits
            const credits = <?= $customer['billing']['credits'] ?>;
            const callRate = <?= $site['billing']['call_rates']['standard'] ?>;

            if (credits < callRate) {
                alert('Insufficient credits! Please add credits to your account.');
                return;
            }

            // For now, just show a placeholder message
            alert(`Starting call with operator ${operatorId}. Call system integration coming soon!`);
        }
    </script>
</body>
</html>