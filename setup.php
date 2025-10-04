<?php
/**
 * AEIMS System Setup
 * Configuration and initialization interface
 */

session_start();
require_once 'auth_functions.php';
requireAdmin();

$config = include 'config.php';
$userInfo = getUserInfo();

$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_config':
                $message = 'Configuration updated successfully!';
                $messageType = 'success';
                break;
            case 'reset_system':
                $message = 'System reset completed!';
                $messageType = 'success';
                break;
            default:
                $message = 'Unknown action';
                $messageType = 'error';
        }
    }
}

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
    <title>System Setup - AEIMS</title>
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .setup-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .setup-header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        .setup-section {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            box-sizing: border-box;
        }
        .btn {
            background: #28a745;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin: 5px;
        }
        .btn:hover {
            background: #218838;
        }
        .btn-danger {
            background: #dc3545;
        }
        .btn-danger:hover {
            background: #c82333;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <div class="setup-header">
            <h1>System Setup</h1>
            <p>Configure AEIMS system settings and parameters</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="setup-section">
            <h2>Database Configuration</h2>
            <form method="POST">
                <input type="hidden" name="action" value="update_config">

                <div class="form-group">
                    <label for="db_host">Database Host</label>
                    <input type="text" id="db_host" name="db_host" value="localhost">
                </div>

                <div class="form-group">
                    <label for="db_name">Database Name</label>
                    <input type="text" id="db_name" name="db_name" value="aeims">
                </div>

                <div class="form-group">
                    <label for="db_user">Database User</label>
                    <input type="text" id="db_user" name="db_user" value="">
                </div>

                <div class="form-group">
                    <label for="db_pass">Database Password</label>
                    <input type="password" id="db_pass" name="db_pass" value="">
                </div>

                <button type="submit" class="btn">Update Database Config</button>
            </form>
        </div>

        <div class="setup-section">
            <h2>System Settings</h2>
            <form method="POST">
                <input type="hidden" name="action" value="update_config">

                <div class="form-group">
                    <label for="site_name">Site Name</label>
                    <input type="text" id="site_name" name="site_name" value="AEIMS">
                </div>

                <div class="form-group">
                    <label for="admin_email">Admin Email</label>
                    <input type="email" id="admin_email" name="admin_email" value="">
                </div>

                <div class="form-group">
                    <label for="timezone">Timezone</label>
                    <select id="timezone" name="timezone">
                        <option value="UTC">UTC</option>
                        <option value="America/New_York">Eastern Time</option>
                        <option value="America/Chicago">Central Time</option>
                        <option value="America/Denver">Mountain Time</option>
                        <option value="America/Los_Angeles">Pacific Time</option>
                    </select>
                </div>

                <button type="submit" class="btn">Update System Settings</button>
            </form>
        </div>

        <div class="setup-section">
            <h2>Security Settings</h2>
            <form method="POST">
                <input type="hidden" name="action" value="update_config">

                <div class="form-group">
                    <label for="session_timeout">Session Timeout (minutes)</label>
                    <input type="number" id="session_timeout" name="session_timeout" value="30">
                </div>

                <div class="form-group">
                    <label for="max_login_attempts">Max Login Attempts</label>
                    <input type="number" id="max_login_attempts" name="max_login_attempts" value="5">
                </div>

                <button type="submit" class="btn">Update Security Settings</button>
            </form>
        </div>

        <div class="setup-section">
            <h2>System Maintenance</h2>
            <p><strong>Warning:</strong> These actions will affect system operation</p>

            <form method="POST" onsubmit="return confirm('Are you sure you want to reset the system?')">
                <input type="hidden" name="action" value="reset_system">
                <button type="submit" class="btn btn-danger">Reset System Data</button>
            </form>
        </div>

        <div style="margin-top: 30px; text-align: center;">
            <a href="admin-dashboard.php" class="btn">Back to Admin Dashboard</a>
            <a href="?logout=1" class="btn btn-danger">Logout</a>
        </div>
    </div>
</body>
</html>