<?php
/**
 * AEIMS Analytics Dashboard
 * System usage and performance analytics
 */

session_start();
require_once 'auth_functions.php';
requireAdmin();

$config = include 'config.php';
$userInfo = getUserInfo();

// Generate sample analytics data
$analyticsData = [
    'daily_logins' => [12, 18, 15, 22, 19, 25, 30],
    'page_views' => [150, 220, 180, 310, 250, 400, 380],
    'system_performance' => [
        'cpu_usage' => 45,
        'memory_usage' => 62,
        'disk_usage' => 34,
        'network_io' => 78
    ],
    'user_activity' => [
        'active_users' => 15,
        'new_registrations' => 3,
        'support_tickets' => 2,
        'error_rate' => 0.1
    ]
];

// Handle logout
if (isset($_GET['logout'])) {
    logout();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics - AEIMS</title>
    <link href="assets/css/style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .analytics-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .analytics-header {
            background: linear-gradient(135deg, #17a2b8 0%, #6f42c1 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        .analytics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .analytics-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .metric-value {
            font-size: 2em;
            font-weight: bold;
            color: #17a2b8;
        }
        .metric-label {
            color: #6c757d;
            font-size: 0.9em;
        }
        .chart-container {
            position: relative;
            height: 300px;
            margin-top: 15px;
        }
        .performance-bar {
            background: #e9ecef;
            height: 20px;
            border-radius: 10px;
            overflow: hidden;
            margin: 10px 0;
        }
        .performance-fill {
            height: 100%;
            background: linear-gradient(90deg, #28a745, #ffc107, #dc3545);
            transition: width 0.3s ease;
        }
        .btn {
            background: #17a2b8;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 5px;
        }
        .btn:hover {
            background: #138496;
        }
        .btn-danger {
            background: #dc3545;
        }
        .btn-danger:hover {
            background: #c82333;
        }
    </style>
</head>
<body>
    <div class="analytics-container">
        <div class="analytics-header">
            <h1>Analytics Dashboard</h1>
            <p>System performance and usage analytics</p>
            <p>Real-time monitoring and insights</p>
        </div>

        <div class="analytics-grid">
            <div class="analytics-card">
                <h3>User Activity</h3>
                <div class="metric-value"><?= $analyticsData['user_activity']['active_users'] ?></div>
                <div class="metric-label">Active Users Today</div>
                <hr>
                <div>New Registrations: <?= $analyticsData['user_activity']['new_registrations'] ?></div>
                <div>Support Tickets: <?= $analyticsData['user_activity']['support_tickets'] ?></div>
                <div>Error Rate: <?= $analyticsData['user_activity']['error_rate'] ?>%</div>
            </div>

            <div class="analytics-card">
                <h3>System Performance</h3>
                <div>
                    <div>CPU Usage: <?= $analyticsData['system_performance']['cpu_usage'] ?>%</div>
                    <div class="performance-bar">
                        <div class="performance-fill" style="width: <?= $analyticsData['system_performance']['cpu_usage'] ?>%"></div>
                    </div>
                </div>
                <div>
                    <div>Memory Usage: <?= $analyticsData['system_performance']['memory_usage'] ?>%</div>
                    <div class="performance-bar">
                        <div class="performance-fill" style="width: <?= $analyticsData['system_performance']['memory_usage'] ?>%"></div>
                    </div>
                </div>
                <div>
                    <div>Disk Usage: <?= $analyticsData['system_performance']['disk_usage'] ?>%</div>
                    <div class="performance-bar">
                        <div class="performance-fill" style="width: <?= $analyticsData['system_performance']['disk_usage'] ?>%"></div>
                    </div>
                </div>
                <div>
                    <div>Network I/O: <?= $analyticsData['system_performance']['network_io'] ?>%</div>
                    <div class="performance-bar">
                        <div class="performance-fill" style="width: <?= $analyticsData['system_performance']['network_io'] ?>%"></div>
                    </div>
                </div>
            </div>

            <div class="analytics-card">
                <h3>Daily Logins</h3>
                <div class="chart-container">
                    <canvas id="loginChart"></canvas>
                </div>
            </div>

            <div class="analytics-card">
                <h3>Page Views</h3>
                <div class="chart-container">
                    <canvas id="pageViewChart"></canvas>
                </div>
            </div>
        </div>

        <div class="analytics-card">
            <h3>Weekly Trends</h3>
            <div class="chart-container">
                <canvas id="trendsChart"></canvas>
            </div>
        </div>

        <div style="margin-top: 30px; text-align: center;">
            <a href="admin-dashboard.php" class="btn">Back to Admin Dashboard</a>
            <a href="system-health.php" class="btn">System Health</a>
            <a href="?logout=1" class="btn btn-danger">Logout</a>
        </div>
    </div>

    <script>
        // Daily Logins Chart
        const loginCtx = document.getElementById('loginChart').getContext('2d');
        new Chart(loginCtx, {
            type: 'line',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                datasets: [{
                    label: 'Daily Logins',
                    data: <?= json_encode($analyticsData['daily_logins']) ?>,
                    borderColor: '#17a2b8',
                    backgroundColor: 'rgba(23, 162, 184, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });

        // Page Views Chart
        const pageViewCtx = document.getElementById('pageViewChart').getContext('2d');
        new Chart(pageViewCtx, {
            type: 'bar',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                datasets: [{
                    label: 'Page Views',
                    data: <?= json_encode($analyticsData['page_views']) ?>,
                    backgroundColor: '#6f42c1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });

        // Trends Chart
        const trendsCtx = document.getElementById('trendsChart').getContext('2d');
        new Chart(trendsCtx, {
            type: 'line',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                datasets: [{
                    label: 'Logins',
                    data: <?= json_encode($analyticsData['daily_logins']) ?>,
                    borderColor: '#17a2b8',
                    backgroundColor: 'rgba(23, 162, 184, 0.1)'
                }, {
                    label: 'Page Views',
                    data: <?= json_encode($analyticsData['page_views']) ?>,
                    borderColor: '#6f42c1',
                    backgroundColor: 'rgba(111, 66, 193, 0.1)'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html>