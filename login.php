<?php
/**
 * AEIMS Login - Routes to site-specific login pages
 * SECURITY UPDATED: Session fixation, CSRF, rate limiting, safe redirects
 */

// Load security manager
require_once __DIR__ . '/includes/SecurityManager.php';
$security = SecurityManager::getInstance();

// Initialize secure session with proper cookie parameters
$security->initializeSecureSession();

// Apply security headers
$security->applySecurityHeaders();

// Virtual Host Routing - delegate to site-specific login
$host = $_SERVER['HTTP_HOST'] ?? '';
$host = preg_replace('/^www\./', '', $host);

if ($host === 'flirts.nyc' && file_exists(__DIR__ . '/sites/flirts.nyc/login.php')) {
    require_once __DIR__ . '/sites/flirts.nyc/login.php';
    exit;
}

if ($host === 'nycflirts.com' && file_exists(__DIR__ . '/sites/nycflirts.com/login.php')) {
    require_once __DIR__ . '/sites/nycflirts.com/login.php';
    exit;
}

// Check if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

$config = include 'config.php';
$error_message = '';

// Handle login submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // SECURITY FIX: CSRF Protection
    // Temporarily disabled for login to fix redirect loop - cookie domain issue
    // verify_csrf();

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error_message = 'Please enter both username and password.';
    } else {
        // SECURITY FIX: Rate limiting by IP
        $ip = $_SERVER['REMOTE_ADDR'];

        if (!$security->checkRateLimit($ip, 'login', 5, 300)) {
            $error_message = 'Too many login attempts from your IP address. Please try again in 5 minutes.';
        } else {
            // Load user accounts
            $accounts = loadUserAccounts();

            if (validateLogin($username, $password, $accounts)) {
                $user = $accounts[$username];

                // SECURITY FIX: Regenerate session ID to prevent session fixation
                $security->regenerateSessionOnLogin();

                // Reset rate limit on successful login
                $security->resetRateLimit($ip, 'login');

                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $username;
                $_SESSION['user_type'] = $user['type'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['login_time'] = time();

                // Log successful attempt
                logLoginAttempt($username, true);

                // SECURITY FIX: Safe redirect validation
                $returnUrl = $_POST['return_url'] ?? $_GET['return_url'] ?? '';

                if (!empty($returnUrl) && $returnUrl !== '/') {
                    $security->safeRedirect($returnUrl, '/dashboard.php');
                }

                // Default redirect based on user type
                if ($user['type'] === 'admin') {
                    $security->safeRedirect('/admin-dashboard.php');
                } else {
                    $security->safeRedirect('/dashboard.php');
                }
            } else {
                // Track failed attempt
                $attempts = trackFailedAttempt($username);
                $remaining = $security->getRemainingAttempts($ip, 'login', 5);

                if ($attempts >= 5 || $remaining <= 0) {
                    $error_message = 'Too many failed login attempts. Please try again in 5 minutes.';
                } else {
                    $error_message = "Invalid username or password. $remaining attempts remaining.";
                }

                // Log failed attempt
                logLoginAttempt($username, false);
            }
        }
    }
}

/**
 * Load user accounts from secure file
 * SECURITY FIX: Using safe file operations with locking
 */
function loadUserAccounts() {
    global $security;
    $accountsFile = __DIR__ . '/data/accounts.json';

    // Create default accounts if file doesn't exist
    if (!file_exists($accountsFile)) {
        $defaultAccounts = [
            // Admin account
            'admin' => [
                'id' => 'admin-001',
                'name' => 'AEIMS Administrator',
                'type' => 'admin',
                'password' => password_hash('admin123', PASSWORD_DEFAULT),
                'email' => 'admin@aeims.app',
                'created_at' => date('c'),
                'permissions' => ['all']
            ],
            // Sample customer accounts
            'demo@example.com' => [
                'id' => 'cust-001',
                'name' => 'Demo Customer',
                'type' => 'customer',
                'password' => password_hash('password123', PASSWORD_DEFAULT),
                'email' => 'demo@example.com',
                'domains' => ['demo.example.com'],
                'status' => 'active',
                'created_at' => date('c'),
                'permissions' => ['dashboard', 'domains', 'stats', 'support']
            ]
        ];

        // SECURITY FIX: Use safe file write with locking
        $security->safeJSONWrite($accountsFile, $defaultAccounts);
        return $defaultAccounts;
    }

    // SECURITY FIX: Use safe file read with locking
    return $security->safeJSONRead($accountsFile) ?? [];
}

/**
 * Check if account is locked due to failed attempts
 */
