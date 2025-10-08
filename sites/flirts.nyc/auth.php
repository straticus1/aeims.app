<?php
/**
 * Customer Authentication System - New Microservice Architecture
 * Handles login, signup, and logout for customer sites with integrated mail system
 */

session_start();

// Load microservice integrations
require_once '../../lib/MicroserviceClient.php';
require_once '../../lib/SiteMailIntegration.php';

// Auto-detect domain
$currentDomain = $_SERVER['HTTP_HOST'] ?? 'flirts.nyc';

try {
    // Initialize microservice clients
    $adminClient = new \AEIMS\Lib\MicroserviceClient('admin-service', 8000);
    $mailIntegration = new \AEIMS\Lib\SiteMailIntegration($currentDomain);

    // Get site configuration
    $site = $adminClient->get("/api/admin/sites/{$currentDomain}");
    if (!$site || !($site['active'] ?? false)) {
        http_response_code(503);
        die('Site temporarily unavailable');
    }
} catch (Exception $e) {
    error_log("Auth system error: " . $e->getMessage());
    http_response_code(500);
    die('Authentication system unavailable');
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($action) {
        case 'login':
            $username = trim($_POST['username']);
            $password = $_POST['password'];

            if ($username && $password) {
                try {
                    // Authenticate via admin service
                    $authResult = $adminClient->post("/api/admin/sites/{$currentDomain}/auth/login", [
                        'username' => $username,
                        'password' => $password
                    ]);

                    if ($authResult['success'] ?? false) {
                        $customer = $authResult['customer'];
                        $ssoToken = $authResult['sso_token'] ?? '';

                        $_SESSION['customer_id'] = $customer['customer_id'];
                        $_SESSION['customer_data'] = $customer;
                        $_SESSION['site_domain'] = $currentDomain;
                        $_SESSION['sso_token'] = $ssoToken;

                        // Store SSO token in a secure cookie for cross-domain access
                        if ($ssoToken) {
                            setcookie('aeims_sso_token', $ssoToken, [
                                'expires' => time() + (24 * 60 * 60), // 24 hours
                                'path' => '/',
                                'domain' => '.afterdarksystems.net', // Allows access across subdomains
                                'secure' => true,
                                'httponly' => true,
                                'samesite' => 'Lax'
                            ]);
                        }

                        // Log successful login
                        $adminClient->post("/api/admin/sites/{$currentDomain}/users/{$customer['customer_id']}/activity", [
                            'action' => 'login',
                            'data' => [
                                'site' => $currentDomain,
                                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                            ]
                        ]);

                        header('Location: /dashboard.php');
                        exit;
                    } else {
                        $message = $authResult['message'] ?? 'Invalid username or password';
                        $messageType = 'error';
                    }
                } catch (Exception $e) {
                    $message = 'Login failed: ' . $e->getMessage();
                    $messageType = 'error';
                }
            } else {
                $message = 'Username and password are required';
                $messageType = 'error';
            }
            break;

        case 'signup':
            $username = trim($_POST['username']);
            $email = trim($_POST['email']);
            $password = $_POST['password'];
            $confirmPassword = $_POST['confirm_password'];

            if ($username && $email && $password && $confirmPassword) {
                if ($password !== $confirmPassword) {
                    $message = 'Passwords do not match';
                    $messageType = 'error';
                } elseif (strlen($password) < 6) {
                    $message = 'Password must be at least 6 characters long';
                    $messageType = 'error';
                } elseif (!$mailIntegration->validateEmail($email)) {
                    $message = 'Please enter a valid email address';
                    $messageType = 'error';
                } else {
                    try {
                        // Create customer via admin service
                        $registrationResult = $adminClient->post("/api/admin/sites/{$currentDomain}/auth/register", [
                            'username' => $username,
                            'email' => $email,
                            'password' => $password,
                            'registration_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                        ]);

                        if ($registrationResult['success'] ?? false) {
                            $customer = $registrationResult['customer'];

                            $_SESSION['customer_id'] = $customer['customer_id'];
                            $_SESSION['customer_data'] = $customer;
                            $_SESSION['site_domain'] = $currentDomain;

                            // Send welcome email
                            try {
                                $mailIntegration->sendWelcomeEmail($customer['customer_id'], [
                                    'name' => $customer['name'] ?? $username,
                                    'username' => $username,
                                    'email' => $email
                                ]);
                            } catch (Exception $e) {
                                error_log("Failed to send welcome email: " . $e->getMessage());
                                // Continue with registration even if email fails
                            }

                            // Log successful registration
                            $adminClient->post("/api/admin/sites/{$currentDomain}/users/{$customer['customer_id']}/activity", [
                                'action' => 'register',
                                'data' => [
                                    'site' => $currentDomain,
                                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                                ]
                            ]);

                            $message = 'Registration successful! Welcome to ' . ($site['name'] ?? $currentDomain);
                            $messageType = 'success';

                            // Redirect to dashboard after short delay
                            header('Location: /dashboard.php');
                            exit;
                        } else {
                            $message = $registrationResult['message'] ?? 'Registration failed. Please try again.';
                            $messageType = 'error';
                        }
                    } catch (Exception $e) {
                        $message = 'Registration failed: ' . $e->getMessage();
                        $messageType = 'error';
                    }
                }
            } else {
                $message = 'All fields are required';
                $messageType = 'error';
            }
            break;

        case 'reset_password':
            $email = trim($_POST['email']);

            if ($email && $mailIntegration->validateEmail($email)) {
                try {
                    // Request password reset via admin service
                    $resetResult = $adminClient->post("/api/admin/sites/{$currentDomain}/auth/password-reset", [
                        'email' => $email
                    ]);

                    if ($resetResult['success'] ?? false) {
                        $userId = $resetResult['user_id'];
                        $resetToken = $resetResult['reset_token'];

                        // Send password reset email
                        try {
                            $mailIntegration->sendPasswordResetEmail($userId, $resetToken, [
                                'email' => $email,
                                'username' => $resetResult['username'] ?? 'User'
                            ]);

                            $message = 'Password reset email sent. Please check your inbox.';
                            $messageType = 'success';
                        } catch (Exception $e) {
                            error_log("Failed to send password reset email: " . $e->getMessage());
                            $message = 'Password reset request processed, but email delivery failed. Please try again.';
                            $messageType = 'error';
                        }
                    } else {
                        $message = $resetResult['message'] ?? 'Email not found in our system.';
                        $messageType = 'error';
                    }
                } catch (Exception $e) {
                    $message = 'Password reset failed: ' . $e->getMessage();
                    $messageType = 'error';
                }
            } else {
                $message = 'Please enter a valid email address';
                $messageType = 'error';
            }
            break;

        case 'reset_password_confirm':
            $token = trim($_POST['token']);
            $newPassword = $_POST['new_password'];
            $confirmPassword = $_POST['confirm_password'];

            if ($token && $newPassword && $confirmPassword) {
                if ($newPassword !== $confirmPassword) {
                    $message = 'Passwords do not match';
                    $messageType = 'error';
                } elseif (strlen($newPassword) < 6) {
                    $message = 'Password must be at least 6 characters long';
                    $messageType = 'error';
                } else {
                    try {
                        // Confirm password reset via admin service
                        $confirmResult = $adminClient->post("/api/admin/sites/{$currentDomain}/auth/password-reset-confirm", [
                            'token' => $token,
                            'new_password' => $newPassword
                        ]);

                        if ($confirmResult['success'] ?? false) {
                            $message = 'Password reset successful! You can now log in with your new password.';
                            $messageType = 'success';
                        } else {
                            $message = $confirmResult['message'] ?? 'Invalid or expired reset token.';
                            $messageType = 'error';
                        }
                    } catch (Exception $e) {
                        $message = 'Password reset failed: ' . $e->getMessage();
                        $messageType = 'error';
                    }
                }
            } else {
                $message = 'All fields are required';
                $messageType = 'error';
            }
            break;
    }
}

// Handle logout
if ($action === 'logout') {
    if (isset($_SESSION['customer_id'])) {
        try {
            // Perform global SSO logout via admin service
            $adminClient->post("/api/admin/sites/{$currentDomain}/auth/logout", [
                'customer_id' => $_SESSION['customer_id'],
                'sso_token' => $_SESSION['sso_token'] ?? ''
            ]);

            // Log logout activity
            $adminClient->post("/api/admin/sites/{$currentDomain}/users/{$_SESSION['customer_id']}/activity", [
                'action' => 'logout',
                'data' => [
                    'site' => $currentDomain,
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]
            ]);
        } catch (Exception $e) {
            error_log("SSO Logout error: " . $e->getMessage());
        }
    }

    // Clear SSO cookie
    setcookie('aeims_sso_token', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'domain' => '.afterdarksystems.net',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_destroy();

    // Show logout confirmation with cross-domain logout iframes
    $logoutConfirmationHtml = generateLogoutConfirmation($site);
    echo $logoutConfirmationHtml;
    exit;
}

// Handle password reset token from URL
if ($action === 'reset' && isset($_GET['token'])) {
    $resetToken = $_GET['token'];
    // Show password reset form
    showPasswordResetForm($resetToken, $site);
    exit;
}

// If we get here with an error, redirect back to homepage with message
if ($message) {
    $_SESSION['auth_message'] = $message;
    $_SESSION['auth_message_type'] = $messageType;
    header('Location: /');
    exit;
}

// Default redirect if no action
header('Location: /');
exit;

/**
 * Show password reset form
 */
function showPasswordResetForm($token, $site) {
    $siteName = $site['name'] ?? 'Site';
    $primaryColor = $site['theme']['primary_color'] ?? '#ef4444';

    echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - ' . htmlspecialchars($siteName) . '</title>
    <style>
        body { font-family: Arial, sans-serif; background: linear-gradient(135deg, #1a1a1a 0%, #2d1b3d 100%); color: white; padding: 50px; }
        .reset-form { max-width: 400px; margin: 0 auto; background: rgba(0,0,0,0.8); padding: 2rem; border-radius: 10px; }
        .form-group { margin-bottom: 1rem; }
        label { display: block; margin-bottom: 0.5rem; }
        input[type="password"] { width: 100%; padding: 0.75rem; border: 1px solid #333; background: rgba(255,255,255,0.1); color: white; border-radius: 5px; }
        .btn { background: ' . $primaryColor . '; color: white; padding: 0.75rem 1.5rem; border: none; border-radius: 5px; cursor: pointer; width: 100%; }
        .btn:hover { opacity: 0.9; }
    </style>
</head>
<body>
    <div class="reset-form">
        <h2>Reset Your Password</h2>
        <form method="POST" action="/auth.php">
            <input type="hidden" name="action" value="reset_password_confirm">
            <input type="hidden" name="token" value="' . htmlspecialchars($token) . '">

            <div class="form-group">
                <label for="new_password">New Password:</label>
                <input type="password" id="new_password" name="new_password" required minlength="6">
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password:</label>
                <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
            </div>

            <button type="submit" class="btn">Reset Password</button>
        </form>

        <p style="text-align: center; margin-top: 1rem;">
            <a href="/" style="color: ' . $primaryColor . ';">Back to ' . htmlspecialchars($siteName) . '</a>
        </p>
    </div>
</body>
</html>';
}

/**
 * Generate logout confirmation page with cross-domain logout
 */
function generateLogoutConfirmation($site) {
    $trustedDomains = ['flirts.nyc', 'nycflirts.com', 'aeims.app', 'afterdarksystems.net'];
    $siteName = $site['name'] ?? 'Site';

    $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logging Out - ' . htmlspecialchars($siteName) . '</title>
    <style>
        body { font-family: Arial, sans-serif; background: #1a1a1a; color: white; text-align: center; padding: 50px; }
        .logout-message { max-width: 500px; margin: 0 auto; }
        .spinner { border: 4px solid #333; border-top: 4px solid #ef4444; border-radius: 50%; width: 40px; height: 40px; animation: spin 2s linear infinite; margin: 20px auto; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <div class="logout-message">
        <h2>Logging Out</h2>
        <div class="spinner"></div>
        <p>You are being logged out from all After Dark Systems sites...</p>
    </div>';

    // Add hidden iframes to logout from all domains
    foreach ($trustedDomains as $domain) {
        $html .= '<iframe src="https://' . $domain . '/sso/logout.php" style="display:none;"></iframe>';
    }

    $html .= '
    <script>
        setTimeout(function() {
            window.location.href = "/";
        }, 3000);
    </script>
</body>
</html>';

    return $html;
}
?>