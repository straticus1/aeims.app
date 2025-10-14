<?php
/**
 * AEIMS Operator Profile Management
 * Allows operators to manage their profile, settings, and availability
 */

session_start();
require_once __DIR__ . '/../includes/SecurityManager.php';
require_once __DIR__ . '/includes/OperatorAuth.php';

$security = SecurityManager::getInstance();
$security->initializeSecureSession();
$security->applySecurityHeaders();

// Require operator authentication
if (!isset($_SESSION['operator_id'])) {
    header('Location: login.php');
    exit();
}

$operatorId = $_SESSION['operator_id'];
$message = '';
$messageType = '';

// Load operator data
$operatorsFile = __DIR__ . '/data/operators.json';
$operators = [];

if (file_exists($operatorsFile)) {
    $operatorsData = $security->safeJSONRead($operatorsFile);
    if ($operatorsData) {
        foreach ($operatorsData as $op) {
            if ($op['operator_id'] === $operatorId) {
                $operator = $op;
                break;
            }
        }
    }
}

if (!isset($operator)) {
    die('Operator not found');
}

// Handle profile updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verify_csrf();

    $action = $_POST['action'];

    if ($action === 'update_profile') {
        // Update basic profile info
        $operator['name'] = $security->sanitizeInput($_POST['name'] ?? $operator['name']);
        $operator['email'] = $security->sanitizeInput($_POST['email'] ?? $operator['email'], 'email');
        $operator['phone'] = $security->sanitizeInput($_POST['phone'] ?? $operator['phone']);
        $operator['bio'] = $security->sanitizeInput($_POST['bio'] ?? $operator['bio']);

        // Update profile in data file
        $operatorsData = $security->safeJSONRead($operatorsFile);
        if ($operatorsData) {
            foreach ($operatorsData as &$op) {
                if ($op['operator_id'] === $operatorId) {
                    $op = $operator;
                    break;
                }
            }
            if ($security->safeJSONWrite($operatorsFile, $operatorsData)) {
                $message = 'Profile updated successfully!';
                $messageType = 'success';
            } else {
                $message = 'Failed to update profile';
                $messageType = 'error';
            }
        }
    }
    elseif ($action === 'update_availability') {
        // Update availability status
        $operator['status'] = $security->sanitizeInput($_POST['status'] ?? 'offline');

        $operatorsData = $security->safeJSONRead($operatorsFile);
        if ($operatorsData) {
            foreach ($operatorsData as &$op) {
                if ($op['operator_id'] === $operatorId) {
                    $op['status'] = $operator['status'];
                    $op['last_status_change'] = date('Y-m-d H:i:s');
                    break;
                }
            }
            if ($security->safeJSONWrite($operatorsFile, $operatorsData)) {
                $message = 'Availability updated!';
                $messageType = 'success';
            }
        }
    }
    elseif ($action === 'update_password') {
        // Update password
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (password_verify($currentPassword, $operator['password'])) {
            if ($newPassword === $confirmPassword) {
                $validation = $security->validatePassword($newPassword);
                if ($validation['valid']) {
                    $operator['password'] = password_hash($newPassword, PASSWORD_DEFAULT);

                    $operatorsData = $security->safeJSONRead($operatorsFile);
                    if ($operatorsData) {
                        foreach ($operatorsData as &$op) {
                            if ($op['operator_id'] === $operatorId) {
                                $op['password'] = $operator['password'];
                                break;
                            }
                        }
                        if ($security->safeJSONWrite($operatorsFile, $operatorsData)) {
                            $message = 'Password updated successfully!';
                            $messageType = 'success';
                        }
                    }
                } else {
                    $message = 'Password not strong enough: ' . implode(', ', $validation['errors']);
                    $messageType = 'error';
                }
            } else {
                $message = 'New passwords do not match';
                $messageType = 'error';
            }
        } else {
            $message = 'Current password is incorrect';
            $messageType = 'error';
        }
    }
}

