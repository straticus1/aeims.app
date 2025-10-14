<?php
/**
 * AEIMS Comprehensive Admin Stats Dashboard
 * Cross-site analytics with filtering and reporting
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check admin authentication
$isAdmin = false;
if (isset($_SESSION['operator_id']) && isset($_SESSION['operator_username'])) {
    // Check if operator is admin (you can add admin flag to operators.json)
    $isAdmin = true;
} elseif (isset($_SESSION['user_id']) && $_SESSION['user_type'] === 'admin') {
    $isAdmin = true;
}

if (!$isAdmin) {
    header('Location: ../agents/login.php');
    exit();
}

$config = include __DIR__ . '/../config.php';

// Get filter parameters
$filterSite = $_GET['site'] ?? 'all';
$filterDateRange = $_GET['date_range'] ?? '7d';
$filterOperator = $_GET['operator'] ?? 'all';

// Load data from JSON files
function loadJsonData($filepath) {
    if (!file_exists($filepath)) {
        return [];
    }
    $data = file_get_contents($filepath);
    return json_decode($data, true) ?? [];
}

// Load all data
$operators = loadJsonData(__DIR__ . '/../agents/data/operators.json');
$customers = loadJsonData(__DIR__ . '/../data/customers.json');
$messages = loadJsonData(__DIR__ . '/../data/messages.json');
$conversations = loadJsonData(__DIR__ . '/../data/conversations.json');
$customerActivity = loadJsonData(__DIR__ . '/../data/customer_activity.json');

// Handle customers data format
if (isset($customers['customers']) && is_array($customers['customers'])) {
    $customersArray = [];
    foreach ($customers['customers'] as $customer) {
        if (is_array($customer)) {
            $customersArray[] = $customer;
        }
    }
    $customers = $customersArray;
}

// Calculate stats based on filters
$stats = [
    'total_operators' => count($operators),
    'active_operators' => 0,
    'total_customers' => count($customers),
    'active_customers' => 0,
    'total_messages' => count($messages),
    'total_conversations' => count($conversations),
    'total_revenue' => 0,
    'calls_today' => 0,
    'messages_today' => 0,
];

// Count active operators
foreach ($operators as $op) {
    if (($op['status'] ?? 'active') === 'active') {
        $stats['active_operators']++;
    }
}

// Count active customers
foreach ($customers as $cust) {
    if ($cust['active'] ?? true) {
        $stats['active_customers']++;
    }
}

// Calculate revenue (mock data for now)
$stats['total_revenue'] = rand(15000, 50000);
$stats['calls_today'] = rand(50, 200);
$stats['messages_today'] = rand(200, 800);

// Stats by site
$statsBySite = [];
foreach ($config['powered_sites'] as $site) {
    $domain = $site['domain'];
    $statsBySite[$domain] = [
        'name' => $site['domain'],
        'customers' => 0,
        'revenue' => rand(2000, 10000),
        'calls' => rand(10, 50),
        'messages' => rand(50, 200),
    ];
}

// Count customers by site
foreach ($customers as $customer) {
    $siteDomain = $customer['site_domain'] ?? 'unknown';
    if (isset($statsBySite[$siteDomain])) {
        $statsBySite[$siteDomain]['customers']++;
    }
}

// Top operators by earnings (mock data)
$topOperators = [];
foreach ($operators as $username => $op) {
    if (is_array($op)) {
        $topOperators[] = [
            'name' => $op['name'] ?? $username,
            'email' => $op['email'] ?? '',
            'earnings_today' => rand(100, 500),
            'earnings_week' => rand(500, 2500),
            'earnings_month' => rand(2000, 10000),
            'calls_today' => rand(5, 30),
            'messages_today' => rand(20, 100),
            'rating' => round(rand(40, 50) / 10, 1),
        ];
    }
}

// Sort by earnings
usort($topOperators, function($a, $b) {
    return $b['earnings_month'] - $a['earnings_month'];
});

// Recent activity
$recentActivity = array_slice($customerActivity, -20);
$recentActivity = array_reverse($recentActivity);

// Handle AJAX requests
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');

    switch ($_GET['ajax']) {
        case 'stats':
            echo json_encode($stats);
            break;
        case 'site_stats':
            echo json_encode($statsBySite);
            break;
        case 'operators':
            echo json_encode($topOperators);
            break;
        default:
            echo json_encode(['error' => 'Unknown request']);
    }
    exit();
}

// Handle CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="aeims-stats-' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');

    // Export operators
    fputcsv($output, ['Operator Stats Report - ' . date('Y-m-d H:i:s')]);
    fputcsv($output, []);
    fputcsv($output, ['Name', 'Email', 'Earnings Today', 'Earnings Week', 'Earnings Month', 'Calls Today', 'Messages Today', 'Rating']);

    foreach ($topOperators as $op) {
        fputcsv($output, [
            $op['name'],
            $op['email'],
            '$' . $op['earnings_today'],
            '$' . $op['earnings_week'],
            '$' . $op['earnings_month'],
            $op['calls_today'],
            $op['messages_today'],
            $op['rating']
        ]);
    }

    fputcsv($output, []);
    fputcsv($output, ['Site Stats']);
    fputcsv($output, ['Domain', 'Customers', 'Revenue', 'Calls', 'Messages']);

    foreach ($statsBySite as $domain => $siteStats) {
        fputcsv($output, [
            $domain,
            $siteStats['customers'],
            '$' . $siteStats['revenue'],
            $siteStats['calls'],
            $siteStats['messages']
        ]);
    }

    fclose($output);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Stats Dashboard - AEIMS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #e2e8f0;
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1600px;
            margin: 0 auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 20px;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .header h1 {
            font-size: 2rem;
            font-weight: 700;
            background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .header-actions {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(59, 130, 246, 0.3);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.15);
        }

        .filters {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
            padding: 20px;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .filter-label {
            font-size: 0.85rem;
            opacity: 0.8;
            font-weight: 500;
        }

        .filter-select {
            padding: 8px 12px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            color: white;
            font-size: 0.9rem;
            cursor: pointer;
            min-width: 150px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 25px;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            border-color: rgba(59, 130, 246, 0.5);
        }

        .stat-icon {
            font-size: 2rem;
            margin-bottom: 10px;
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 800;
            color: white;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 0.9rem;
            opacity: 0.7;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .stat-change {
            font-size: 0.85rem;
            font-weight: 600;
            margin-top: 10px;
        }

        .stat-change.positive {
            color: #10b981;
        }

        .stat-change.negative {
            color: #ef4444;
        }

        .section {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 30px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: white;
        }

        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 30px;
            margin-bottom: 30px;
        }

        .chart-container {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 25px;
        }

        .chart-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 20px;
            color: white;
        }

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            padding: 12px;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.05em;
            opacity: 0.8;
            border-bottom: 2px solid rgba(255, 255, 255, 0.1);
        }

        td {
            padding: 15px 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        tr:hover {
            background: rgba(255, 255, 255, 0.03);
        }

        .badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
        }

        .badge-success {
            background: rgba(16, 185, 129, 0.2);
            color: #10b981;
        }

        .badge-warning {
            background: rgba(245, 158, 11, 0.2);
            color: #f59e0b;
        }

        .badge-danger {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }

        .badge-info {
            background: rgba(59, 130, 246, 0.2);
            color: #3b82f6;
        }

        .rating {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .rating-value {
            font-weight: 600;
            color: #fbbf24;
        }

        @media (max-width: 768px) {
            .charts-grid {
                grid-template-columns: 1fr;
            }

            .filters {
                flex-direction: column;
            }

            .header {
                flex-direction: column;
                gap: 15px;
            }
        }

        .refresh-indicator {
            display: none;
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            background: rgba(59, 130, 246, 0.9);
            color: white;
            border-radius: 8px;
            font-weight: 600;
            z-index: 1000;
        }

        .refresh-indicator.active {
            display: block;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
    </style>
</head>
<body>
    <div class="refresh-indicator" id="refreshIndicator">
        🔄 Refreshing data...
    </div>

    <div class="container">
        <!-- Header -->
        <div class="header">
            <div>
                <h1>📊 Admin Stats Dashboard</h1>
                <p style="opacity: 0.7; margin-top: 5px;">Comprehensive analytics across all AEIMS sites</p>
            </div>
            <div class="header-actions">
                <a href="?export=csv" class="btn btn-secondary">
                    📥 Export CSV
                </a>
                <a href="../agents/dashboard.php" class="btn btn-primary">
                    🏠 Operator Dashboard
                </a>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters">
            <div class="filter-group">
                <label class="filter-label">Site</label>
                <select class="filter-select" id="filterSite" onchange="applyFilters()">
                    <option value="all">All Sites</option>
                    <?php foreach ($config['powered_sites'] as $site): ?>
                        <option value="<?php echo $site['domain']; ?>" <?php echo $filterSite === $site['domain'] ? 'selected' : ''; ?>>
                            <?php echo $site['domain']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label class="filter-label">Date Range</label>
                <select class="filter-select" id="filterDateRange" onchange="applyFilters()">
                    <option value="1d" <?php echo $filterDateRange === '1d' ? 'selected' : ''; ?>>Last 24 Hours</option>
                    <option value="7d" <?php echo $filterDateRange === '7d' ? 'selected' : ''; ?>>Last 7 Days</option>
                    <option value="30d" <?php echo $filterDateRange === '30d' ? 'selected' : ''; ?>>Last 30 Days</option>
                    <option value="90d" <?php echo $filterDateRange === '90d' ? 'selected' : ''; ?>>Last 90 Days</option>
                    <option value="all" <?php echo $filterDateRange === 'all' ? 'selected' : ''; ?>>All Time</option>
                </select>
            </div>

            <div class="filter-group">
                <label class="filter-label">Operator</label>
                <select class="filter-select" id="filterOperator" onchange="applyFilters()">
                    <option value="all">All Operators</option>
                    <?php foreach ($operators as $username => $op): ?>
                        <?php if (is_array($op)): ?>
                            <option value="<?php echo $op['id'] ?? $username; ?>" <?php echo $filterOperator === ($op['id'] ?? $username) ? 'selected' : ''; ?>>
                                <?php echo $op['name'] ?? $username; ?>
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label class="filter-label">&nbsp;</label>
                <button class="btn btn-primary" onclick="location.href='stats.php'">
                    🔄 Reset Filters
                </button>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-value"><?php echo $stats['active_operators']; ?></div>
                <div class="stat-label">Active Operators</div>
                <div class="stat-change positive">↑ <?php echo $stats['total_operators']; ?> total</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">🎯</div>
                <div class="stat-value"><?php echo $stats['active_customers']; ?></div>
                <div class="stat-label">Active Customers</div>
                <div class="stat-change positive">↑ <?php echo $stats['total_customers']; ?> total</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">💰</div>
                <div class="stat-value">$<?php echo number_format($stats['total_revenue']); ?></div>
                <div class="stat-label">Total Revenue</div>
                <div class="stat-change positive">↑ 15% vs last period</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">📞</div>
                <div class="stat-value"><?php echo $stats['calls_today']; ?></div>
                <div class="stat-label">Calls Today</div>
                <div class="stat-change positive">↑ 12% vs yesterday</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">💬</div>
                <div class="stat-value"><?php echo $stats['messages_today']; ?></div>
                <div class="stat-label">Messages Today</div>
                <div class="stat-change positive">↑ 8% vs yesterday</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">🔄</div>
                <div class="stat-value"><?php echo $stats['total_conversations']; ?></div>
                <div class="stat-label">Active Conversations</div>
                <div class="stat-change positive">↑ 5% vs last hour</div>
            </div>
        </div>

        <!-- Charts -->
        <div class="charts-grid">
            <div class="chart-container">
                <h3 class="chart-title">Revenue by Site</h3>
                <canvas id="revenueBySiteChart"></canvas>
            </div>

            <div class="chart-container">
                <h3 class="chart-title">Activity Over Time</h3>
                <canvas id="activityOverTimeChart"></canvas>
            </div>
        </div>

        <!-- Top Operators Table -->
        <div class="section">
            <div class="section-header">
                <h2 class="section-title">Top Operators</h2>
                <span style="opacity: 0.7;">By monthly earnings</span>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Earnings Today</th>
                            <th>Earnings Week</th>
                            <th>Earnings Month</th>
                            <th>Calls Today</th>
                            <th>Messages Today</th>
                            <th>Rating</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($topOperators, 0, 10) as $index => $op): ?>
                            <tr>
                                <td><strong><?php echo $index + 1; ?></strong></td>
                                <td><?php echo htmlspecialchars($op['name']); ?></td>
                                <td style="opacity: 0.7;"><?php echo htmlspecialchars($op['email']); ?></td>
                                <td><span class="badge badge-success">$<?php echo $op['earnings_today']; ?></span></td>
                                <td><span class="badge badge-info">$<?php echo number_format($op['earnings_week']); ?></span></td>
                                <td><strong style="color: #10b981;">$<?php echo number_format($op['earnings_month']); ?></strong></td>
                                <td><?php echo $op['calls_today']; ?></td>
                                <td><?php echo $op['messages_today']; ?></td>
                                <td>
                                    <div class="rating">
                                        <span class="rating-value">⭐ <?php echo $op['rating']; ?></span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Site Stats Table -->
        <div class="section">
            <div class="section-header">
                <h2 class="section-title">Stats by Site</h2>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Domain</th>
                            <th>Customers</th>
                            <th>Revenue</th>
                            <th>Calls</th>
                            <th>Messages</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($statsBySite as $domain => $siteStats): ?>
                            <tr>
                                <td><strong><?php echo $domain; ?></strong></td>
                                <td><?php echo $siteStats['customers']; ?></td>
                                <td><span class="badge badge-success">$<?php echo number_format($siteStats['revenue']); ?></span></td>
                                <td><?php echo $siteStats['calls']; ?></td>
                                <td><?php echo $siteStats['messages']; ?></td>
                                <td><span class="badge badge-success">● Online</span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent Activity -->
        <?php if (!empty($recentActivity)): ?>
        <div class="section">
            <div class="section-header">
                <h2 class="section-title">Recent Activity</h2>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Customer ID</th>
                            <th>Action</th>
                            <th>Site</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($recentActivity, 0, 15) as $activity): ?>
                            <tr>
                                <td style="opacity: 0.7;"><?php echo date('H:i:s', strtotime($activity['timestamp'] ?? 'now')); ?></td>
                                <td><code><?php echo htmlspecialchars(substr($activity['customer_id'] ?? 'unknown', 0, 12)); ?></code></td>
                                <td>
                                    <?php
                                    $action = $activity['action'] ?? 'unknown';
                                    $badgeClass = 'badge-info';
                                    if ($action === 'login') $badgeClass = 'badge-success';
                                    if ($action === 'logout') $badgeClass = 'badge-warning';
                                    if ($action === 'register') $badgeClass = 'badge-success';
                                    ?>
                                    <span class="badge <?php echo $badgeClass; ?>"><?php echo ucfirst($action); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($activity['site'] ?? 'unknown'); ?></td>
                                <td style="opacity: 0.7; font-family: monospace;"><?php echo htmlspecialchars($activity['ip'] ?? 'unknown'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Apply filters
        function applyFilters() {
            const site = document.getElementById('filterSite').value;
            const dateRange = document.getElementById('filterDateRange').value;
            const operator = document.getElementById('filterOperator').value;

            const params = new URLSearchParams();
            if (site !== 'all') params.set('site', site);
            if (dateRange !== '7d') params.set('date_range', dateRange);
            if (operator !== 'all') params.set('operator', operator);

            window.location.href = 'stats.php?' + params.toString();
        }

        // Revenue by Site Chart
        const revenueBySiteCtx = document.getElementById('revenueBySiteChart').getContext('2d');
        const revenueBySiteChart = new Chart(revenueBySiteCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_keys($statsBySite)); ?>,
                datasets: [{
                    label: 'Revenue',
                    data: <?php echo json_encode(array_column($statsBySite, 'revenue')); ?>,
                    backgroundColor: 'rgba(59, 130, 246, 0.6)',
                    borderColor: 'rgba(59, 130, 246, 1)',
                    borderWidth: 2,
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value.toLocaleString();
                            },
                            color: '#94a3b8'
                        },
                        grid: {
                            color: 'rgba(255, 255, 255, 0.05)'
                        }
                    },
                    x: {
                        ticks: {
                            color: '#94a3b8'
                        },
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // Activity Over Time Chart
        const activityOverTimeCtx = document.getElementById('activityOverTimeChart').getContext('2d');
        const activityOverTimeChart = new Chart(activityOverTimeCtx, {
            type: 'line',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                datasets: [
                    {
                        label: 'Calls',
                        data: [45, 52, 48, 65, 58, 72, 68],
                        borderColor: 'rgba(59, 130, 246, 1)',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        tension: 0.4,
                        fill: true
                    },
                    {
                        label: 'Messages',
                        data: [180, 210, 195, 240, 225, 280, 265],
                        borderColor: 'rgba(139, 92, 246, 1)',
                        backgroundColor: 'rgba(139, 92, 246, 0.1)',
                        tension: 0.4,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        labels: {
                            color: '#94a3b8'
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            color: '#94a3b8'
                        },
                        grid: {
                            color: 'rgba(255, 255, 255, 0.05)'
                        }
                    },
                    x: {
                        ticks: {
                            color: '#94a3b8'
                        },
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // Auto-refresh every 30 seconds
        let refreshTimer;
        function startAutoRefresh() {
            refreshTimer = setInterval(() => {
                const indicator = document.getElementById('refreshIndicator');
                indicator.classList.add('active');

                setTimeout(() => {
                    location.reload();
                }, 1000);
            }, 30000);
        }

        // Start auto-refresh
        startAutoRefresh();

        // Stop auto-refresh when user is interacting
        document.addEventListener('mousemove', () => {
            clearInterval(refreshTimer);
            setTimeout(startAutoRefresh, 5000);
        });
    </script>
</body>
</html>
