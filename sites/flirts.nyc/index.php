<?php
/**
 * Flirts NYC - Customer Site Homepage
 * AEIMS Multi-Site Architecture - Customer Portal
 */

session_start();

// Load SSO middleware first
require_once 'sso/middleware.php';

// Load site configuration
require_once '../../services/SiteManager.php';
require_once '../../services/OperatorManager.php';

// Check for SSO auto-login
checkSSOSession();

try {
    $siteManager = new \AEIMS\Services\SiteManager();
    $operatorManager = new \AEIMS\Services\OperatorManager();

    // Dynamically determine site from HTTP_HOST or SERVER_NAME
    $hostname = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'flirts.nyc';
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
            color: <?= $site['theme']['text_color'] ?>;
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
            color: <?= $site['theme']['text_color'] ?>;
            text-decoration: none;
            transition: color 0.3s ease;
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
    <header class="header">
        <div class="nav-container">
            <a href="/" class="logo"><?= htmlspecialchars($site['name']) ?></a>

            <nav>
                <ul class="nav-menu">
                    <li><a href="#about">About Us</a></li>
                    <?php if ($isLoggedIn): ?>
                        <li><a href="/dashboard.php">Dashboard</a></li>
                        <li><a href="/logout.php">Logout</a></li>
                    <?php else: ?>
                        <li><a href="#" onclick="openLoginModal()">Sign In</a></li>
                    <?php endif; ?>
                </ul>
            </nav>

            <div class="auth-buttons">
                <?php if ($isLoggedIn): ?>
                    <div class="user-profile">
                        <span>Welcome, <?= htmlspecialchars($currentCustomer['username']) ?></span>
                        <a href="/dashboard.php" class="btn btn-primary">Enter</a>
                    </div>
                <?php else: ?>
                    <button class="btn btn-secondary" onclick="openLoginModal()">Sign In</button>
                    <button class="btn btn-primary" onclick="openSignupModal()">Join Now</button>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main>
        <section class="hero">
            <div class="hero-content">
                <h1>Welcome to Flirts NYC</h1>
                <p>Are you ready to start flirting? Find your flirt, find them now. On flirts.nyc.</p>
                <?php if (!$isLoggedIn): ?>
                    <button class="btn btn-primary" onclick="openSignupModal()">Start Flirting Now</button>
                <?php else: ?>
                    <a href="/dashboard.php" class="btn btn-primary">Enter Flirts NYC</a>
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