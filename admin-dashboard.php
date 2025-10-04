<?php
/**
 * AEIMS Admin Dashboard
 * Administrative interface for system management
 */

session_start();
require_once 'auth_functions.php';
requireAdmin();

$config = include 'config.php';
$userInfo = getUserInfo();

// Get system stats
$systemStats = [
    'total_users' => count(json_decode(file_get_contents(__DIR__ . '/data/accounts.json'), true) ?? []),
    'active_sessions' => 1, // Current user
    'system_status' => 'operational',
    'uptime' => '99.9%'
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
    <title>Admin Dashboard - AEIMS</title>
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .admin-dashboard {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .admin-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid #667eea;
        }
        .admin-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        .action-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .action-btn {
            background: #667eea;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 5px;
        }
        .action-btn:hover {
            background: #5a6fd8;
        }
    </style>
</head>
<body>
    <div class="admin-dashboard">
        <div class="admin-header">
            <h1>Admin Dashboard</h1>
            <p>Welcome back, <?= htmlspecialchars($userInfo['name']) ?>!</p>
            <p>Administrator Control Panel</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Users</h3>
                <div style="font-size: 2em; color: #667eea;"><?= $systemStats['total_users'] ?></div>
            </div>
            <div class="stat-card">
                <h3>Active Sessions</h3>
                <div style="font-size: 2em; color: #28a745;"><?= $systemStats['active_sessions'] ?></div>
            </div>
            <div class="stat-card">
                <h3>System Status</h3>
                <div style="font-size: 1.5em; color: #28a745;"><?= ucfirst($systemStats['system_status']) ?></div>
            </div>
            <div class="stat-card">
                <h3>Uptime</h3>
                <div style="font-size: 2em; color: #17a2b8;"><?= $systemStats['uptime'] ?></div>
            </div>
        </div>

        <div class="admin-actions">
            <div class="action-card">
                <h3>User Management</h3>
                <p>Manage user accounts and permissions</p>
                <a href="admin.php" class="action-btn">Manage Users</a>
                <a href="analytics.php" class="action-btn">View Analytics</a>
            </div>

            <div class="action-card">
                <h3>System Configuration</h3>
                <p>Configure system settings and parameters</p>
                <a href="setup.php" class="action-btn">System Setup</a>
                <a href="system-health.php" class="action-btn">System Health</a>
            </div>

            <div class="action-card">
                <h3>Support & Monitoring</h3>
                <p>Monitor system performance and support</p>
                <a href="support.php" class="action-btn">Support Center</a>
                <a href="dashboard.php" class="action-btn">User Dashboard</a>
                <a href="auth.php" class="action-btn">🔐 Authentication Status</a>
            </div>

            <div class="action-card">
                <h3>Operator Verification</h3>
                <p>Manage operator identity verification and revalidation</p>
                <a href="operator-verification-admin.php" class="action-btn">Verification Status</a>
                <button onclick="runRevalidationCheck()" class="action-btn" style="background: #28a745;">Run Revalidation Check</button>
            </div>
        </div>

        <div style="margin-top: 30px; text-align: center;">
            <a href="?logout=1" class="action-btn" style="background: #dc3545;">Logout</a>
        </div>
    </div>

    <script>
        function runRevalidationCheck() {
            if (confirm('Run revalidation check for all operators? This will send notifications to operators with expired or expiring verifications.')) {
                const button = event.target;
                button.innerHTML = 'Running...';
                button.disabled = true;

                fetch('revalidation-checker.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'run_check=1'
                })
                .then(response => response.json())
                .then(data => {
                    let message = `Revalidation check completed:\n`;
                    message += `- Total operators checked: ${data.summary.total_operators_checked}\n`;
                    message += `- Expired verifications: ${data.summary.expired_verifications}\n`;
                    message += `- Expiring soon: ${data.summary.expiring_soon}\n`;
                    message += `- Notifications sent: ${data.summary.notifications_sent}`;

                    alert(message);
                    button.innerHTML = 'Run Revalidation Check';
                    button.disabled = false;
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error running revalidation check. Please try again.');
                    button.innerHTML = 'Run Revalidation Check';
                    button.disabled = false;
                });
            }
        }
    </script>
</body>
</html>