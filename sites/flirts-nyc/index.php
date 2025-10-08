<?php
/**
 * Flirts.nyc - Customer Dating Site
 * NYC-focused adult dating and entertainment platform
 */

// Ensure we're on the correct domain
$host = $_SERVER['HTTP_HOST'] ?? '';
if (strpos($host, 'flirts.nyc') === false) {
    header('Location: https://flirts.nyc' . $_SERVER['REQUEST_URI']);
    exit();
}

// Include AEIMS functionality
require_once '../../includes/SiteSpecificAuth.php';
$auth = new SiteSpecificAuth();
$siteConfig = $auth->getSiteConfig();

session_start();

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']) && isset($_SESSION['user_type']);
$userType = $_SESSION['user_type'] ?? 'customer';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flirts.nyc - Find Your Perfect Match in NYC</title>
    <meta name="description" content="Connect with beautiful singles in New York City. Video chat, text messaging, and live interactions with verified local singles.">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="assets/site.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="customer-site nyc-theme">
    <!-- Age Verification Banner -->
    <div id="age-verification-banner" class="age-verification-banner">
        <div class="banner-content">
            <div class="banner-text">
                <strong>Age Verification Required</strong><br>
                You must be 18 or older to access Flirts.nyc. By continuing, you confirm you are of legal age and agree to our terms.
            </div>
            <div class="banner-actions">
                <button id="accept-terms" class="btn btn-primary">I am 18+ and Accept</button>
                <button id="decline-terms" class="btn btn-outline">Exit Site</button>
            </div>
        </div>
    </div>

    <header class="site-header">
        <nav class="nav">
            <div class="nav-brand">
                <h1>Flirts<span class="accent">.nyc</span></h1>
                <span class="tagline">Find Your NYC Connection</span>
            </div>
            <ul class="nav-menu">
                <li><a href="#browse">Browse</a></li>
                <li><a href="#features">Features</a></li>
                <li><a href="#pricing">Pricing</a></li>
                <?php if ($isLoggedIn): ?>
                <li><a href="dashboard.php">My Account</a></li>
                <li><a href="../../logout.php">Logout</a></li>
                <?php else: ?>
                <li><a href="login.php" class="nav-login">Login</a></li>
                <li><a href="signup.php" class="nav-signup">Join Now</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>

    <main>
        <?php if (!$isLoggedIn): ?>
        <!-- Landing Page for Visitors -->
        <section class="hero">
            <div class="hero-background"></div>
            <div class="container">
                <div class="hero-content">
                    <h1 class="hero-title">
                        Meet Amazing Singles in <span class="gradient-text">New York City</span>
                    </h1>
                    <p class="hero-description">
                        Connect with verified local singles through live video chat, text messaging, and premium adult entertainment.
                        Experience genuine connections in the city that never sleeps.
                    </p>
                    <div class="hero-stats">
                        <div class="stat">
                            <span class="stat-number">2,847</span>
                            <span class="stat-label">Active Members</span>
                        </div>
                        <div class="stat">
                            <span class="stat-number">150+</span>
                            <span class="stat-label">Online Now</span>
                        </div>
                        <div class="stat">
                            <span class="stat-number">24/7</span>
                            <span class="stat-label">Live Support</span>
                        </div>
                    </div>
                    <div class="hero-cta">
                        <a href="signup.php" class="btn btn-primary btn-large">Join Free Now</a>
                        <a href="#browse" class="btn btn-secondary">Browse Profiles</a>
                    </div>
                </div>
            </div>
        </section>

        <section id="browse" class="browse-preview">
            <div class="container">
                <h2 class="section-title">Meet Local Singles</h2>
                <div class="profiles-grid">
                    <div class="profile-card">
                        <div class="profile-image">
                            <img src="../../assets/images/sample-profile-1.jpg" alt="Profile" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgdmlld0JveD0iMCAwIDIwMCAyMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjIwMCIgaGVpZ2h0PSIyMDAiIGZpbGw9IiNGMEY0RjgiLz48cGF0aCBkPSJNMTAwIDkwQzExMC40NTcgOTAgMTE5IDgxLjQ1NyAxMTkgNzFDMTE5IDYwLjU0MyAxMTAuNDU3IDUyIDEwMCA1MkM4OS41NDMgNTIgODEgNjAuNTQzIDgxIDcxQzgxIDgxLjQ1NyA4OS41NDMgOTAgMTAwIDkwWiIgZmlsbD0iIzlDQTNBRiIvPjxwYXRoIGQ9Ik02OC4yIDEzOEg2OC4yNUM3MS4zMTY3IDEyNS41IDgwLjQ2NjcgMTEzIDEwMCAxMTNDMTE5LjUzMyAxMTMgMTI4LjY4MyAxMjUuNSAxMzEuOCAxMzhIMTMxLjgiIGZpbGw9IiM5Q0EzQUYiLz48L3N2Zz4='">
                            <span class="status-indicator online"></span>
                        </div>
                        <div class="profile-info">
                            <h3>Jessica, 25</h3>
                            <p>Manhattan • Online now</p>
                        </div>
                    </div>

                    <div class="profile-card">
                        <div class="profile-image">
                            <img src="../../assets/images/sample-profile-2.jpg" alt="Profile" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgdmlld0JveD0iMCAwIDIwMCAyMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjIwMCIgaGVpZ2h0PSIyMDAiIGZpbGw9IiNGMEY0RjgiLz48cGF0aCBkPSJNMTAwIDkwQzExMC40NTcgOTAgMTE5IDgxLjQ1NyAxMTkgNzFDMTE5IDYwLjU0MyAxMTAuNDU3IDUyIDEwMCA1MkM4OS41NDMgNTIgODEgNjAuNTQzIDgxIDcxQzgxIDgxLjQ1NyA4OS41NDMgOTAgMTAwIDkwWiIgZmlsbD0iIzlDQTNBRiIvPjxwYXRoIGQ9Ik02OC4yIDEzOEg2OC4yNUM3MS4zMTY3IDEyNS41IDgwLjQ2NjcgMTEzIDEwMCAxMTNDMTE5LjUzMyAxMTMgMTI4LjY4MyAxMjUuNSAxMzEuOCAxMzhIMTMxLjgiIGZpbGw9IiM5Q0EzQUYiLz48L3N2Zz4='">
                            <span class="status-indicator busy"></span>
                        </div>
                        <div class="profile-info">
                            <h3>Sophia, 28</h3>
                            <p>Brooklyn • In private chat</p>
                        </div>
                    </div>

                    <div class="profile-card">
                        <div class="profile-image">
                            <img src="../../assets/images/sample-profile-3.jpg" alt="Profile" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgdmlld0JveD0iMCAwIDIwMCAyMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjIwMCIgaGVpZ2h0PSIyMDAiIGZpbGw9IiNGMEY0RjgiLz48cGF0aCBkPSJNMTAwIDkwQzExMC40NTcgOTAgMTE5IDgxLjQ1NyAxMTkgNzFDMTE5IDYwLjU0MyAxMTAuNDU3IDUyIDEwMCA1MkM4OS41NDMgNTIgODEgNjAuNTQzIDgxIDcxQzgxIDgxLjQ1NyA4OS41NDMgOTAgMTAwIDkwWiIgZmlsbD0iIzlDQTNBRiIvPjxwYXRoIGQ9Ik02OC4yIDEzOEg2OC4yNUM3MS4zMTY3IDEyNS41IDgwLjQ2NjcgMTEzIDEwMCAxMTNDMTE5LjUzMyAxMTMgMTI4LjY4MyAxMjUuNSAxMzEuOCAxMzhIMTMxLjgiIGZpbGw9IiM5Q0EzQUYiLz48L3N2Zz4='">
                            <span class="status-indicator online"></span>
                        </div>
                        <div class="profile-info">
                            <h3>Ashley, 23</h3>
                            <p>Queens • Online now</p>
                        </div>
                    </div>

                    <div class="profile-card">
                        <div class="profile-image">
                            <img src="../../assets/images/sample-profile-4.jpg" alt="Profile" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgdmlld0JveD0iMCAwIDIwMCAyMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjIwMCIgaGVpZ2h0PSIyMDAiIGZpbGw9IiNGMEY0RjgiLz48cGF0aCBkPSJNMTAwIDkwQzExMC40NTcgOTAgMTE5IDgxLjQ1NyAxMTkgNzFDMTE5IDYwLjU0MyAxMTAuNDU3IDUyIDEwMCA1MkM4OS41NDMgNTIgODEgNjAuNTQzIDgxIDcxQzgxIDgxLjQ1NyA4OS41NDMgOTAgMTAwIDkwWiIgZmlsbD0iIzlDQTNBRiIvPjxwYXRoIGQ9Ik02OC4yIDEzOEg2OC4yNUM3MS4zMTY3IDEyNS41IDgwLjQ2NjcgMTEzIDEwMCAxMTNDMTE5LjUzMyAxMTMgMTI4LjY4MyAxMjUuNSAxMzEuOCAxMzhIMTMxLjgiIGZpbGw9IiM5Q0EzQUYiLz48L3N2Zz4='">
                            <span class="status-indicator away"></span>
                        </div>
                        <div class="profile-info">
                            <h3>Madison, 26</h3>
                            <p>Bronx • Away</p>
                        </div>
                    </div>
                </div>
                <div class="browse-cta">
                    <a href="signup.php" class="btn btn-primary">View All Profiles</a>
                </div>
            </div>
        </section>
        <?php else: ?>
        <!-- Dashboard for Logged-in Users -->
        <section class="dashboard">
            <div class="container">
                <h2>Welcome back, <?php echo htmlspecialchars($_SESSION['name'] ?? $_SESSION['username']); ?>!</h2>
                <div class="dashboard-grid">
                    <div class="dashboard-card">
                        <h3>🔍 Browse Profiles</h3>
                        <p>Discover new connections</p>
                        <a href="browse.php" class="btn btn-primary">Start Browsing</a>
                    </div>
                    <div class="dashboard-card">
                        <h3>💬 My Messages</h3>
                        <p>Continue conversations</p>
                        <a href="messages.php" class="btn btn-primary">View Messages</a>
                    </div>
                    <div class="dashboard-card">
                        <h3>📹 Live Video</h3>
                        <p>Start video chat</p>
                        <a href="video.php" class="btn btn-primary">Go Live</a>
                    </div>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <section id="features" class="features">
            <div class="container">
                <h2 class="section-title">Premium Features</h2>
                <div class="features-grid">
                    <div class="feature-card">
                        <div class="feature-icon">📹</div>
                        <h3>Live Video Chat</h3>
                        <p>High-quality video calls with beautiful singles</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">💬</div>
                        <h3>Instant Messaging</h3>
                        <p>Real-time text chat with photo sharing</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">🔒</div>
                        <h3>Secure & Discrete</h3>
                        <p>Your privacy is our top priority</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">🏙️</div>
                        <h3>NYC Focused</h3>
                        <p>Connect with local New York singles</p>
                    </div>
                </div>
            </div>
        </section>

        <section id="pricing" class="pricing">
            <div class="container">
                <h2 class="section-title">Simple Pricing</h2>
                <div class="pricing-card">
                    <h3>Credits System</h3>
                    <p>Pay only for what you use</p>
                    <ul>
                        <li>Text messages: $0.50 each</li>
                        <li>Video chat: $2.99/minute</li>
                        <li>Photo sharing: $0.99 each</li>
                    </ul>
                    <a href="credits.php" class="btn btn-primary">Buy Credits</a>
                </div>
            </div>
        </section>
    </main>

    <footer class="site-footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-brand">
                    <h3>Flirts.nyc</h3>
                    <p>Your NYC connection awaits</p>
                </div>
                <div class="footer-links">
                    <h4>Support</h4>
                    <ul>
                        <li><a href="help.php">Help Center</a></li>
                        <li><a href="contact.php">Contact Us</a></li>
                        <li><a href="safety.php">Safety Tips</a></li>
                    </ul>
                </div>
                <div class="footer-links">
                    <h4>Legal</h4>
                    <ul>
                        <li><a href="terms.php">Terms of Service</a></li>
                        <li><a href="privacy.php">Privacy Policy</a></li>
                        <li><a href="billing.php">Billing Terms</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> Flirts.nyc. All rights reserved. 18+ only.</p>
            </div>
        </div>
    </footer>

    <style>
        .customer-site.nyc-theme {
            font-family: 'Inter', sans-serif;
        }

        .site-header {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
            color: white;
            padding: 1rem 0;
        }

        .nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        .nav-brand h1 {
            margin: 0;
            font-size: 2rem;
            font-weight: 700;
        }

        .accent {
            color: #feca57;
        }

        .tagline {
            font-size: 0.875rem;
            opacity: 0.9;
        }

        .nav-menu {
            display: flex;
            list-style: none;
            margin: 0;
            padding: 0;
            gap: 2rem;
        }

        .nav-menu a {
            color: white;
            text-decoration: none;
            font-weight: 500;
        }

        .nav-login, .nav-signup {
            background: rgba(255, 255, 255, 0.1);
            padding: 0.5rem 1rem;
            border-radius: 6px;
        }

        .hero {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 4rem 0;
            position: relative;
            overflow: hidden;
        }

        .hero-background {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="0.5"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            position: relative;
        }

        .hero-title {
            font-size: 3rem;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .gradient-text {
            background: linear-gradient(45deg, #feca57, #ff9ff3);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero-description {
            font-size: 1.25rem;
            text-align: center;
            max-width: 600px;
            margin: 0 auto 2rem auto;
            opacity: 0.9;
        }

        .hero-stats {
            display: flex;
            justify-content: center;
            gap: 3rem;
            margin-bottom: 3rem;
        }

        .stat {
            text-align: center;
        }

        .stat-number {
            display: block;
            font-size: 2rem;
            font-weight: 700;
            color: #feca57;
        }

        .stat-label {
            font-size: 0.875rem;
            opacity: 0.8;
        }

        .hero-cta {
            display: flex;
            justify-content: center;
            gap: 1rem;
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
            background: #ff6b6b;
            color: white;
        }

        .btn-primary:hover {
            background: #ee5a24;
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: transparent;
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        .btn-large {
            padding: 1rem 2rem;
            font-size: 1.125rem;
        }

        .browse-preview {
            padding: 4rem 0;
            background: #f8f9fa;
        }

        .section-title {
            text-align: center;
            font-size: 2.5rem;
            margin-bottom: 3rem;
            color: #2d3436;
        }

        .profiles-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .profile-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
        }

        .profile-card:hover {
            transform: translateY(-5px);
        }

        .profile-image {
            position: relative;
            width: 100px;
            height: 100px;
            border-radius: 50%;
            margin: 0 auto 1rem auto;
            overflow: hidden;
        }

        .profile-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .status-indicator {
            position: absolute;
            bottom: 5px;
            right: 5px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 3px solid white;
        }

        .status-indicator.online {
            background: #00b894;
        }

        .status-indicator.busy {
            background: #e17055;
        }

        .status-indicator.away {
            background: #fdcb6e;
        }

        .profile-info h3 {
            margin: 0 0 0.5rem 0;
            color: #2d3436;
        }

        .profile-info p {
            color: #636e72;
            margin: 0;
        }

        .browse-cta {
            text-align: center;
        }

        .features {
            padding: 4rem 0;
            background: white;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
        }

        .feature-card {
            text-align: center;
            padding: 2rem;
        }

        .feature-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .feature-card h3 {
            margin-bottom: 1rem;
            color: #2d3436;
        }

        .pricing {
            padding: 4rem 0;
            background: #f8f9fa;
        }

        .pricing-card {
            max-width: 400px;
            margin: 0 auto;
            background: white;
            padding: 2rem;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .pricing-card ul {
            list-style: none;
            padding: 0;
            margin: 1.5rem 0;
        }

        .pricing-card li {
            padding: 0.5rem 0;
            border-bottom: 1px solid #e9ecef;
        }

        .site-footer {
            background: #2d3436;
            color: white;
            padding: 3rem 0 1rem 0;
        }

        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .footer-links ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .footer-links a {
            color: #b2bec3;
            text-decoration: none;
        }

        .footer-bottom {
            text-align: center;
            padding-top: 2rem;
            border-top: 1px solid #636e72;
            color: #b2bec3;
        }

        /* Age verification banner styles */
        .age-verification-banner {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(135deg, #e17055 0%, #d63031 100%);
            color: white;
            z-index: 10000;
            box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.3);
        }

        .banner-content {
            padding: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            max-width: 1200px;
            margin: 0 auto;
            gap: 2rem;
        }

        .banner-actions {
            display: flex;
            gap: 1rem;
        }

        .banner-actions .btn {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }

        .banner-actions .btn-outline {
            background: transparent;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        @media (max-width: 768px) {
            .banner-content {
                flex-direction: column;
                text-align: center;
            }

            .banner-actions {
                width: 100%;
                justify-content: center;
            }

            .hero-stats {
                flex-direction: column;
                gap: 1rem;
            }

            .hero-cta {
                flex-direction: column;
                align-items: center;
            }

            .nav-menu {
                display: none;
            }
        }
    </style>

    <script>
        // Age verification banner
        document.addEventListener('DOMContentLoaded', function() {
            const banner = document.getElementById('age-verification-banner');
            const acceptBtn = document.getElementById('accept-terms');
            const declineBtn = document.getElementById('decline-terms');

            if (localStorage.getItem('flirts_nyc_age_verified') === 'true') {
                banner.style.display = 'none';
            }

            acceptBtn.addEventListener('click', function() {
                localStorage.setItem('flirts_nyc_age_verified', 'true');
                banner.style.display = 'none';
            });

            declineBtn.addEventListener('click', function() {
                window.location.href = 'https://www.google.com';
            });
        });
    </script>
</body>
</html>