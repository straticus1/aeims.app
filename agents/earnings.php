<?php
/**
 * Operator Earnings Dashboard
 * Track earnings breakdown by service type with filters
 */

session_start();

if (!isset($_SESSION['operator_id'])) {
    header('Location: /agents/login.php');
    exit;
}

require_once '../services/OperatorManager.php';
require_once '../services/ActivityLogger.php';

try {
    $operatorManager = new \AEIMS\Services\OperatorManager();
    $activityLogger = new \AEIMS\Services\ActivityLogger();

    $operator = $operatorManager->getOperator($_SESSION['operator_id']);

    if (!$operator) {
        session_destroy();
        header('Location: /agents/login.php');
        exit;
    }

    // Get filter parameters
    $preset = $_GET['preset'] ?? 'monthly';
    $filterType = $_GET['type'] ?? '';
    $filterCustomer = $_GET['customer'] ?? '';
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

    // Get earnings data
    $earnings = $activityLogger->getOperatorEarnings(
        $_SESSION['operator_id'],
        $startDate,
        $endDate,
        $filterCustomer ?: null,
        $filterType ?: null
    );

    // Get operator domain-specific name
    $hostname = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $hostname = preg_replace('/^www\./', '', $hostname);
    $hostname = preg_replace('/:\d+$/', '', $hostname);
    $operatorDisplayName = $operator['profile']['display_names'][$hostname] ?? $operator['name'];

} catch (Exception $e) {
    error_log("Operator earnings error: " . $e->getMessage());
    $error = "An error occurred loading your earnings.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Earnings Dashboard</title>
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
            background: linear-gradient(45deg, #fbbf24, #f59e0b);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .filters-panel {
            background: rgba(0, 0, 0, 0.3);
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
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
            color: #fbbf24;
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
            border: 1px solid rgba(251, 191, 36, 0.3);
            border-radius: 20px;
            color: white;
            text-decoration: none;
            font-size: 0.85rem;
            transition: all 0.2s;
        }

        .preset-btn:hover,
        .preset-btn.active {
            background: linear-gradient(45deg, #fbbf24, #f59e0b);
            border-color: #fbbf24;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: rgba(0, 0, 0, 0.3);
            padding: 1.5rem;
            border-radius: 15px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .stat-label {
            font-size: 0.9rem;
            color: #9ca3af;
            margin-bottom: 0.5rem;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            background: linear-gradient(45deg, #fbbf24, #f59e0b);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .breakdown-section {
            background: rgba(0, 0, 0, 0.3);
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .breakdown-section h2 {
            color: #fbbf24;
            margin-bottom: 1.5rem;
        }

        .breakdown-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.05);
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
            background: linear-gradient(45deg, #667eea, #764ba2);
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
            color: #9ca3af;
        }

        .breakdown-amount {
            font-size: 1.5rem;
            font-weight: bold;
            color: #fbbf24;
        }

        .earnings-table {
            width: 100%;
            border-collapse: collapse;
        }

        .earnings-table th {
            background: rgba(251, 191, 36, 0.2);
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid rgba(251, 191, 36, 0.3);
        }

        .earnings-table td {
            padding: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .earnings-table tr:hover {
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
        .type-paid_operator_message { background: rgba(251, 191, 36, 0.2); color: #fbbf24; }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #9ca3af;
        }

        .earnings-summary {
            background: linear-gradient(45deg, rgba(251, 191, 36, 0.2), rgba(245, 158, 11, 0.2));
            border: 1px solid rgba(251, 191, 36, 0.5);
            padding: 1.5rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            font-size: 1.1rem;
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

    <main class="main-content">
        <div class="page-header">
            <h1>💰 Earnings Dashboard</h1>
            <p>Track your performance and earnings</p>
        </div>

        <form method="GET" class="filters-panel">
            <div class="filter-row">
                <div class="filter-group">
                    <label>Activity Type</label>
                    <select name="type">
                        <option value="">All Types</option>
                        <option value="message" <?= $filterType === 'message' ? 'selected' : '' ?>>Messages</option>
                        <option value="paid_operator_message" <?= $filterType === 'paid_operator_message' ? 'selected' : '' ?>>Paid Messages</option>
                        <option value="call" <?= $filterType === 'call' ? 'selected' : '' ?>>Calls</option>
                        <option value="video" <?= $filterType === 'video' ? 'selected' : '' ?>>Video</option>
                        <option value="cam" <?= $filterType === 'cam' ? 'selected' : '' ?>>Cam</option>
                        <option value="chat" <?= $filterType === 'chat' ? 'selected' : '' ?>>Chat</option>
                        <option value="toy_control" <?= $filterType === 'toy_control' ? 'selected' : '' ?>>Toy Control</option>
                        <option value="content" <?= $filterType === 'content' ? 'selected' : '' ?>>Content</option>
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

        <div class="earnings-summary">
            💰 You earned <strong>$<?= number_format($earnings['total_earnings'], 2) ?></strong>
            from <?= $earnings['count'] ?> transaction<?= $earnings['count'] !== 1 ? 's' : '' ?>
            (<?= date('M d', strtotime($startDate)) ?> - <?= date('M d, Y', strtotime($endDate)) ?>)
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Earnings</div>
                <div class="stat-value">$<?= number_format($earnings['total_earnings'], 2) ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Total Transactions</div>
                <div class="stat-value"><?= number_format($earnings['count']) ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Date Range</div>
                <div class="stat-value" style="font-size: 1.2rem;">
                    <?= date('M d', strtotime($startDate)) ?> - <?= date('M d, Y', strtotime($endDate)) ?>
                </div>
            </div>
        </div>

        <?php if (!empty($earnings['breakdown'])): ?>
            <div class="breakdown-section">
                <h2>Earnings Breakdown by Service</h2>

                <?php
                $iconMap = [
                    'message' => '💬',
                    'paid_operator_message' => '💰',
                    'call' => '📞',
                    'video' => '📹',
                    'cam' => '📷',
                    'chat' => '💭',
                    'toy_control' => '🎮',
                    'content' => '🎁'
                ];

                $labelMap = [
                    'message' => 'Messages',
                    'paid_operator_message' => 'Paid Messages',
                    'call' => 'Voice Calls',
                    'video' => 'Video Calls',
                    'cam' => 'Cam Shows',
                    'chat' => 'Chat Sessions',
                    'toy_control' => 'Toy Control',
                    'content' => 'Premium Content'
                ];
                ?>

                <?php foreach ($earnings['breakdown'] as $type => $data): ?>
                    <div class="breakdown-item">
                        <div class="breakdown-info">
                            <div class="breakdown-icon">
                                <?= $iconMap[$type] ?? '📊' ?>
                            </div>
                            <div class="breakdown-details">
                                <div class="breakdown-label"><?= $labelMap[$type] ?? ucfirst(str_replace('_', ' ', $type)) ?></div>
                                <div class="breakdown-count">
                                    <?= $data['count'] ?> transaction<?= $data['count'] !== 1 ? 's' : '' ?> •
                                    Revenue: $<?= number_format($data['total_revenue'], 2) ?>
                                </div>
                            </div>
                        </div>
                        <div class="breakdown-amount">
                            $<?= number_format($data['total_earnings'], 2) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($earnings['activities'])): ?>
            <div class="breakdown-section">
                <h2>Recent Transactions</h2>

                <table class="earnings-table">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Type</th>
                            <th>Revenue</th>
                            <th>Your Earnings</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice(array_reverse($earnings['activities']), 0, 50) as $activity): ?>
                            <tr>
                                <td><?= date('M d, Y g:i A', strtotime($activity['timestamp'])) ?></td>
                                <td>
                                    <span class="activity-type type-<?= $activity['type'] ?>">
                                        <?= $iconMap[$activity['type']] ?? '📊' ?>
                                        <?= $labelMap[$activity['type']] ?? ucfirst(str_replace('_', ' ', $activity['type'])) ?>
                                    </span>
                                </td>
                                <td style="font-weight: 500; color: #9ca3af;">
                                    $<?= number_format($activity['amount'], 2) ?>
                                </td>
                                <td style="font-weight: 600; color: #fbbf24;">
                                    $<?= number_format($activity['operator_earnings'], 2) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <h3>No Earnings Yet</h3>
                <p>Start engaging with customers to earn money!</p>
                <a href="/agents/operator-messages.php" class="btn btn-primary" style="margin-top: 1rem;">View Messages</a>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
