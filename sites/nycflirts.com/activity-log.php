<?php
/**
 * Customer Activity Log
 * Track spending breakdown by feature with filters
 */

session_start();

if (!isset($_SESSION['customer_id'])) {
    header('Location: /');
    exit;
}

require_once 'services/SiteManager.php';
require_once 'services/CustomerManager.php';
require_once 'services/OperatorManager.php';
require_once 'services/ActivityLogger.php';

try {
    $siteManager = new \AEIMS\Services\SiteManager();
    $customerManager = new \AEIMS\Services\CustomerManager();
    $operatorManager = new \AEIMS\Services\OperatorManager();
    $activityLogger = new \AEIMS\Services\ActivityLogger();

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

    // Get filter parameters
    $preset = $_GET['preset'] ?? 'monthly';
    $filterType = $_GET['type'] ?? '';
    $filterOperator = $_GET['operator'] ?? '';
    $customStart = $_GET['start_date'] ?? '';
    $customEnd = $_GET['end_date'] ?? '';

    // Calculate date range
    if ($customStart && $customEnd) {
        $startDate = $customStart;
        $endDate = $customEnd;
    } else {
        $dateRange = \AEIMS\Services\ActivityLogger::getDateRangePreset($preset);
        $startDate = $dateRange['start'];
        $endDate = $dateRange['end'];
    }

    // Get spending data
    $spending = $activityLogger->getCustomerSpending(
        $_SESSION['customer_id'],
        $startDate,
        $endDate,
        $filterOperator ?: null,
        $filterType ?: null
    );

    // Get most viewed operators
    $mostViewed = $activityLogger->getMostViewedOperators($_SESSION['customer_id'], 5);

    // Get profile viewers (who viewed your profile)
    $profileViewers = $activityLogger->getProfileViewers($_SESSION['customer_id'], $startDate, $endDate);

    // Get all operators for filter dropdown
    $allOperators = $operatorManager->getActiveOperators();

} catch (Exception $e) {
    error_log("Activity log error: " . $e->getMessage());
    $error = "An error occurred loading your activity log.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Log - <?= htmlspecialchars($site['name']) ?></title>
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
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(239, 68, 68, 0.3);
        }

        .logo {
            font-size: 1.5rem;
            font-weight: bold;
            color: <?= $site['theme']['primary_color'] ?>;
            text-decoration: none;
        }

        .header-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .credits-display {
            background: linear-gradient(45deg, <?= $site['theme']['primary_color'] ?>, <?= $site['theme']['secondary_color'] ?>);
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
            color: <?= $site['theme']['primary_color'] ?>;
            border: 1px solid <?= $site['theme']['primary_color'] ?>;
        }

        .main-content {
            padding: 2rem;
            max-width: 1400px;
            margin: 0 auto;
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

        .filters-panel {
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
            font-size: 0.9rem;
        }

        .filter-group select,
        .filter-group input {
            padding: 0.75rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            background: rgba(0, 0, 0, 0.3);
            color: white;
        }

        .preset-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .preset-btn {
            padding: 0.5rem 1rem;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            border-radius: 20px;
            color: white;
            text-decoration: none;
            font-size: 0.85rem;
            transition: all 0.2s;
        }

        .preset-btn:hover,
        .preset-btn.active {
            background: <?= $site['theme']['primary_color'] ?>;
            border-color: <?= $site['theme']['primary_color'] ?>;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.05);
            padding: 1.5rem;
            border-radius: 15px;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .stat-label {
            font-size: 0.9rem;
            color: #ffffff;
            margin-bottom: 0.5rem;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: <?= $site['theme']['primary_color'] ?>;
        }

        .breakdown-section {
            background: rgba(255, 255, 255, 0.05);
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .breakdown-section h2 {
            color: <?= $site['theme']['primary_color'] ?>;
            margin-bottom: 1.5rem;
        }

        .breakdown-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 10px;
            margin-bottom: 1rem;
        }

        .breakdown-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .breakdown-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(45deg, <?= $site['theme']['primary_color'] ?>, <?= $site['theme']['accent_color'] ?>);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

        .breakdown-details {
            flex: 1;
        }

        .breakdown-label {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .breakdown-count {
            font-size: 0.85rem;
            color: #ffffff;
        }

        .breakdown-amount {
            font-size: 1.5rem;
            font-weight: bold;
            color: <?= $site['theme']['accent_color'] ?>;
        }

        .activity-table {
            width: 100%;
            border-collapse: collapse;
        }

        .activity-table th {
            background: rgba(239, 68, 68, 0.2);
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid rgba(239, 68, 68, 0.3);
        }

        .activity-table td {
            padding: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .activity-table tr:hover {
            background: rgba(255, 255, 255, 0.05);
        }

        .activity-type {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .type-message { background: rgba(59, 130, 246, 0.2); color: #93c5fd; }
        .type-call { background: rgba(16, 185, 129, 0.2); color: #6ee7b7; }
        .type-video { background: rgba(168, 85, 247, 0.2); color: #c4b5fd; }
        .type-cam { background: rgba(244, 63, 94, 0.2); color: #fda4af; }
        .type-chat { background: rgba(251, 146, 60, 0.2); color: #fdba74; }
        .type-toy_control { background: rgba(236, 72, 153, 0.2); color: #f9a8d4; }
        .type-content { background: rgba(139, 92, 246, 0.2); color: #c4b5fd; }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #ffffff;
        }

        .viewers-list {
            display: grid;
            gap: 1rem;
        }

        .viewer-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 10px;
        }

        .premium-feature {
            background: linear-gradient(45deg, rgba(251, 191, 36, 0.2), rgba(245, 158, 11, 0.2));
            border: 1px solid rgba(251, 191, 36, 0.5);
        }

        .premium-badge {
            background: linear-gradient(45deg, #fbbf24, #f59e0b);
            color: #000;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <header class="header">
        <a href="/dashboard.php" class="logo"><?= htmlspecialchars($site['name']) ?></a>

        <div class="header-actions">
            <div class="credits-display">
                Credits: $<?= number_format($customer['billing']['credits'], 2) ?>
            </div>
            <a href="/dashboard.php" class="btn btn-secondary">Dashboard</a>
            <a href="/messages.php" class="btn btn-secondary">Messages</a>
        </div>
    </header>

    <main class="main-content">
        <div class="page-header">
            <h1>Activity Log</h1>
            <p>Track your spending and activity history</p>
        </div>

        <form method="GET" class="filters-panel">
            <div class="filter-row">
                <div class="filter-group">
                    <label>Activity Type</label>
                    <select name="type">
                        <option value="">All Types</option>
                        <option value="message" <?= $filterType === 'message' ? 'selected' : '' ?>>Messages</option>
                        <option value="call" <?= $filterType === 'call' ? 'selected' : '' ?>>Calls</option>
                        <option value="video" <?= $filterType === 'video' ? 'selected' : '' ?>>Video</option>
                        <option value="cam" <?= $filterType === 'cam' ? 'selected' : '' ?>>Cam</option>
                        <option value="chat" <?= $filterType === 'chat' ? 'selected' : '' ?>>Chat</option>
                        <option value="toy_control" <?= $filterType === 'toy_control' ? 'selected' : '' ?>>Toy Control</option>
                        <option value="content" <?= $filterType === 'content' ? 'selected' : '' ?>>Content</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Operator</label>
                    <select name="operator">
                        <option value="">All Operators</option>
                        <?php foreach ($allOperators as $op): ?>
                            <?php if (isset($op['domains'][$hostname])): ?>
                                <option value="<?= $op['id'] ?>" <?= $filterOperator === $op['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($op['profile']['display_names'][$hostname] ?? $op['name']) ?>
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Start Date</label>
                    <input type="date" name="start_date" value="<?= htmlspecialchars($customStart) ?>">
                </div>

                <div class="filter-group">
                    <label>End Date</label>
                    <input type="date" name="end_date" value="<?= htmlspecialchars($customEnd) ?>">
                </div>
            </div>

            <div class="filter-group">
                <label>Quick Presets</label>
                <div class="preset-buttons">
                    <a href="?preset=today" class="preset-btn <?= $preset === 'today' ? 'active' : '' ?>">Today</a>
                    <a href="?preset=yesterday" class="preset-btn <?= $preset === 'yesterday' ? 'active' : '' ?>">Yesterday</a>
                    <a href="?preset=week" class="preset-btn <?= $preset === 'week' ? 'active' : '' ?>">Week</a>
                    <a href="?preset=bi-weekly" class="preset-btn <?= $preset === 'bi-weekly' ? 'active' : '' ?>">Bi-weekly</a>
                    <a href="?preset=monthly" class="preset-btn <?= $preset === 'monthly' ? 'active' : '' ?>">Monthly</a>
                    <a href="?preset=quarterly" class="preset-btn <?= $preset === 'quarterly' ? 'active' : '' ?>">Quarterly</a>
                    <a href="?preset=half-year" class="preset-btn <?= $preset === 'half-year' ? 'active' : '' ?>">Half Year</a>
                    <a href="?preset=half-year-plus-3" class="preset-btn <?= $preset === 'half-year-plus-3' ? 'active' : '' ?>">9 Months</a>
                    <a href="?preset=yearly" class="preset-btn <?= $preset === 'yearly' ? 'active' : '' ?>">Yearly</a>
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="margin-top: 1rem;">Apply Filters</button>
        </form>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Spent</div>
                <div class="stat-value">$<?= number_format($spending['total_spent'], 2) ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Total Activities</div>
                <div class="stat-value"><?= number_format($spending['count']) ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Date Range</div>
                <div class="stat-value" style="font-size: 1.2rem;">
                    <?= date('M d', strtotime($startDate)) ?> - <?= date('M d, Y', strtotime($endDate)) ?>
                </div>
            </div>
        </div>

        <?php if (!empty($spending['breakdown'])): ?>
            <div class="breakdown-section">
                <h2>Spending Breakdown</h2>

                <?php
                $iconMap = [
                    'message' => '💬',
                    'call' => '📞',
                    'video' => '📹',
                    'cam' => '📷',
                    'chat' => '💭',
                    'toy_control' => '🎮',
                    'content' => '🎁'
                ];

                $labelMap = [
                    'message' => 'Messages',
                    'call' => 'Voice Calls',
                    'video' => 'Video Calls',
                    'cam' => 'Cam Shows',
                    'chat' => 'Chat Sessions',
                    'toy_control' => 'Toy Control',
                    'content' => 'Premium Content'
                ];
                ?>

                <?php foreach ($spending['breakdown'] as $type => $data): ?>
                    <div class="breakdown-item">
                        <div class="breakdown-info">
                            <div class="breakdown-icon">
                                <?= $iconMap[$type] ?? '📊' ?>
                            </div>
                            <div class="breakdown-details">
                                <div class="breakdown-label"><?= $labelMap[$type] ?? ucfirst(str_replace('_', ' ', $type)) ?></div>
                                <div class="breakdown-count"><?= $data['count'] ?> transaction<?= $data['count'] !== 1 ? 's' : '' ?></div>
                            </div>
                        </div>
                        <div class="breakdown-amount">
                            $<?= number_format($data['total'], 2) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="breakdown-section">
            <h2>Most Viewed Operators</h2>

            <?php if (empty($mostViewed)): ?>
                <div class="empty-state">
                    <p>No operator views recorded yet.</p>
                </div>
            <?php else: ?>
                <div class="viewers-list">
                    <?php foreach ($mostViewed as $view): ?>
                        <?php
                        $op = $operatorManager->getOperator($view['operator_id']);
                        $opName = $op ? ($op['profile']['display_names'][$hostname] ?? $op['name']) : 'Unknown';
                        ?>
                        <div class="viewer-item">
                            <div>
                                <div style="font-weight: 600;"><?= htmlspecialchars($opName) ?></div>
                                <div style="font-size: 0.85rem; color: #ffffff;">
                                    <?= $view['view_count'] ?> views • Last: <?= date('M d, Y g:i A', strtotime($view['last_viewed'])) ?>
                                </div>
                            </div>
                            <a href="/operator-profile.php?id=<?= $view['operator_id'] ?>" class="btn btn-secondary">View Profile</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="breakdown-section premium-feature">
            <h2>Who Viewed Your Profile <span class="premium-badge">FREE (Soon $20)</span></h2>

            <?php if (empty($profileViewers)): ?>
                <div class="empty-state">
                    <p>No operators have viewed your profile yet.</p>
                    <p style="margin-top: 1rem; font-size: 0.9rem;">Complete your profile to attract more attention!</p>
                </div>
            <?php else: ?>
                <div class="viewers-list">
                    <?php foreach ($profileViewers as $view): ?>
                        <?php
                        $op = $operatorManager->getOperator($view['operator_id']);
                        $opName = $op ? ($op['profile']['display_names'][$hostname] ?? $op['name']) : 'Unknown';
                        ?>
                        <div class="viewer-item">
                            <div>
                                <div style="font-weight: 600;"><?= htmlspecialchars($opName) ?></div>
                                <div style="font-size: 0.85rem; color: #ffffff;">
                                    <?= date('M d, Y g:i A', strtotime($view['timestamp'])) ?>
                                </div>
                            </div>
                            <a href="/operator-profile.php?id=<?= $view['operator_id'] ?>" class="btn btn-secondary">View Profile</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($spending['activities'])): ?>
            <div class="breakdown-section">
                <h2>Recent Activity</h2>

                <table class="activity-table">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Type</th>
                            <th>Operator</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice(array_reverse($spending['activities']), 0, 50) as $activity): ?>
                            <?php
                            $op = $operatorManager->getOperator($activity['operator_id']);
                            $opName = $op ? ($op['profile']['display_names'][$hostname] ?? $op['name']) : 'Unknown';
                            ?>
                            <tr>
                                <td><?= date('M d, Y g:i A', strtotime($activity['timestamp'])) ?></td>
                                <td>
                                    <span class="activity-type type-<?= $activity['type'] ?>">
                                        <?= $iconMap[$activity['type']] ?? '📊' ?>
                                        <?= $labelMap[$activity['type']] ?? ucfirst(str_replace('_', ' ', $activity['type'])) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($opName) ?></td>
                                <td style="font-weight: 600; color: <?= $site['theme']['accent_color'] ?>;">
                                    $<?= number_format($activity['amount'], 2) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