$config = include __DIR__ . '/../config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - AEIMS Operator Portal</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
        }
        .header {
            background: rgba(255, 255, 255, 0.95);
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .profile-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 20px;
        }
        .profile-card h2 {
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
        }
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-family: inherit;
        }
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        .btn {
            padding: 12px 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: transform 0.2s;
        }
        .btn:hover {
            transform: translateY(-2px);
        }
        .btn-secondary {
            background: #6c757d;
        }
        .message {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 600;
        }
        .status-online { background: #d4edda; color: #155724; }
        .status-busy { background: #fff3cd; color: #856404; }
        .status-offline { background: #f8d7da; color: #721c24; }
        .profile-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 30px;
        }
        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2.5rem;
            font-weight: bold;
        }
        .profile-info h1 {
            color: #333;
            margin-bottom: 5px;
        }
        .profile-info p {
            color: #666;
        }
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #ddd;
        }
        .tab {
            padding: 12px 24px;
            cursor: pointer;
            background: none;
            border: none;
            color: #666;
            font-weight: 600;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
        }
        .tab.active {
            color: #667eea;
            border-bottom-color: #667eea;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1 style="color: #333;">Operator Profile</h1>
            </div>
            <div>
                <a href="dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
            </div>
        </div>

        <?php if ($message): ?>
        <div class="message <?= $messageType ?>">
            <?= htmlspecialchars($message) ?>
        </div>
        <?php endif; ?>

        <div class="profile-card">
            <div class="profile-header">
                <div class="profile-avatar">
                    <?= strtoupper(substr($operator['name'], 0, 1)) ?>
                </div>
                <div class="profile-info">
                    <h1><?= htmlspecialchars($operator['name']) ?></h1>
                    <p><?= htmlspecialchars($operator['email']) ?></p>
                    <span class="status-badge status-<?= $operator['status'] ?>">
                        <?= ucfirst($operator['status']) ?>
                    </span>
                </div>
            </div>

            <div class="tabs">
                <button class="tab active" onclick="showTab('profile')">Profile Information</button>
                <button class="tab" onclick="showTab('availability')">Availability</button>
                <button class="tab" onclick="showTab('security')">Security</button>
            </div>

            <!-- Profile Tab -->
            <div id="profile" class="tab-content active">
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update_profile">

                    <div class="form-group">
                        <label for="name">Display Name *</label>
                        <input type="text" id="name" name="name" value="<?= htmlspecialchars($operator['name']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="email">Email Address *</label>
                        <input type="email" id="email" name="email" value="<?= htmlspecialchars($operator['email']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($operator['phone'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label for="bio">Bio</label>
                        <textarea id="bio" name="bio" placeholder="Tell customers about yourself..."><?= htmlspecialchars($operator['bio'] ?? '') ?></textarea>
                    </div>

                    <button type="submit" class="btn">Update Profile</button>
                </form>
            </div>

            <!-- Availability Tab -->
            <div id="availability" class="tab-content">
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update_availability">

                    <div class="form-group">
                        <label for="status">Current Status</label>
                        <select id="status" name="status">
                            <option value="online" <?= $operator['status'] === 'online' ? 'selected' : '' ?>>🟢 Online - Available for calls/chats</option>
                            <option value="busy" <?= $operator['status'] === 'busy' ? 'selected' : '' ?>>🟡 Busy - In a session</option>
                            <option value="away" <?= $operator['status'] === 'away' ? 'selected' : '' ?>>🟠 Away - Be right back</option>
                            <option value="offline" <?= $operator['status'] === 'offline' ? 'selected' : '' ?>>🔴 Offline - Not available</option>
                        </select>
                    </div>

                    <button type="submit" class="btn">Update Status</button>
                </form>
            </div>

            <!-- Security Tab -->
            <div id="security" class="tab-content">
                <h2>Change Password</h2>
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update_password">

                    <div class="form-group">
                        <label for="current_password">Current Password *</label>
                        <input type="password" id="current_password" name="current_password" required>
                    </div>

                    <div class="form-group">
                        <label for="new_password">New Password *</label>
                        <input type="password" id="new_password" name="new_password" required>
                        <small style="color: #666;">Must be at least 10 characters with uppercase, lowercase, number, and special character</small>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password *</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>

                    <button type="submit" class="btn">Change Password</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function showTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });

            // Show selected tab
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }
    </script>
</body>
</html>
