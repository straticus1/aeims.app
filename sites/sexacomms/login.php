<?php
/**
 * SexaComms.com - Admin Interface Portal
 * Administrative interface for AEIMS platform management
 */

// Check if user is accessing admin interface
$host = $_SERVER['HTTP_HOST'] ?? '';
if (strpos($host, 'sexacomms.com') === false) {
    header('Location: https://sexacomms.com' . $_SERVER['REQUEST_URI']);
    exit();
}

// Include common AEIMS functionality
require_once __DIR__ . '/../../includes/SiteSpecificAuth.php';
$auth = new SiteSpecificAuth();
$siteConfig = $auth->getSiteConfig();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']) && isset($_SESSION['user_type']);
$userType = $_SESSION['user_type'] ?? '';

// Admin interface requires admin or operator privileges
if ($isLoggedIn && !in_array($userType, ['admin', 'operator'])) {
    $isLoggedIn = false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SexaComms - AEIMS Administration</title>
    <meta name="description" content="Administrative interface for AEIMS platform management and operator access.">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="assets/admin.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="admin-interface">
    <div class="admin-container">
        <header class="admin-header">
            <div class="header-brand">
                <h1>SexaComms</h1>
                <span class="brand-subtitle">AEIMS Administration</span>
            </div>
            <?php if ($isLoggedIn): ?>
            <div class="header-user">
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['name'] ?? $_SESSION['username']); ?></span>
                <a href="../../logout.php" class="btn btn-outline">Logout</a>
            </div>
            <?php endif; ?>
        </header>

        <main class="admin-main">
            <?php if (!$isLoggedIn): ?>
            <!-- Login Form -->
            <div class="login-container">
                <div class="login-card">
                    <h2>Admin Login</h2>
                    <p>Access the AEIMS administration interface</p>

                    <form method="POST" action="../../login.php" class="login-form">
                        <input type="hidden" name="return_url" value="https://sexacomms.com<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>">

                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" id="username" name="username" required>
                        </div>

                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" id="password" name="password" required>
                        </div>

                        <button type="submit" class="btn btn-primary">Login</button>
                    </form>

                    <div class="quick-login-section">
                        <h4>Quick Login</h4>
                        <div class="quick-login-buttons">
                            <button onclick="quickLogin('admin', 'AEIMSAdmin2024!SecurePass')" class="btn btn-quick">Admin Login</button>
                            <button onclick="quickLogin('demo@example.com', 'demo123')" class="btn btn-quick">Demo Login</button>
                        </div>
                    </div>

                    <div class="login-footer">
                        <p>Need access? Contact your system administrator.</p>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <!-- Admin Dashboard -->
            <div class="dashboard-container">
                <h2>AEIMS Administration Dashboard</h2>

                <div class="dashboard-grid">
                    <div class="dashboard-card">
                        <h3>🏢 Site Management</h3>
                        <p>Manage customer sites, domains, and configurations</p>
                        <a href="sites.php" class="btn btn-primary">Manage Sites</a>
                    </div>

                    <div class="dashboard-card">
                        <h3>👥 User Management</h3>
                        <p>Manage operators, customers, and admin accounts</p>
                        <a href="users.php" class="btn btn-primary">Manage Users</a>
                    </div>

                    <div class="dashboard-card">
                        <h3>📞 Telephony Platform</h3>
                        <p>Access operator dashboard and call management</p>
                        <a href="../../operator-dashboard.php" class="btn btn-primary">Operator Dashboard</a>
                    </div>

                    <div class="dashboard-card">
                        <h3>💰 Billing & Revenue</h3>
                        <p>View revenue, payments, and billing information</p>
                        <a href="billing.php" class="btn btn-primary">View Billing</a>
                    </div>

                    <div class="dashboard-card">
                        <h3>📊 Analytics</h3>
                        <p>Platform statistics, usage metrics, and reports</p>
                        <a href="analytics.php" class="btn btn-primary">View Analytics</a>
                    </div>

                    <div class="dashboard-card">
                        <h3>⚙️ System Settings</h3>
                        <p>Platform configuration and system settings</p>
                        <a href="settings.php" class="btn btn-primary">System Settings</a>
                    </div>
                </div>

                <div class="quick-stats">
                    <h3>Quick Stats</h3>
                    <div class="stats-grid">
                        <div class="stat-item">
                            <span class="stat-number">4</span>
                            <span class="stat-label">Active Sites</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number">12</span>
                            <span class="stat-label">Active Operators</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number">150</span>
                            <span class="stat-label">Registered Customers</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number">99.8%</span>
                            <span class="stat-label">Platform Uptime</span>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <style>
        .admin-interface {
            background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);
            min-height: 100vh;
            font-family: 'Inter', sans-serif;
        }

        .admin-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .admin-header {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: white;
        }

        .header-brand h1 {
            margin: 0;
            font-size: 2rem;
            font-weight: 700;
        }

        .brand-subtitle {
            opacity: 0.8;
            font-size: 0.875rem;
        }

        .header-user {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .login-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 60vh;
        }

        .login-card {
            background: white;
            border-radius: 10px;
            padding: 2.5rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            max-width: 400px;
            width: 100%;
        }

        .login-card h2 {
            margin: 0 0 0.5rem 0;
            color: #1f2937;
            text-align: center;
        }

        .login-card p {
            color: #6b7280;
            text-align: center;
            margin-bottom: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #374151;
            font-weight: 500;
        }

        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 1rem;
        }

        .form-group input:focus {
            outline: none;
            border-color: #1e40af;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .dashboard-container {
            color: white;
        }

        .dashboard-container h2 {
            margin-bottom: 2rem;
            text-align: center;
            font-size: 2.5rem;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        .dashboard-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 10px;
            padding: 2rem;
            text-align: center;
        }

        .dashboard-card h3 {
            margin: 0 0 1rem 0;
            font-size: 1.5rem;
        }

        .dashboard-card p {
            margin-bottom: 1.5rem;
            opacity: 0.9;
        }

        .quick-stats {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 10px;
            padding: 2rem;
        }

        .quick-stats h3 {
            margin: 0 0 1.5rem 0;
            text-align: center;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            display: block;
            font-size: 2rem;
            font-weight: 700;
            color: #fbbf24;
        }

        .stat-label {
            display: block;
            font-size: 0.875rem;
            opacity: 0.8;
            margin-top: 0.25rem;
        }

        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-primary {
            background: #1e40af;
            color: white;
        }

        .btn-primary:hover {
            background: #1e3a8a;
            transform: translateY(-1px);
        }

        .btn-outline {
            background: transparent;
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        .btn-outline:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .login-footer {
            text-align: center;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #e5e7eb;
        }

        .login-footer p {
            color: #9ca3af;
            font-size: 0.875rem;
        }

        .quick-login-section {
            margin: 2rem 0;
            text-align: center;
            padding-top: 2rem;
            border-top: 1px solid #e5e7eb;
        }

        .quick-login-section h4 {
            margin-bottom: 1rem;
            color: #374151;
            font-weight: 600;
        }

        .quick-login-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn-quick {
            background: #059669;
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-quick:hover {
            background: #047857;
            transform: translateY(-1px);
        }
    </style>

    <script>
        function quickLogin(username, password) {
            const usernameField = document.getElementById('username');
            const passwordField = document.getElementById('password');
            const form = document.querySelector('.login-form');

            if (usernameField && passwordField && form) {
                usernameField.value = username;
                passwordField.value = password;
                form.submit();
            } else {
                console.error('Login form elements not found');
            }
        }
    </script>
</body>
</html>