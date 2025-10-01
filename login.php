<?php
session_start();

// Check if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

$config = include 'config.php';
$error_message = '';

// Handle login submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error_message = 'Please enter both username and password.';
    } else {
        // Load user accounts
        $accounts = loadUserAccounts();

        if (validateLogin($username, $password, $accounts)) {
            $user = $accounts[$username];

            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $username;
            $_SESSION['user_type'] = $user['type'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['login_time'] = time();

            // Redirect based on user type
            if ($user['type'] === 'admin') {
                header('Location: admin-dashboard.php');
            } else {
                header('Location: dashboard.php');
            }
            exit();
        } else {
            $error_message = 'Invalid username or password.';

            // Log failed attempt
            logLoginAttempt($username, false);
        }
    }
}

/**
 * Load user accounts from secure file
 */
function loadUserAccounts() {
    $accountsFile = __DIR__ . '/data/accounts.json';

    // Create default accounts if file doesn't exist
    if (!file_exists($accountsFile)) {
        $defaultAccounts = [
            // Admin account
            'admin' => [
                'id' => 'admin-001',
                'name' => 'AEIMS Administrator',
                'type' => 'admin',
                'password' => password_hash('AEIMSAdmin2024!SecurePass', PASSWORD_DEFAULT),
                'email' => 'admin@aeims.app',
                'created_at' => date('c'),
                'permissions' => ['all']
            ],
            // Sample customer accounts
            'demo@example.com' => [
                'id' => 'cust-001',
                'name' => 'Demo Customer',
                'type' => 'customer',
                'password' => password_hash('demo123', PASSWORD_DEFAULT),
                'email' => 'demo@example.com',
                'domains' => ['demo.example.com'],
                'status' => 'active',
                'created_at' => date('c'),
                'permissions' => ['dashboard', 'domains', 'stats', 'support']
            ]
        ];

        $dataDir = dirname($accountsFile);
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }

        file_put_contents($accountsFile, json_encode($defaultAccounts, JSON_PRETTY_PRINT));
        return $defaultAccounts;
    }

    return json_decode(file_get_contents($accountsFile), true) ?? [];
}

/**
 * Validate login credentials
 */
function validateLogin($username, $password, $accounts) {
    if (!isset($accounts[$username])) {
        return false;
    }

    $user = $accounts[$username];

    // Check if customer account is active
    if ($user['type'] === 'customer' && ($user['status'] ?? 'active') !== 'active') {
        return false;
    }

    return password_verify($password, $user['password']);
}

/**
 * Log login attempt
 */
function logLoginAttempt($username, $success) {
    $logEntry = [
        'timestamp' => date('c'),
        'username' => $username,
        'success' => $success,
        'ip' => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
    ];

    $logFile = __DIR__ . '/logs/login-attempts.log';
    $logDir = dirname($logFile);

    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    file_put_contents($logFile, json_encode($logEntry) . "\n", FILE_APPEND | LOCK_EX);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Login - <?php echo $config['site']['name']; ?> | <?php echo $config['site']['company']; ?></title>
    <meta name="description" content="Secure customer login for AEIMS platform management. Access your domains, view statistics, and manage your adult entertainment platform.">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/login.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="login-body">
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <a href="index.php" class="logo-link">
                    <h1 class="login-logo"><?php echo $config['site']['name']; ?></h1>
                    <span class="login-subtitle"><?php echo $config['site']['company']; ?></span>
                </a>
                <h2>Customer Login</h2>
                <p>Access your AEIMS management dashboard</p>
            </div>

            <?php if ($error_message): ?>
            <div class="error-message">
                <span class="error-icon">⚠️</span>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
            <?php endif; ?>

            <form class="login-form" method="POST" action="">
                <div class="form-group">
                    <label for="username">Email or Username</label>
                    <input
                        type="text"
                        id="username"
                        name="username"
                        required
                        value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                        placeholder="Enter your email or username"
                        autocomplete="username"
                    >
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        required
                        placeholder="Enter your password"
                        autocomplete="current-password"
                    >
                </div>

                <div class="form-options">
                    <label class="checkbox-label">
                        <input type="checkbox" name="remember" value="1">
                        <span class="checkmark"></span>
                        Remember me
                    </label>
                    <a href="#forgot-password" class="forgot-link">Forgot password?</a>
                </div>

                <button type="submit" class="login-btn">Sign In</button>
            </form>

            <div class="login-footer">
                <div class="demo-accounts">
                    <h4>Demo Accounts</h4>
                    <div class="demo-account">
                        <strong>Customer Demo:</strong><br>
                        Email: demo@example.com<br>
                        Password: demo123
                    </div>
                    <div class="demo-account">
                        <strong>Admin Access:</strong><br>
                        Username: admin<br>
                        Password: <span class="admin-password">AEIMSAdmin2024!SecurePass</span>
                    </div>
                </div>

                <div class="login-links">
                    <p>New customer? <a href="index.php#contact">Contact us for access</a></p>
                    <p><a href="support.php">Need help with login?</a></p>
                </div>
            </div>
        </div>

        <div class="login-info">
            <h3>AEIMS Customer Portal</h3>
            <div class="feature-list">
                <div class="feature-item">
                    <span class="feature-icon">🎛️</span>
                    <div>
                        <strong>Initial AEIMS Setup</strong>
                        <p>Configure your platform settings and preferences</p>
                    </div>
                </div>
                <div class="feature-item">
                    <span class="feature-icon">🌐</span>
                    <div>
                        <strong>Domain Management</strong>
                        <p>Add, remove, and configure your domains</p>
                    </div>
                </div>
                <div class="feature-item">
                    <span class="feature-icon">📊</span>
                    <div>
                        <strong>Analytics & Stats</strong>
                        <p>View line, chat, and video statistics</p>
                    </div>
                </div>
                <div class="feature-item">
                    <span class="feature-icon">🎫</span>
                    <div>
                        <strong>Support Tickets</strong>
                        <p>Submit and track your support requests</p>
                    </div>
                </div>
            </div>

            <div class="security-info">
                <h4>🔒 Secure Access</h4>
                <ul>
                    <li>SSL encrypted connection</li>
                    <li>Session-based authentication</li>
                    <li>Login attempt monitoring</li>
                    <li>Automatic session timeout</li>
                </ul>
            </div>
        </div>
    </div>

    <div class="background-animation">
        <div class="floating-elements">
            <div class="float-element"></div>
            <div class="float-element"></div>
            <div class="float-element"></div>
            <div class="float-element"></div>
            <div class="float-element"></div>
        </div>
    </div>

    <script src="assets/js/login.js"></script>
</body>
</html>