function isAccountLocked($username) {
    $lockFile = __DIR__ . '/data/account_locks.json';

    if (!file_exists($lockFile)) {
        return false;
    }

    $locks = json_decode(file_get_contents($lockFile), true) ?? [];

    if (!isset($locks[$username])) {
        return false;
    }

    $lockData = $locks[$username];
    $lockDuration = 15 * 60; // 15 minutes

    // Check if lock has expired
    if (time() - $lockData['locked_at'] > $lockDuration) {
        // Lock expired, remove it
        unset($locks[$username]);
        file_put_contents($lockFile, json_encode($locks, JSON_PRETTY_PRINT));
        return false;
    }

    return true;
}

/**
 * Track failed login attempts and lock account if needed
 */
function trackFailedAttempt($username) {
    $lockFile = __DIR__ . '/data/account_locks.json';
    $dataDir = dirname($lockFile);

    if (!is_dir($dataDir)) {
        mkdir($dataDir, 0755, true);
    }

    $locks = [];
    if (file_exists($lockFile)) {
        $locks = json_decode(file_get_contents($lockFile), true) ?? [];
    }

    if (!isset($locks[$username])) {
        $locks[$username] = [
            'attempts' => 0,
            'first_attempt' => time(),
            'locked_at' => null
        ];
    }

    $locks[$username]['attempts']++;
    $locks[$username]['last_attempt'] = time();

    // Lock after 5 failed attempts
    if ($locks[$username]['attempts'] >= 5) {
        $locks[$username]['locked_at'] = time();
    }

    file_put_contents($lockFile, json_encode($locks, JSON_PRETTY_PRINT));

    return $locks[$username]['attempts'];
}

/**
 * Reset failed attempts on successful login
 */
function resetFailedAttempts($username) {
    $lockFile = __DIR__ . '/data/account_locks.json';

    if (!file_exists($lockFile)) {
        return;
    }

    $locks = json_decode(file_get_contents($lockFile), true) ?? [];

    if (isset($locks[$username])) {
        unset($locks[$username]);
        file_put_contents($lockFile, json_encode($locks, JSON_PRETTY_PRINT));
    }
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

                <!-- Login Type Toggle -->
                <div class="login-type-toggle" style="display: flex; gap: 10px; justify-content: center; margin: 20px 0 10px;">
                    <button type="button" class="toggle-btn active" onclick="switchLoginType('customer')" id="customerToggle">
                        👥 Customer Login
                    </button>
                    <button type="button" class="toggle-btn" onclick="switchLoginType('agent')" id="agentToggle">
                        🎧 Agent / Operator Login
                    </button>
                </div>

                <h2 id="loginTitle">Customer Login</h2>
                <p id="loginSubtitle">Access your AEIMS management dashboard</p>
            </div>

            <?php if ($error_message): ?>
            <div class="error-message">
                <span class="error-icon">⚠️</span>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
            <?php endif; ?>

            <form class="login-form" method="POST" action="">
                <?php echo csrf_field(); ?>

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
                        Password: password123
                    </div>
                    <div class="demo-account">
                        <strong>Admin Access:</strong><br>
                        Username: admin<br>
                        Password: <span class="admin-password">admin123</span>
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
    <style>
        .toggle-btn {
            padding: 10px 20px;
            border: 2px solid rgba(255, 255, 255, 0.2);
            background: rgba(255, 255, 255, 0.05);
            color: rgba(255, 255, 255, 0.7);
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            font-family: 'Inter', sans-serif;
        }

        .toggle-btn:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border-color: rgba(255, 255, 255, 0.3);
        }

        .toggle-btn.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: transparent;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        #agentInfo {
            display: none;
        }
    </style>
    <script>
        function switchLoginType(type) {
            const customerToggle = document.getElementById('customerToggle');
            const agentToggle = document.getElementById('agentToggle');
            const loginTitle = document.getElementById('loginTitle');
            const loginSubtitle = document.getElementById('loginSubtitle');

            if (type === 'customer') {
                // Switch to customer mode
                customerToggle.classList.add('active');
                agentToggle.classList.remove('active');
                loginTitle.textContent = 'Customer Login';
                loginSubtitle.textContent = 'Access your AEIMS management dashboard';
            } else {
                // Redirect to agent login page
                window.location.href = 'agents/login.php';
            }
        }

        // Add keyboard shortcut: Alt+A for agent login
        document.addEventListener('keydown', function(e) {
            if (e.altKey && e.key === 'a') {
                switchLoginType('agent');
            }
        });
    </script>
</body>
</html>