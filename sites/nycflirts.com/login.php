<?php
/**
 * NYC Flirts - Customer Login Page
 * Customer authentication for nycflirts.com
 */

session_start();

require_once __DIR__ . '/../../includes/CustomerAuth.php';

$auth = new CustomerAuth('nycflirts.com');
$error_message = '';
$success_message = '';

// Check if already logged in
if ($auth->isLoggedIn()) {
    header('Location: /');
    exit();
}

// Handle login submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error_message = 'Please enter both username and password.';
    } else {
        $result = $auth->authenticate($username, $password);

        if ($result['success']) {
            header('Location: ' . ($result['redirect'] ?? '/'));
            exit();
        } else {
            $error_message = $result['message'];
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - NYC Flirts</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', sans-serif;
            background: linear-gradient(135deg, #1a1a1a 0%, #2d1b3d 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
            width: 100%;
            max-width: 450px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(233, 30, 99, 0.2);
        }

        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo {
            font-size: 2.5rem;
            font-weight: bold;
            background: linear-gradient(45deg, #e91e63, #9c27b0);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 10px;
        }

        .subtitle {
            color: #999;
            font-size: 0.9rem;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            color: #fff;
            font-weight: 500;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }

        input[type="text"],
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 12px 16px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            color: #fff;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #e91e63;
            background: rgba(255, 255, 255, 0.15);
        }

        input::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }

        .btn-primary {
            width: 100%;
            padding: 14px;
            background: linear-gradient(45deg, #e91e63, #9c27b0);
            border: none;
            border-radius: 10px;
            color: white;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(233, 30, 99, 0.3);
        }

        .error-message {
            background: rgba(239, 68, 68, 0.2);
            border: 1px solid rgba(239, 68, 68, 0.5);
            color: #fff;
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }

        .success-message {
            background: rgba(34, 197, 94, 0.2);
            border: 1px solid rgba(34, 197, 94, 0.5);
            color: #fff;
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }

        .form-footer {
            margin-top: 25px;
            text-align: center;
        }

        .form-footer a {
            color: #e91e63;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .form-footer a:hover {
            color: #9c27b0;
        }

        .divider {
            margin: 25px 0;
            text-align: center;
            color: #666;
            position: relative;
        }

        .divider::before,
        .divider::after {
            content: '';
            position: absolute;
            top: 50%;
            width: 40%;
            height: 1px;
            background: rgba(255, 255, 255, 0.1);
        }

        .divider::before {
            left: 0;
        }

        .divider::after {
            right: 0;
        }

        .demo-accounts {
            background: rgba(33, 150, 243, 0.1);
            border: 1px solid rgba(33, 150, 243, 0.3);
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
        }

        .demo-accounts h4 {
            color: #2196f3;
            margin-bottom: 10px;
            font-size: 0.9rem;
        }

        .demo-accounts p {
            color: #ccc;
            font-size: 0.85rem;
            line-height: 1.5;
        }

        .demo-accounts strong {
            color: #fff;
        }

        .back-link {
            text-align: center;
            margin-top: 20px;
        }

        .back-link a {
            color: #999;
            text-decoration: none;
            font-size: 0.9rem;
        }

        .back-link a:hover {
            color: #fff;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="logo">NYC Flirts</div>
            <div class="subtitle">Premium Adult Entertainment</div>
        </div>

        <?php if ($error_message): ?>
            <div class="error-message"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <?php if ($success_message): ?>
            <div class="success-message"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Username or Email</label>
                <input
                    type="text"
                    id="username"
                    name="username"
                    placeholder="Enter your username or email"
                    required
                    autocomplete="username"
                    value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                >
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Enter your password"
                    required
                    autocomplete="current-password"
                >
            </div>

            <button type="submit" class="btn-primary">Sign In</button>
        </form>

        <div class="form-footer">
            <p><a href="/register.php">Don't have an account? Sign up</a></p>
            <p style="margin-top: 10px;"><a href="/forgot-password.php">Forgot password?</a></p>
        </div>

        <div class="divider">or</div>

        <div class="demo-accounts">
            <h4>🧪 Test Accounts</h4>
            <p>
                <strong>Username:</strong> nycuser<br>
                <strong>Password:</strong> password123
            </p>
            <p style="margin-top: 10px;">
                <strong>Username:</strong> nycfun25<br>
                <strong>Password:</strong> password123
            </p>
        </div>

        <div class="back-link">
            <a href="/">← Back to Home</a>
        </div>
    </div>
</body>
</html>
