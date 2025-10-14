<?php
/**
 * Customer Profile Management Page
 * Edit personal info, change password, manage preferences
 */

session_start();

if (!isset($_SESSION['customer_id'])) {
    header('Location: /');
    exit;
}

require_once 'services/SiteManager.php';
require_once 'services/CustomerManager.php';

try {
    $siteManager = new \AEIMS\Services\SiteManager();
    $customerManager = new \AEIMS\Services\CustomerManager();

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

    // Handle form submissions
    $success = null;
    $error = null;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['update_profile'])) {
            // Update profile logic would go here
            $success = "Profile updated successfully!";
        } elseif (isset($_POST['change_password'])) {
            // Change password logic would go here
            $success = "Password changed successfully!";
        }
    }

} catch (Exception $e) {
    error_log("Profile error: " . $e->getMessage());
    $error = "An error occurred. Please try again.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - <?= htmlspecialchars($site['name']) ?></title>
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
            max-width: 900px;
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

        .profile-section {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            border: 1px solid rgba(239, 68, 68, 0.2);
            backdrop-filter: blur(10px);
        }

        .profile-section h2 {
            color: <?= $site['theme']['primary_color'] ?>;
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #ffffff;
            font-weight: 500;
        }

        .form-group input {
            width: 100%;
            padding: 1rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            background: rgba(0, 0, 0, 0.3);
            color: #ffffff;
            font-size: 1rem;
        }

        .form-group input:focus {
            outline: none;
            border-color: <?= $site['theme']['primary_color'] ?>;
            box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.2);
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 1rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            color: #ffffff;
        }

        .info-label {
            font-weight: 600;
            color: <?= $site['theme']['primary_color'] ?>;
        }

        .alert {
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: 10px;
            font-weight: 500;
        }

        .alert-success {
            background: rgba(34, 197, 94, 0.2);
            border: 1px solid rgba(34, 197, 94, 0.5);
            color: #86efac;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.2);
            border: 1px solid rgba(239, 68, 68, 0.5);
            color: #fca5a5;
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
                <a href="/favorites.php" class="btn btn-secondary">Favorites</a>
                <a href="/logout.php" class="btn btn-secondary">Logout</a>
            </div>
        </div>
    </header>

    <main class="main-content">
        <div class="page-header">
            <h1>My Profile</h1>
            <p>Manage your account settings and preferences</p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="profile-section">
            <h2>Account Information</h2>
            <div class="info-row">
                <span class="info-label">Username:</span>
                <span><?= htmlspecialchars($customer['username']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Email:</span>
                <span><?= htmlspecialchars($customer['email']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Customer ID:</span>
                <span><?= htmlspecialchars($customer['customer_id']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Member Since:</span>
                <span><?= date('F j, Y', strtotime($customer['created_at'])) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Account Status:</span>
                <span style="color: #10b981; font-weight: 600;">Active</span>
            </div>
        </div>

        <div class="profile-section">
            <h2>Billing Information</h2>
            <div class="info-row">
                <span class="info-label">Current Credits:</span>
                <span style="color: <?= $site['theme']['primary_color'] ?>; font-weight: 600; font-size: 1.2rem;">
                    $<?= number_format($customer['billing']['credits'], 2) ?>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">Payment Method:</span>
                <span><?= $customer['billing']['payment_method'] ? htmlspecialchars($customer['billing']['payment_method']) : 'None on file' ?></span>
            </div>
            <div style="margin-top: 1.5rem;">
                <a href="/payment.php" class="btn btn-primary">Add Credits</a>
            </div>
        </div>

        <div class="profile-section">
            <h2>Update Profile</h2>
            <form method="POST">
                <div class="form-group">
                    <label>Display Name</label>
                    <input type="text" name="display_name" value="<?= htmlspecialchars($customer['username']) ?>">
                </div>
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($customer['email']) ?>">
                </div>
                <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
            </form>
        </div>

        <div class="profile-section">
            <h2>Change Password</h2>
            <form method="POST">
                <div class="form-group">
                    <label>Current Password</label>
                    <input type="password" name="current_password" required>
                </div>
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="new_password" required>
                </div>
                <div class="form-group">
                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_password" required>
                </div>
                <button type="submit" name="change_password" class="btn btn-primary">Change Password</button>
            </form>
        </div>

        <div class="profile-section">
            <h2>Preferences</h2>
            <div class="info-row">
                <span class="info-label">Email Notifications:</span>
                <span>Enabled</span>
            </div>
            <div class="info-row">
                <span class="info-label">SMS Notifications:</span>
                <span>Disabled</span>
            </div>
            <div class="info-row">
                <span class="info-label">Privacy Level:</span>
                <span>Maximum</span>
            </div>
        </div>

        <div class="profile-section" style="border-color: rgba(239, 68, 68, 0.5);">
            <h2 style="color: #ef4444;">Danger Zone</h2>
            <p style="margin-bottom: 1rem; color: #ffffff;">Once you delete your account, there is no going back. Please be certain.</p>
            <button class="btn btn-secondary" style="border-color: #ef4444; color: #ef4444;">Delete Account</button>
        </div>
    </main>
</body>
</html>
