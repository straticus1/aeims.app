<?php
/**
 * Flirts NYC - Customer Site Homepage
 * AEIMS Multi-Site Architecture - Customer Portal
 */

session_start();

// Load SSO middleware first
require_once __DIR__ . '/sso/middleware.php';

// Load site configuration
require_once __DIR__ . '/../../services/SiteManager.php';
require_once __DIR__ . '/../../services/OperatorManager.php';

// Check for SSO auto-login
checkSSOSession();

try {
    $siteManager = new \AEIMS\Services\SiteManager();
    $operatorManager = new \AEIMS\Services\OperatorManager();

    // Dynamically determine site from HTTP_HOST or SERVER_NAME
    $hostname = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'nycflirts.com';
    // Remove www. prefix if present
    $hostname = preg_replace('/^www\./', '', $hostname);
    // Remove port if present
    $hostname = preg_replace('/:\d+$/', '', $hostname);
    
    $site = $siteManager->getSite($hostname);
    if (!$site || !$site['active']) {
        http_response_code(503);
        die('Site temporarily unavailable');
    }
} catch (Exception $e) {
    error_log("Site loading error: " . $e->getMessage());
    http_response_code(500);
    die('Site configuration error');
}

$isLoggedIn = isset($_SESSION['customer_id']);
$currentCustomer = $isLoggedIn ? $_SESSION['customer_data'] : null;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($site['name']) ?> - Premium Adult Entertainment</title>
    <link rel="icon" href="<?= htmlspecialchars($site['theme']['favicon_url'] ?? '/assets/images/favicon.ico') ?>">
    <?php if ($isLoggedIn): ?>
    <script src="/assets/js/notifications.js"></script>
    <?php endif; ?>

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

        .nav-menu {
            display: flex;
            gap: 2rem;
            list-style: none;
        }

        .nav-menu a {
            color: #ffffff;
            text-decoration: none;
            transition: color 0.3s ease;
            font-weight: 500;
            font-size: 1rem;
        }

        .nav-menu a:hover {
            color: <?= $site['theme']['primary_color'] ?>;
        }

        .auth-buttons {
            display: flex;
            gap: 1rem;
        }

        .btn {
            padding: 0.7rem 1.5rem;
            border: none;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            cursor: pointer;
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

        .hero {
            margin-top: 80px;
            padding: 4rem 2rem;
            text-align: center;
            background: linear-gradient(rgba(0,0,0,0.7), rgba(0,0,0,0.7)), url('assets/images/hero-bg.jpg');
            background-size: cover;
            background-position: center;
            min-height: 70vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .hero-content h1 {
            font-size: 3.5rem;
            margin-bottom: 1.5rem;
            background: linear-gradient(45deg, <?= $site['theme']['primary_color'] ?>, <?= $site['theme']['accent_color'] ?>);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero-content p {
            font-size: 1.3rem;
            margin-bottom: 2rem;
            max-width: 600px;
        }

        .categories {
            padding: 4rem 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }

        .categories h2 {
            text-align: center;
            font-size: 2.5rem;
            margin-bottom: 3rem;
            color: <?= $site['theme']['primary_color'] ?>;
        }

        .category-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
        }

        .category-card {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 2rem;
            text-align: center;
            transition: transform 0.3s ease;
            border: 1px solid rgba(239, 68, 68, 0.2);
            backdrop-filter: blur(10px);
        }

        .category-card:hover {
            transform: translateY(-5px);
            border-color: <?= $site['theme']['primary_color'] ?>;
        }

        .category-card h3 {
            font-size: 1.3rem;
            margin-bottom: 1rem;
            color: <?= $site['theme']['accent_color'] ?>;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            z-index: 2000;
            backdrop-filter: blur(5px);
        }

        .modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: linear-gradient(135deg, #2a2a2a 0%, #1a1a1a 100%);
            padding: 3rem;
            border-radius: 20px;
            border: 1px solid rgba(239, 68, 68, 0.3);
            min-width: 400px;
            max-width: 500px;
            width: 90%;
        }

        .modal h2 {
            color: <?= $site['theme']['primary_color'] ?>;
            margin-bottom: 2rem;
            text-align: center;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: <?= $site['theme']['text_color'] ?>;
            font-weight: 500;
        }

        .form-group input {
            width: 100%;
            padding: 1rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            background: rgba(0, 0, 0, 0.3);
            color: <?= $site['theme']['text_color'] ?>;
            font-size: 1rem;
        }

        .form-group input:focus {
            outline: none;
            border-color: <?= $site['theme']['primary_color'] ?>;
            box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.2);
        }

        .modal-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
        }

        .close-modal {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: none;
            border: none;
            color: <?= $site['theme']['text_color'] ?>;
            font-size: 1.5rem;
            cursor: pointer;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 1rem;
            color: <?= $site['theme']['text_color'] ?>;
        }

        .footer {
            background: #0a0a0a;
            padding: 3rem 2rem 2rem;
            margin-top: 4rem;
            text-align: center;
            border-top: 1px solid rgba(239, 68, 68, 0.3);
        }

        @media (max-width: 768px) {
            .nav-menu {
                display: none;
            }

            .hero-content h1 {
                font-size: 2.5rem;
            }

            .category-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php
    // Display authentication messages if any
    $authMessage = $_SESSION['auth_message'] ?? null;
    $authMessageType = $_SESSION['auth_message_type'] ?? 'info';

    if ($authMessage) {
        unset($_SESSION['auth_message']);
        unset($_SESSION['auth_message_type']);
        $alertColor = $authMessageType === 'error' ? '#ef4444' : '#10b981';
        echo "<div style='position: fixed; top: 80px; left: 50%; transform: translateX(-50%); z-index: 3000; background: rgba(0,0,0,0.95); color: $alertColor; padding: 1rem 2rem; border-radius: 10px; border: 1px solid $alertColor; box-shadow: 0 4px 6px rgba(0,0,0,0.3);'>" . htmlspecialchars($authMessage) . "</div>";
        echo "<script>setTimeout(() => { const alert = document.querySelector('div[style*=\"position: fixed\"]'); if(alert) alert.remove(); }, 5000); " . ($authMessageType === 'error' ? "window.addEventListener('DOMContentLoaded', () => openLoginModal());" : "") . "</script>";
    }
    ?>

    <header class="header">
        <div class="nav-container">
            <a href="/" class="logo"><?= htmlspecialchars($site['name']) ?></a>

            <nav>
                <ul class="nav-menu">
                    <?php if ($isLoggedIn): ?>
                        <li><a href="/search-operators.php">🔍 Search</a></li>
                        <li><a href="/messages.php">✉️ Messages</a></li>
                        <li><a href="/chat.php">💬 Chat</a></li>
                        <li><a href="/rooms.php">🏠 Rooms</a></li>
                        <li><a href="/activity-log.php">📊 Activity</a></li>
                        <li><a href="/settings.php">⚙️ Settings</a></li>
                        <li><a href="/logout.php">🚪 Logout</a></li>
                    <?php else: ?>
                        <li><a href="#about">About Us</a></li>
                    <?php endif; ?>
                </ul>
            </nav>

            <div class="auth-buttons">
                <?php if ($isLoggedIn): ?>
                    <div class="user-profile">
                        <span>👤 <?= htmlspecialchars($currentCustomer['username']) ?></span>
                    </div>
                <?php else: ?>
                    <a href="/login.php" class="btn btn-secondary">Sign In</a>
                    <a href="/register.php" class="btn btn-primary">Join Now</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main>
        <section class="hero">
            <div class="hero-content">
                <?php if ($isLoggedIn): ?>
                    <h1>Welcome Back, <?= htmlspecialchars($currentCustomer['username']) ?></h1>
                    <p>Ready to flirt? Browse operators, check your messages, or start a conversation.</p>
                    <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap; margin-top: 2rem;">
                        <a href="/search-operators.php" class="btn btn-primary">Browse Operators</a>
                        <a href="/messages.php" class="btn btn-secondary" style="border-color: #fff; color: #fff;">Check Messages</a>
                        <a href="/chat.php" class="btn btn-secondary" style="border-color: #fff; color: #fff;">Start Chat</a>
                    </div>
                <?php else: ?>
                    <h1>Welcome to NYC Flirts</h1>
                    <p>Are you ready to start flirting? Find your flirt, find them now. On nycflirts.com.</p>
                    <button class="btn btn-primary" onclick="openSignupModal()">Start Flirting Now</button>
                <?php endif; ?>
            </div>
        </section>

        <section id="about" class="categories">
            <h2>Why Flirts NYC</h2>
            <div class="category-grid">
                <div class="category-card">
                    <h3>💳 Discrete Billing</h3>
                    <p>Your privacy matters. All transactions appear discreetly on your statement with no obvious references.</p>
                </div>

                <div class="category-card">
                    <h3>📞 Anonymous Calling</h3>
                    <p>Connect with complete privacy. Your personal information stays protected while you enjoy conversations.</p>
                </div>

                <div class="category-card">
                    <h3>⭐ Experienced Operators</h3>
                    <p>Our professional operators are skilled in creating engaging, memorable experiences tailored to you.</p>
                </div>

                <div class="category-card">
                    <h3>🚀 State of the Art Platform</h3>
                    <p>Cutting-edge technology ensures seamless connections, crystal-clear audio, and reliable service.</p>
                </div>

                <div class="category-card">
                    <h3>✨ Most Unique Features</h3>
                    <p>Experience the industry's most innovative features designed to enhance your flirting experience.</p>
                </div>

                <div class="category-card">
                    <h3>🔒 After Dark Systems Network</h3>
                    <p>Part of the trusted After Dark Systems network, ensuring quality, security, and professional service.</p>
                </div>
            </div>

            <div style="text-align: center; margin-top: 3rem;">
                <?php if (!$isLoggedIn): ?>
                    <button class="btn btn-primary" onclick="openSignupModal()" style="font-size: 1.2rem; padding: 1rem 2rem;">Join Flirts NYC Today</button>
                <?php else: ?>
                    <a href="/dashboard.php" class="btn btn-primary" style="font-size: 1.2rem; padding: 1rem 2rem;">Start Flirting</a>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <footer class="footer">
        <p>&copy; 2024 Flirts NYC. All rights reserved.</p>
        <p>Part of the <strong>After Dark Systems</strong> Network</p>
        <p style="font-size: 0.9rem; margin-top: 1rem; opacity: 0.8;">
            Powered by AEIMS Platform - Secure, Discrete, Professional
        </p>
    </footer>

    <!-- Login Modal -->
    <div id="loginModal" class="modal">
        <div class="modal-content">
            <button class="close-modal" onclick="closeLoginModal()">&times;</button>
            <h2>Welcome Back</h2>
            <form id="loginForm" method="POST" action="/auth.php">
                <input type="hidden" name="action" value="login">

                <div class="form-group">
                    <label for="login_username">Username or Email:</label>
                    <input type="text" name="username" id="login_username" required>
                </div>

                <div class="form-group">
                    <label for="login_password">Password:</label>
                    <input type="password" name="password" id="login_password" required>
                </div>

                <div class="modal-actions">
                    <button type="submit" class="btn btn-primary">Login</button>
                    <button type="button" class="btn btn-secondary" onclick="closeLoginModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Signup Modal -->
    <div id="signupModal" class="modal">
        <div class="modal-content">
            <button class="close-modal" onclick="closeSignupModal()">&times;</button>
            <h2>Join <?= htmlspecialchars($site['name']) ?></h2>
            <form id="signupForm" method="POST" action="/auth.php">
                <input type="hidden" name="action" value="signup">

                <div class="form-group">
                    <label for="signup_username">Username:</label>
                    <input type="text" name="username" id="signup_username" required>
                </div>

                <div class="form-group">
                    <label for="signup_email">Email:</label>
                    <input type="email" name="email" id="signup_email" required>
                </div>

                <div class="form-group">
                    <label for="signup_password">Password:</label>
                    <input type="password" name="password" id="signup_password" required>
                </div>

                <div class="form-group">
                    <label for="signup_confirm_password">Confirm Password:</label>
                    <input type="password" name="confirm_password" id="signup_confirm_password" required>
                </div>

                <div class="modal-actions">
                    <button type="submit" class="btn btn-primary">Create Account</button>
                    <button type="button" class="btn btn-secondary" onclick="closeSignupModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openLoginModal() {
            document.getElementById('loginModal').style.display = 'block';
        }

        function closeLoginModal() {
            document.getElementById('loginModal').style.display = 'none';
        }

        function openSignupModal() {
            document.getElementById('signupModal').style.display = 'block';
        }

        function closeSignupModal() {
            document.getElementById('signupModal').style.display = 'none';
        }

        // Close modals when clicking outside
        window.addEventListener('click', function(event) {
            const loginModal = document.getElementById('loginModal');
            const signupModal = document.getElementById('signupModal');

            if (event.target === loginModal) {
                closeLoginModal();
            } else if (event.target === signupModal) {
                closeSignupModal();
            }
        });

        // Form validation
        document.getElementById('signupForm').addEventListener('submit', function(e) {
            const password = document.getElementById('signup_password').value;
            const confirmPassword = document.getElementById('signup_confirm_password').value;

            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
            }
        });
    </script>
</body>
</html>