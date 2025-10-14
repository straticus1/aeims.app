<?php
/**
 * Operator Search Page
 * Advanced search and filter for operators
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication
if (!isset($_SESSION['customer_id'])) {
    header('Location: /');
    exit;
}

require_once 'services/SiteManager.php';
require_once 'services/CustomerManager.php';
require_once 'services/OperatorManager.php';

try {
    $siteManager = new \AEIMS\Services\SiteManager();
    $customerManager = new \AEIMS\Services\CustomerManager();
    $operatorManager = new \AEIMS\Services\OperatorManager();

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
} catch (Exception $e) {
    error_log("Search error: " . $e->getMessage());
    session_destroy();
    header('Location: /');
    exit;
}

// Get search parameters
$searchQuery = $_GET['q'] ?? '';
$category = $_GET['category'] ?? 'all';
$sortBy = $_GET['sort'] ?? 'rating'; // rating, price, newest
$minPrice = isset($_GET['min_price']) ? floatval($_GET['min_price']) : null;
$maxPrice = isset($_GET['max_price']) ? floatval($_GET['max_price']) : null;
$services = $_GET['services'] ?? []; // calls, chat, video, cam

// Get all operators and apply filters
$allOperators = $operatorManager->getActiveOperators();
$filteredOperators = [];

foreach ($allOperators as $operator) {
    // Domain filter
    if (!isset($operator['domains'][$hostname]) || !$operator['domains'][$hostname]['active']) {
        continue;
    }

    // Search query filter
    if ($searchQuery) {
        $displayName = $operator['profile']['display_names'][$hostname] ?? $operator['name'];
        $bio = $operator['profile']['bios'][$hostname] ?? '';

        if (stripos($displayName, $searchQuery) === false && stripos($bio, $searchQuery) === false) {
            continue;
        }
    }

    // Category filter
    if ($category !== 'all') {
        if (!in_array($category, $operator['profile']['specialties'] ?? [])) {
            continue;
        }
    }

    // Services filter
    if (!empty($services)) {
        $operatorServices = $operator['services'] ?? [];
        $hasAllServices = true;
        foreach ($services as $service) {
            if (!in_array($service, $operatorServices)) {
                $hasAllServices = false;
                break;
            }
        }
        if (!$hasAllServices) {
            continue;
        }
    }

    // Price filter (based on call rate)
    if ($minPrice !== null || $maxPrice !== null) {
        $callRate = $operator['settings']['services'][$hostname]['calls']['rate_per_minute'] ?? 0;
        if ($minPrice !== null && $callRate < $minPrice) continue;
        if ($maxPrice !== null && $callRate > $maxPrice) continue;
    }

    $filteredOperators[] = $operator;
}

// Sort operators
usort($filteredOperators, function($a, $b) use ($sortBy, $hostname) {
    switch ($sortBy) {
        case 'price':
            $aPrice = $a['settings']['services'][$hostname]['calls']['rate_per_minute'] ?? 0;
            $bPrice = $b['settings']['services'][$hostname]['calls']['rate_per_minute'] ?? 0;
            return $aPrice <=> $bPrice;
        case 'newest':
            return strtotime($b['created_at']) <=> strtotime($a['created_at']);
        case 'rating':
        default:
            $aRating = $a['stats']['today']['rating'] ?? 0;
            $bRating = $b['stats']['today']['rating'] ?? 0;
            return $bRating <=> $aRating;
    }
});

$resultCount = count($filteredOperators);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Operators - <?= htmlspecialchars($site['name']) ?></title>
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

        .search-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .search-header h1 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            background: linear-gradient(45deg, <?= $site['theme']['primary_color'] ?>, <?= $site['theme']['accent_color'] ?>);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .search-container {
            display: flex;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .search-box {
            flex: 1;
            display: flex;
            gap: 1rem;
        }

        .search-input {
            flex: 1;
            padding: 1rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            background: rgba(0, 0, 0, 0.3);
            color: white;
            font-size: 1rem;
        }

        .search-input:focus {
            outline: none;
            border-color: <?= $site['theme']['primary_color'] ?>;
        }

        .filters {
            background: rgba(255, 255, 255, 0.05);
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .filter-group label {
            font-weight: 500;
            color: <?= $site['theme']['primary_color'] ?>;
        }

        .filter-group select,
        .filter-group input {
            padding: 0.75rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            background: rgba(0, 0, 0, 0.3);
            color: white;
        }

        .filter-checkboxes {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
        }

        .results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .result-count {
            font-size: 1.1rem;
            color: #9ca3af;
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
            color: #d1d5db;
            font-size: 0.9rem;
        }

        .operator-pricing {
            display: flex;
            gap: 1rem;
            margin: 1rem 0;
            font-size: 0.85rem;
        }

        .price-tag {
            background: rgba(239, 68, 68, 0.2);
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
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
            color: #9ca3af;
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
                <a href="/dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
                <a href="/auth.php?action=logout" class="btn btn-secondary">Logout</a>
            </div>
        </div>
    </header>

    <main class="main-content">
        <div class="search-header">
            <h1>Find Your Perfect Match</h1>
            <p>Advanced search for operators</p>
        </div>

        <form method="GET" action="/search-operators.php">
            <div class="search-container">
                <div class="search-box">
                    <input type="text" name="q" class="search-input" placeholder="Search by name or interests..." value="<?= htmlspecialchars($searchQuery) ?>">
                    <button type="submit" class="btn btn-primary">Search</button>
                </div>
            </div>

            <div class="filters">
                <div class="filter-row">
                    <div class="filter-group">
                        <label>Category</label>
                        <select name="category">
                            <option value="all" <?= $category === 'all' ? 'selected' : '' ?>>All Categories</option>
                            <option value="girlfriend_experience" <?= $category === 'girlfriend_experience' ? 'selected' : '' ?>>Girlfriend Experience</option>
                            <option value="roleplay" <?= $category === 'roleplay' ? 'selected' : '' ?>>Roleplay</option>
                            <option value="domination" <?= $category === 'domination' ? 'selected' : '' ?>>Domination</option>
                            <option value="fetish" <?= $category === 'fetish' ? 'selected' : '' ?>>Fetish</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>Sort By</label>
                        <select name="sort">
                            <option value="rating" <?= $sortBy === 'rating' ? 'selected' : '' ?>>Highest Rated</option>
                            <option value="price" <?= $sortBy === 'price' ? 'selected' : '' ?>>Lowest Price</option>
                            <option value="newest" <?= $sortBy === 'newest' ? 'selected' : '' ?>>Newest First</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>Min Price ($/min)</label>
                        <input type="number" name="min_price" step="0.50" min="0" max="20" value="<?= $minPrice ?? '' ?>" placeholder="0.00">
                    </div>

                    <div class="filter-group">
                        <label>Max Price ($/min)</label>
                        <input type="number" name="max_price" step="0.50" min="0" max="20" value="<?= $maxPrice ?? '' ?>" placeholder="20.00">
                    </div>
                </div>

                <div class="filter-group">
                    <label>Services</label>
                    <div class="filter-checkboxes">
                        <label class="checkbox-label">
                            <input type="checkbox" name="services[]" value="calls" <?= in_array('calls', $services) ? 'checked' : '' ?>>
                            <span>Calls</span>
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="services[]" value="chat" <?= in_array('chat', $services) ? 'checked' : '' ?>>
                            <span>Chat</span>
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="services[]" value="video" <?= in_array('video', $services) ? 'checked' : '' ?>>
                            <span>Video</span>
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="services[]" value="cam" <?= in_array('cam', $services) ? 'checked' : '' ?>>
                            <span>Cam</span>
                        </label>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">Apply Filters</button>
            </div>
        </form>

        <div class="results-header">
            <div class="result-count">
                Found <?= $resultCount ?> operator<?= $resultCount !== 1 ? 's' : '' ?>
            </div>
        </div>

        <?php if (empty($filteredOperators)): ?>
            <div class="empty-state">
                <h3>No Operators Found</h3>
                <p>Try adjusting your search filters or browse all operators.</p>
                <a href="/dashboard.php" class="btn btn-primary" style="margin-top: 1rem;">View All Operators</a>
            </div>
        <?php else: ?>
            <div class="operators-grid">
                <?php foreach ($filteredOperators as $operator): ?>
                    <?php
                    $displayName = $operator['profile']['display_names'][$hostname] ?? $operator['name'];
                    $bio = $operator['profile']['bios'][$hostname] ?? 'Experienced operator';
                    $callRate = $operator['settings']['services'][$hostname]['calls']['rate_per_minute'] ?? 0;
                    $chatRate = $operator['settings']['services'][$hostname]['chat']['rate_per_minute'] ?? 0;
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

                        <div class="operator-pricing">
                            <?php if ($callRate > 0): ?>
                                <span class="price-tag">Calls: $<?= number_format($callRate, 2) ?>/min</span>
                            <?php endif; ?>
                            <?php if ($chatRate > 0): ?>
                                <span class="price-tag">Chat: $<?= number_format($chatRate, 2) ?>/min</span>
                            <?php endif; ?>
                        </div>

                        <div class="operator-actions">
                            <a href="/operator-profile.php?id=<?= $operator['id'] ?>" class="btn btn-primary btn-small">
                                View Profile
                            </a>
                            <a href="/messages.php?operator_id=<?= $operator['id'] ?>" class="btn btn-secondary btn-small">
                                Message
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
