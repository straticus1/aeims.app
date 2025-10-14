<?php
/**
 * AEIMS Agents Portal - Operator Login
 * SECURITY UPDATED: Session fixation, CSRF, rate limiting
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

// SECURITY FIX: Load SecurityManager
require_once __DIR__ . '/../includes/SecurityManager.php';
$security = SecurityManager::getInstance();

// Initialize secure session
$security->initializeSecureSession();
$security->applySecurityHeaders();

// Adjust paths for router.php routing
$operatorAuthPath = __DIR__ . '/includes/OperatorAuth.php';
if (!file_exists($operatorAuthPath)) {
    die('ERROR: OperatorAuth.php not found at: ' . $operatorAuthPath . '<br>__DIR__ is: ' . __DIR__);
}

require_once $operatorAuthPath;

// Load config
$configPath = __DIR__ . '/../config.php';
if (!file_exists($configPath)) {
    die('Config file not found at: ' . $configPath);
}
$config = include $configPath;
if (!is_array($config)) {
    die('Config file did not return an array');
}

$auth = new OperatorAuth();
$message = '';
$messageType = '';

// Check if already logged in
if ($auth->isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

// Handle login form submission
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'], $_POST['password'])) {
    // SECURITY FIX: CSRF Protection
    verify_csrf();

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $message = 'Please enter both username and password.';
        $messageType = 'error';
    } else {
        // SECURITY FIX: Rate limiting
        $ip = $_SERVER['REMOTE_ADDR'];
        if (!$security->checkRateLimit($ip, 'operator_login', 5, 300)) {
            $message = 'Too many login attempts. Please try again in 5 minutes.';
            $messageType = 'error';
        } else {
            $result = $auth->authenticate($username, $password);

            if ($result['success']) {
                // SECURITY FIX: Regenerate session to prevent session fixation
                $security->regenerateSessionOnLogin();

                // Reset rate limit on success
                $security->resetRateLimit($ip, 'operator_login');

                header('Location: ' . ($result['redirect'] ?? 'dashboard.php'));
                exit();
            } else {
                $message = $result['error'];
                $messageType = 'error';
            }
        }
    }
}

// Handle logout message
if (isset($_GET['logged_out'])) {
    $message = 'You have been logged out successfully.';
    $messageType = 'success';
}

if (isset($_GET['timeout'])) {
    $message = 'Your session has expired. Please log in again.';
    $messageType = 'warning';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Operator Login - <?php echo $config['portal']['name']; ?></title>
    <meta name="description" content="AEIMS Agents Portal - Secure login for cross-domain operators">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            max-width: 1000px;
            width: 100%;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            overflow: hidden;
            box-shadow: 0 25px 45px rgba(0, 0, 0, 0.1);
        }

        .login-form-section {
            padding: 60px 50px;
            background: rgba(255, 255, 255, 0.95);
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .login-info-section {
            padding: 60px 50px;
            background: rgba(0, 0, 0, 0.2);
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .logo {
            text-align: center;
            margin-bottom: 40px;
        }

        .logo h1 {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 5px;
        }

        .logo p {
            color: #6b7280;
            font-size: 1rem;
            font-weight: 500;
        }

        .login-form {
            width: 100%;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #374151;
            font-size: 0.95rem;
        }

        .form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: white;
        }

        .form-group input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .login-btn {
            width: 100%;
            padding: 14px 20px;
            background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(59, 130, 246, 0.4);
        }

        .login-btn:active {
            transform: translateY(0);
        }

        .message {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .message.success {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .message.error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .message.warning {
            background: #fffbeb;
            color: #92400e;
            border: 1px solid #fed7aa;
        }

        .demo-accounts {
            margin-top: 30px;
            padding: 20px;
            background: #f8fafc;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }

        .demo-accounts h4 {
            color: #1f2937;
            margin-bottom: 15px;
            font-weight: 600;
        }

        .demo-account {
            background: white;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 10px;
            font-size: 0.9rem;
            border: 1px solid #e5e7eb;
        }

        .demo-account strong {
            color: #3b82f6;
        }

        .info-content h2 {
            font-size: 2rem;
            margin-bottom: 20px;
            font-weight: 700;
        }

        .info-content p {
            font-size: 1.1rem;
            line-height: 1.6;
            margin-bottom: 30px;
            opacity: 0.9;
        }

        .feature-list {
            list-style: none;
        }

        .feature-list li {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            font-size: 1rem;
        }

        .feature-list li::before {
            content: "✨";
            margin-right: 12px;
            font-size: 1.2rem;
        }

        .domains-showcase {
            margin-top: 30px;
            padding: 20px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .domains-showcase h4 {
            margin-bottom: 15px;
            font-weight: 600;
        }

        .domain-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .domain-tag {
            padding: 4px 8px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 6px;
            font-size: 0.8rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        @media (max-width: 768px) {
            .login-container {
                grid-template-columns: 1fr;
                max-width: 400px;
            }

            .login-info-section {
                display: none;
            }

            .login-form-section {
                padding: 40px 30px;
            }
        }

        .back-link {
            position: fixed;
            top: 20px;
            left: 20px;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .back-link:hover {
            color: white;
            transform: translateX(-5px);
        }

        .back-link::before {
            content: "←";
            margin-right: 8px;
        }
    </style>
</head>
<body>
    <a href="../index.php" class="back-link">Back to AEIMS</a>

    <div class="login-container">
        <div class="login-form-section">
            <div class="logo">
                <h1>Agents Portal</h1>
                <p><?php echo $config['portal']['subtitle']; ?></p>
            </div>

            <?php if ($message): ?>
                <div class="message <?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <form class="login-form" method="POST" action="">
                <?php echo csrf_field(); ?>

                <div class="form-group">
                    <label for="username">Username</label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        required 
                        value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                        placeholder="Enter your username"
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

                <button type="submit" class="login-btn">
                    Sign In to Dashboard
                </button>
            </form>

            <div class="demo-accounts">
                <h4>Demo Operator Accounts</h4>
                <div class="demo-account">
                    <strong>Sarah Johnson:</strong> sarah@example.com / demo123<br>
                    <small>Domains: Beasty Bitches, Cavern Love, Nite Text, Nine Inches Of, Holy Flirts</small>
                </div>
                <div class="demo-account">
                    <strong>Jessica Williams:</strong> jessica@example.com / demo456<br>
                    <small>Domains: NYC Flirts, GFE Calls, Late Nite Love, Fantasy Flirts</small>
                </div>
                <div class="demo-account">
                    <strong>Amanda Rodriguez:</strong> amanda@example.com / demo789<br>
                    <small>Domains: Domme Cats, Fantasy Flirts, Nine Inches Of, Beasty Bitches</small>
                </div>
            </div>
        </div>

        <div class="login-info-section">
            <div class="info-content">
                <h2>Cross-Domain Operator Management</h2>
                <p>Manage your presence across all <?php echo count($config['domains']); ?> AEIMS domains from a single, unified dashboard.</p>

                <ul class="feature-list">
                    <li>Turn calls, texts & chat on/off instantly</li>
                    <li>Update forwarding settings across domains</li>
                    <li>Manage profiles, photos & content</li>
                    <li>Set custom rates & availability</li>
                    <li>View earnings & analytics</li>
                    <li>Sync settings across all domains</li>
                </ul>

                <div class="domains-showcase">
                    <h4>Available Domains (<?php echo count($config['domains']); ?>)</h4>
                    <div class="domain-tags">
                        <?php foreach (array_keys($config['domains']) as $domain): ?>
                            <span class="domain-tag"><?php echo $domain; ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto-focus username field
        document.getElementById('username').focus();

        // Add enter key handling
        document.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.querySelector('.login-form').submit();
            }
        });

        // Add loading state to button
        document.querySelector('.login-form').addEventListener('submit', function() {
            const btn = document.querySelector('.login-btn');
            btn.innerHTML = 'Signing In...';
            btn.disabled = true;
        });
    </script>
</body>
</html>