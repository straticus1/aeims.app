<?php
/**
 * Site-Specific Login Handler
 * Handles login.sitename.com requests and routes users appropriately
 */

require_once 'includes/SiteSpecificAuth.php';

$siteAuth = new SiteSpecificAuth();
$currentSite = $siteAuth->getCurrentSite();
$siteConfig = $siteAuth->getSiteConfig();

$error = '';
$success = '';

// Handle login form submission
if ($_POST['action'] ?? '' === 'login') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password';
    } else {
        $user = $siteAuth->authenticateUser($username, $password);

        if ($user) {
            $siteAuth->createSession($user);
            $redirectUrl = $siteAuth->getRedirectUrl($user);

            // Log successful login
            error_log("Site login successful: {$username} ({$user['type']}) -> {$redirectUrl}");

            header('Location: ' . $redirectUrl);
            exit();
        } else {
            $error = 'Invalid username or password for ' . htmlspecialchars($currentSite);
        }
    }
}

// Handle registration
if ($_POST['action'] ?? '' === 'register') {
    $userData = [
        'username' => $_POST['username'] ?? '',
        'email' => $_POST['email'] ?? '',
        'password' => $_POST['password'] ?? '',
        'name' => $_POST['name'] ?? '',
        'role' => $_POST['role'] ?? 'customer'
    ];

    if (empty($userData['username']) || empty($userData['email']) || empty($userData['password'])) {
        $error = 'Please fill in all required fields';
    } else {
        $result = $siteAuth->registerUser($userData);

        if (isset($result['success'])) {
            $success = 'Account created successfully! You can now log in.';
        } else {
            $error = $result['error'] ?? 'Registration failed';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo htmlspecialchars($siteConfig['site_name'] ?? $currentSite); ?></title>
    <meta name="description" content="Login to <?php echo htmlspecialchars($currentSite); ?> - AEIMS Platform">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/site-login.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="site-login-body">
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="site-logo">
                    <img src="<?php echo $siteConfig['login_logo'] ?? '/assets/images/logo.png'; ?>"
                         alt="<?php echo htmlspecialchars($currentSite); ?>"
                         onerror="this.style.display='none'">
                    <h1><?php echo htmlspecialchars($siteConfig['site_name'] ?? $currentSite); ?></h1>
                </div>
                <p class="login-subtitle">Sign in to access your account</p>
            </div>

            <div class="login-tabs">
                <button class="tab-button active" onclick="showTab('login')">Sign In</button>
                <button class="tab-button" onclick="showTab('register')">Register</button>
            </div>

            <!-- Error/Success Messages -->
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <span class="alert-icon">⚠️</span>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <span class="alert-icon">✅</span>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <?php
            // Show test credentials in development
            if ($_SERVER['SERVER_NAME'] === 'localhost' || strpos($_SERVER['SERVER_NAME'], '127.0.0.1') !== false) {
                include 'includes/test-credentials.php';
            }
            ?>

            <!-- Login Form -->
            <div id="login-tab" class="tab-content active">
                <form method="POST" class="login-form">
                    <input type="hidden" name="action" value="login">

                    <div class="form-group">
                        <label for="username">Username or Email</label>
                        <input type="text" id="username" name="username" required
                               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                               placeholder="Enter your username or email">
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required
                               placeholder="Enter your password">
                    </div>

                    <div class="form-options">
                        <label class="checkbox-label">
                            <input type="checkbox" name="remember_me">
                            <span class="checkmark"></span>
                            Remember me
                        </label>
                        <a href="forgot-password.php?site=<?php echo urlencode($currentSite); ?>" class="forgot-link">
                            Forgot password?
                        </a>
                    </div>

                    <button type="submit" class="btn btn-primary btn-full">
                        Sign In to <?php echo htmlspecialchars($currentSite); ?>
                    </button>
                </form>
            </div>

            <!-- Registration Form -->
            <div id="register-tab" class="tab-content">
                <form method="POST" class="login-form">
                    <input type="hidden" name="action" value="register">

                    <div class="form-group">
                        <label for="reg-name">Full Name</label>
                        <input type="text" id="reg-name" name="name" required
                               value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>"
                               placeholder="Enter your full name">
                    </div>

                    <div class="form-group">
                        <label for="reg-username">Username</label>
                        <input type="text" id="reg-username" name="username" required
                               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                               placeholder="Choose a username">
                    </div>

                    <div class="form-group">
                        <label for="reg-email">Email Address</label>
                        <input type="email" id="reg-email" name="email" required
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                               placeholder="Enter your email address">
                    </div>

                    <div class="form-group">
                        <label for="reg-password">Password</label>
                        <input type="password" id="reg-password" name="password" required
                               placeholder="Create a password">
                    </div>

                    <div class="form-group">
                        <label for="reg-role">Account Type</label>
                        <select id="reg-role" name="role" required>
                            <option value="customer" <?php echo ($_POST['role'] ?? '') === 'customer' ? 'selected' : ''; ?>>
                                Customer
                            </option>
                            <option value="operator" <?php echo ($_POST['role'] ?? '') === 'operator' ? 'selected' : ''; ?>>
                                Operator
                            </option>
                            <option value="reseller" <?php echo ($_POST['role'] ?? '') === 'reseller' ? 'selected' : ''; ?>>
                                Reseller
                            </option>
                        </select>
                    </div>

                    <div class="form-options">
                        <label class="checkbox-label">
                            <input type="checkbox" name="reserve_username" checked>
                            <span class="checkmark"></span>
                            Reserve username across all sites
                        </label>
                    </div>

                    <div class="form-options">
                        <label class="checkbox-label">
                            <input type="checkbox" name="terms" required>
                            <span class="checkmark"></span>
                            I agree to the <a href="/terms.php" target="_blank">Terms of Service</a>
                        </label>
                    </div>

                    <button type="submit" class="btn btn-primary btn-full">
                        Create Account
                    </button>
                </form>
            </div>

            <div class="login-footer">
                <div class="site-info">
                    <p>Powered by <strong>AEIMS Platform</strong></p>
                    <p>Enterprise telephony and customer management</p>
                </div>

                <div class="login-links">
                    <a href="https://aeims.app/support.php">Support</a>
                    <span>•</span>
                    <a href="https://aeims.app/">AEIMS.app</a>
                    <span>•</span>
                    <a href="/privacy.php">Privacy</a>
                </div>
            </div>
        </div>

        <!-- Background Elements -->
        <div class="login-background">
            <div class="bg-element bg-element-1"></div>
            <div class="bg-element bg-element-2"></div>
            <div class="bg-element bg-element-3"></div>
        </div>
    </div>

    <script>
        function showTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });

            // Remove active class from all tab buttons
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('active');
            });

            // Show selected tab
            document.getElementById(tabName + '-tab').classList.add('active');
            event.target.classList.add('active');

            // Clear any form errors
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => alert.style.display = 'none');
        }

        // Auto-focus username field on page load
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('username').focus();
        });

        // Form validation
        document.querySelector('#register-tab form').addEventListener('submit', function(e) {
            const password = document.getElementById('reg-password').value;
            const username = document.getElementById('reg-username').value;

            if (password.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long');
                return;
            }

            if (username.length < 3) {
                e.preventDefault();
                alert('Username must be at least 3 characters long');
                return;
            }
        });
    </script>
</body>
</html>