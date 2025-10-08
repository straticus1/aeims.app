<?php
/**
 * NYCFlirts.com - Customer Dating Site
 * New York City adult dating and entertainment platform
 */

// Ensure we're on the correct domain
$host = $_SERVER['HTTP_HOST'] ?? '';
if (strpos($host, 'nycflirts.com') === false) {
    header('Location: https://nycflirts.com' . $_SERVER['REQUEST_URI']);
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
    <title>NYC Flirts - Premier Adult Entertainment in New York City</title>
    <meta name="description" content="Experience premium adult entertainment with verified NYC singles. Live video chat, messaging, and intimate connections in New York City.">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="assets/site.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="customer-site premium-theme">
    <!-- Age Verification Banner -->
    <div id="age-verification-banner" class="age-verification-banner">
        <div class="banner-content">
            <div class="banner-text">
                <strong>Adult Content Warning</strong><br>
                This site contains adult content. You must be 18 or older and agree to our terms to continue.
            </div>
            <div class="banner-actions">
                <button id="accept-terms" class="btn btn-primary">I'm 18+ and Agree</button>
                <button id="decline-terms" class="btn btn-outline">Leave Site</button>
            </div>
        </div>
    </div>

    <header class="site-header">
        <nav class="nav">
            <div class="nav-brand">
                <h1><span class="nyc">NYC</span>Flirts</h1>
                <span class="tagline">Premium NYC Experience</span>
            </div>
            <ul class="nav-menu">
                <li><a href="#models">Models</a></li>
                <li><a href="#features">Features</a></li>
                <li><a href="#vip">VIP</a></li>
                <?php if ($isLoggedIn): ?>
                <li><a href="dashboard.php">My Account</a></li>
                <li><a href="../../logout.php">Logout</a></li>
                <?php else: ?>
                <li><a href="login.php" class="nav-login">Member Login</a></li>
                <li><a href="signup.php" class="nav-signup">Join VIP</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>

    <main>
        <?php if (!$isLoggedIn): ?>
        <!-- Premium Landing Page -->
        <section class="hero">
            <div class="hero-video-bg">
                <div class="video-overlay"></div>
            </div>
            <div class="container">
                <div class="hero-content">
                    <h1 class="hero-title">
                        Elite Adult Entertainment<br>
                        <span class="gradient-text">In the Heart of NYC</span>
                    </h1>
                    <p class="hero-description">
                        Connect with sophisticated, verified models for premium video chat experiences.
                        Discrete, secure, and exclusively for discerning gentlemen.
                    </p>
                    <div class="hero-stats">
                        <div class="stat">
                            <span class="stat-number">200+</span>
                            <span class="stat-label">Elite Models</span>
                        </div>
                        <div class="stat">
                            <span class="stat-number">4K</span>
                            <span class="stat-label">HD Quality</span>
                        </div>
                        <div class="stat">
                            <span class="stat-number">100%</span>
                            <span class="stat-label">Discrete</span>
                        </div>
                    </div>
                    <div class="hero-cta">
                        <a href="signup.php" class="btn btn-primary btn-large">Join VIP Club</a>
                        <a href="#models" class="btn btn-secondary">Preview Models</a>
                    </div>
                </div>
            </div>
        </section>

        <section id="models" class="models-showcase">
            <div class="container">
                <h2 class="section-title">Featured Elite Models</h2>
                <p class="section-subtitle">Verified, sophisticated, and exclusively available to our VIP members</p>

                <div class="models-grid">
                    <div class="model-card premium">
                        <div class="model-image">
                            <img src="../../assets/images/model-1.jpg" alt="Elite Model" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzAwIiBoZWlnaHQ9IjQwMCIgdmlld0JveD0iMCAwIDMwMCA0MDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjMwMCIgaGVpZ2h0PSI0MDAiIGZpbGw9IiNGMEY0RjgiLz48cGF0aCBkPSJNMTUwIDEzNUMxNjUuNjg1IDEzNSAxNzggMTIyLjY4NSAxNzggMTA3QzE3OCA5MS4zMTUgMTY1LjY4NSA3OSAxNTAgNzlDMTM0LjMxNSA3OSAxMjIgOTEuMzE1IDEyMiAxMDdDMTIyIDEyMi42ODUgMTM0LjMxNSAxMzUgMTUwIDEzNVoiIGZpbGw9IiM5Q0EzQUYiLz48cGF0aCBkPSJNMTAzLjMgMjA3SDE5Ni43QzE5Ni43IDE4MC41IDE4NS4yIDE3MCAxNTAgMTcwQzExNC44IDE3MCAxMDMuMyAxODAuNSAxMDMuMyAyMDdaIiBmaWxsPSIjOUNBM0FGIi8+PC9zdmc+'">
                            <div class="model-overlay">
                                <span class="status-badge vip">VIP</span>
                                <span class="rating">★ 4.9</span>
                            </div>
                        </div>
                        <div class="model-info">
                            <h3>Valentina</h3>
                            <p class="model-age">25 • Manhattan</p>
                            <p class="model-specialties">Video Chat • Private Shows</p>
                            <div class="model-price">$4.99/min</div>
                        </div>
                    </div>

                    <div class="model-card premium">
                        <div class="model-image">
                            <img src="../../assets/images/model-2.jpg" alt="Elite Model" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzAwIiBoZWlnaHQ9IjQwMCIgdmlld0JveD0iMCAwIDMwMCA0MDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjMwMCIgaGVpZ2h0PSI0MDAiIGZpbGw9IiNGMEY0RjgiLz48cGF0aCBkPSJNMTUwIDEzNUMxNjUuNjg1IDEzNSAxNzggMTIyLjY4NSAxNzggMTA3QzE3OCA5MS4zMTUgMTY1LjY4NSA3OSAxNTAgNzlDMTM0LjMxNSA3OSAxMjIgOTEuMzE1IDEyMiAxMDdDMTIyIDEyMi42ODUgMTM0LjMxNSAxMzUgMTUwIDEzNVoiIGZpbGw9IiM5Q0EzQUYiLz48cGF0aCBkPSJNMTAzLjMgMjA3SDE5Ni43QzE5Ni43IDE4MC41IDE4NS4yIDE3MCAxNTAgMTcwQzExNC44IDE3MCAxMDMuMyAxODAuNSAxMDMuMyAyMDdaIiBmaWxsPSIjOUNBM0FGIi8+PC9zdmc+'">
                            <div class="model-overlay">
                                <span class="status-badge online">LIVE</span>
                                <span class="rating">★ 4.8</span>
                            </div>
                        </div>
                        <div class="model-info">
                            <h3>Isabella</h3>
                            <p class="model-age">27 • Brooklyn</p>
                            <p class="model-specialties">Intimate Chat • Roleplay</p>
                            <div class="model-price">$3.99/min</div>
                        </div>
                    </div>

                    <div class="model-card premium">
                        <div class="model-image">
                            <img src="../../assets/images/model-3.jpg" alt="Elite Model" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzAwIiBoZWlnaHQ9IjQwMCIgdmlld0JveD0iMCAwIDMwMCA0MDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjMwMCIgaGVpZ2h0PSI0MDAiIGZpbGw9IiNGMEY0RjgiLz48cGF0aCBkPSJNMTUwIDEzNUMxNjUuNjg1IDEzNSAxNzggMTIyLjY4NSAxNzggMTA3QzE3OCA5MS4zMTUgMTY1LjY4NSA3OSAxNTAgNzlDMTM0LjMxNSA3OSAxMjIgOTEuMzE1IDEyMiAxMDdDMTIyIDEyMi42ODUgMTM0LjMxNSAxMzUgMTUwIDEzNVoiIGZpbGw9IiM5Q0EzQUYiLz48cGF0aCBkPSJNMTAzLjMgMjA3SDE5Ni43QzE5Ni43IDE4MC41IDE4NS4yIDE3MCAxNTAgMTcwQzExNC44IDE3MCAxMDMuMyAxODAuNSAxMDMuMyAyMDdaIiBmaWxsPSIjOUNBM0FGIi8+PC9zdmc+'">
                            <div class="model-overlay">
                                <span class="status-badge exclusive">EXCLUSIVE</span>
                                <span class="rating">★ 5.0</span>
                            </div>
                        </div>
                        <div class="model-info">
                            <h3>Sophia</h3>
                            <p class="model-age">24 • Upper East Side</p>
                            <p class="model-specialties">Private Shows • VIP Only</p>
                            <div class="model-price">$6.99/min</div>
                        </div>
                    </div>
                </div>

                <div class="models-cta">
                    <p>Want to see all 200+ elite models?</p>
                    <a href="signup.php" class="btn btn-primary">Join VIP Now</a>
                </div>
            </div>
        </section>
        <?php else: ?>
        <!-- VIP Member Dashboard -->
        <section class="vip-dashboard">
            <div class="container">
                <div class="welcome-header">
                    <h2>Welcome back, VIP Member <?php echo htmlspecialchars($_SESSION['name'] ?? $_SESSION['username']); ?></h2>
                    <div class="credits-display">
                        <span class="credits-label">Credits:</span>
                        <span class="credits-amount">$47.50</span>
                        <a href="add-credits.php" class="btn btn-small">Add Credits</a>
                    </div>
                </div>

                <div class="dashboard-grid">
                    <div class="dashboard-card featured">
                        <h3>🔴 Live Models</h3>
                        <p>23 models online now</p>
                        <a href="live-models.php" class="btn btn-primary">Enter Live Rooms</a>
                    </div>
                    <div class="dashboard-card">
                        <h3>💬 Private Messages</h3>
                        <p>3 new messages</p>
                        <a href="messages.php" class="btn btn-primary">View Messages</a>
                    </div>
                    <div class="dashboard-card">
                        <h3>⭐ Favorites</h3>
                        <p>Your saved models</p>
                        <a href="favorites.php" class="btn btn-primary">View Favorites</a>
                    </div>
                    <div class="dashboard-card">
                        <h3>📱 Mobile App</h3>
                        <p>Download for iOS/Android</p>
                        <a href="mobile.php" class="btn btn-primary">Get App</a>
                    </div>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <section id="features" class="features">
            <div class="container">
                <h2 class="section-title">Premium VIP Features</h2>
                <div class="features-grid">
                    <div class="feature-card premium">
                        <div class="feature-icon">🎥</div>
                        <h3>4K HD Video</h3>
                        <p>Ultra-high definition video streaming for the ultimate experience</p>
                    </div>
                    <div class="feature-card premium">
                        <div class="feature-icon">🔒</div>
                        <h3>100% Discrete</h3>
                        <p>Anonymous billing and complete privacy protection</p>
                    </div>
                    <div class="feature-card premium">
                        <div class="feature-icon">🌟</div>
                        <h3>Verified Models</h3>
                        <p>All models are 18+, verified, and professionally screened</p>
                    </div>
                    <div class="feature-card premium">
                        <div class="feature-icon">💎</div>
                        <h3>VIP Exclusives</h3>
                        <p>Access to exclusive content and private shows</p>
                    </div>
                </div>
            </div>
        </section>

        <section id="vip" class="vip-membership">
            <div class="container">
                <h2 class="section-title">VIP Membership</h2>
                <div class="vip-tiers">
                    <div class="tier-card basic">
                        <h3>Basic</h3>
                        <div class="tier-price">Free</div>
                        <ul>
                            <li>Browse model profiles</li>
                            <li>Send messages ($0.50 each)</li>
                            <li>Standard video quality</li>
                        </ul>
                        <a href="signup.php" class="btn btn-outline">Sign Up Free</a>
                    </div>

                    <div class="tier-card premium featured">
                        <h3>VIP Gold</h3>
                        <div class="tier-price">$29.99/month</div>
                        <ul>
                            <li>Unlimited messaging</li>
                            <li>HD video quality</li>
                            <li>Priority customer support</li>
                            <li>Exclusive model access</li>
                        </ul>
                        <a href="signup.php?tier=gold" class="btn btn-primary">Join VIP Gold</a>
                    </div>

                    <div class="tier-card platinum">
                        <h3>VIP Platinum</h3>
                        <div class="tier-price">$49.99/month</div>
                        <ul>
                            <li>Everything in Gold</li>
                            <li>4K Ultra HD video</li>
                            <li>Private show discounts</li>
                            <li>Dedicated VIP concierge</li>
                        </ul>
                        <a href="signup.php?tier=platinum" class="btn btn-premium">Join VIP Platinum</a>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="site-footer premium">
        <div class="container">
            <div class="footer-content">
                <div class="footer-brand">
                    <h3>NYC Flirts</h3>
                    <p>Premium adult entertainment</p>
                </div>
                <div class="footer-links">
                    <h4>Members</h4>
                    <ul>
                        <li><a href="help.php">VIP Support</a></li>
                        <li><a href="contact.php">Contact</a></li>
                        <li><a href="mobile.php">Mobile App</a></li>
                    </ul>
                </div>
                <div class="footer-links">
                    <h4>Legal</h4>
                    <ul>
                        <li><a href="terms.php">Terms</a></li>
                        <li><a href="privacy.php">Privacy</a></li>
                        <li><a href="age-verification.php">Age Verification</a></li>
                    </ul>
                </div>
                <div class="footer-contact">
                    <h4>24/7 VIP Support</h4>
                    <p>support@nycflirts.com</p>
                    <p>1-800-NYC-FLRT</p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> NYC Flirts. All rights reserved. 18+ only. Adults only content.</p>
                <div class="payment-methods">
                    <span>💳 Visa</span>
                    <span>💳 MasterCard</span>
                    <span>💳 Discover</span>
                    <span>🔒 Secure</span>
                </div>
            </div>
        </div>
    </footer>

    <style>
        .customer-site.premium-theme {
            font-family: 'Inter', sans-serif;
            background: #0a0a0a;
            color: #ffffff;
        }

        .site-header {
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
            border-bottom: 2px solid #d4af37;
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
            font-size: 2.5rem;
            font-weight: 700;
            color: #ffffff;
        }

        .nyc {
            color: #d4af37;
        }

        .tagline {
            font-size: 0.875rem;
            color: #d4af37;
            font-style: italic;
        }

        .nav-menu {
            display: flex;
            list-style: none;
            margin: 0;
            padding: 0;
            gap: 2rem;
        }

        .nav-menu a {
            color: #ffffff;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
        }

        .nav-menu a:hover {
            color: #d4af37;
        }

        .nav-login, .nav-signup {
            background: linear-gradient(135deg, #d4af37 0%, #f4d03f 100%);
            color: #000000 !important;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-weight: 600;
        }

        .hero {
            background: linear-gradient(rgba(0,0,0,0.7), rgba(0,0,0,0.7)), url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><rect width="100" height="100" fill="%23000"/></svg>');
            padding: 6rem 0;
            position: relative;
            overflow: hidden;
        }

        .hero-video-bg {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
        }

        .video-overlay {
            background: linear-gradient(45deg, rgba(212, 175, 55, 0.1), rgba(0, 0, 0, 0.8));
            width: 100%;
            height: 100%;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            position: relative;
            z-index: 2;
        }

        .hero-title {
            font-size: 3.5rem;
            margin-bottom: 1.5rem;
            text-align: center;
            font-weight: 700;
        }

        .gradient-text {
            background: linear-gradient(45deg, #d4af37, #f4d03f);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero-description {
            font-size: 1.25rem;
            text-align: center;
            max-width: 700px;
            margin: 0 auto 3rem auto;
            opacity: 0.9;
            line-height: 1.6;
        }

        .hero-stats {
            display: flex;
            justify-content: center;
            gap: 4rem;
            margin-bottom: 3rem;
        }

        .stat {
            text-align: center;
        }

        .stat-number {
            display: block;
            font-size: 2.5rem;
            font-weight: 700;
            color: #d4af37;
        }

        .stat-label {
            font-size: 1rem;
            opacity: 0.8;
        }

        .hero-cta {
            display: flex;
            justify-content: center;
            gap: 1.5rem;
        }

        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
        }

        .btn-primary {
            background: linear-gradient(135deg, #d4af37 0%, #f4d03f 100%);
            color: #000000;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(212, 175, 55, 0.3);
        }

        .btn-secondary {
            background: transparent;
            color: #ffffff;
            border: 2px solid #d4af37;
        }

        .btn-large {
            padding: 1rem 2rem;
            font-size: 1.125rem;
        }

        .models-showcase {
            padding: 6rem 0;
            background: #111111;
        }

        .section-title {
            text-align: center;
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #ffffff;
        }

        .section-subtitle {
            text-align: center;
            font-size: 1.125rem;
            color: #d4af37;
            margin-bottom: 4rem;
        }

        .models-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-bottom: 4rem;
        }

        .model-card {
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
            border: 1px solid #d4af37;
            border-radius: 15px;
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .model-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(212, 175, 55, 0.2);
        }

        .model-image {
            position: relative;
            width: 100%;
            height: 400px;
            overflow: hidden;
        }

        .model-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .model-overlay {
            position: absolute;
            top: 1rem;
            left: 1rem;
            right: 1rem;
            display: flex;
            justify-content: space-between;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-badge.vip {
            background: #d4af37;
            color: #000000;
        }

        .status-badge.online {
            background: #00ff00;
            color: #000000;
        }

        .status-badge.exclusive {
            background: #ff0080;
            color: #ffffff;
        }

        .rating {
            background: rgba(0, 0, 0, 0.7);
            color: #d4af37;
            padding: 0.25rem 0.5rem;
            border-radius: 20px;
            font-size: 0.75rem;
        }

        .model-info {
            padding: 1.5rem;
        }

        .model-info h3 {
            margin: 0 0 0.5rem 0;
            font-size: 1.5rem;
            color: #ffffff;
        }

        .model-age {
            color: #d4af37;
            margin-bottom: 0.5rem;
        }

        .model-specialties {
            color: #cccccc;
            margin-bottom: 1rem;
        }

        .model-price {
            font-size: 1.25rem;
            font-weight: 700;
            color: #d4af37;
        }

        .models-cta {
            text-align: center;
        }

        .models-cta p {
            font-size: 1.125rem;
            margin-bottom: 1.5rem;
            color: #cccccc;
        }

        .features {
            padding: 6rem 0;
            background: #0a0a0a;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
        }

        .feature-card {
            text-align: center;
            padding: 2rem;
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
            border-radius: 15px;
            border: 1px solid #333333;
        }

        .feature-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .feature-card h3 {
            margin-bottom: 1rem;
            color: #d4af37;
        }

        .vip-membership {
            padding: 6rem 0;
            background: #111111;
        }

        .vip-tiers {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            max-width: 1000px;
            margin: 0 auto;
        }

        .tier-card {
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
            border: 1px solid #333333;
            border-radius: 15px;
            padding: 2rem;
            text-align: center;
        }

        .tier-card.featured {
            border: 2px solid #d4af37;
            transform: scale(1.05);
        }

        .tier-price {
            font-size: 2rem;
            font-weight: 700;
            color: #d4af37;
            margin: 1rem 0;
        }

        .tier-card ul {
            list-style: none;
            padding: 0;
            margin: 2rem 0;
        }

        .tier-card li {
            padding: 0.5rem 0;
            border-bottom: 1px solid #333333;
            color: #cccccc;
        }

        .btn-premium {
            background: linear-gradient(135deg, #8e44ad 0%, #9b59b6 100%);
            color: #ffffff;
        }

        .site-footer.premium {
            background: #000000;
            border-top: 2px solid #d4af37;
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
            color: #cccccc;
            text-decoration: none;
        }

        .footer-links a:hover {
            color: #d4af37;
        }

        .footer-bottom {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 2rem;
            border-top: 1px solid #333333;
            color: #cccccc;
        }

        .payment-methods {
            display: flex;
            gap: 1rem;
        }

        /* Age verification banner */
        .age-verification-banner {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(135deg, #8e44ad 0%, #9b59b6 100%);
            color: white;
            z-index: 10000;
            box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.5);
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

            .footer-bottom {
                flex-direction: column;
                gap: 1rem;
            }
        }
    </style>

    <script>
        // Age verification banner
        document.addEventListener('DOMContentLoaded', function() {
            const banner = document.getElementById('age-verification-banner');
            const acceptBtn = document.getElementById('accept-terms');
            const declineBtn = document.getElementById('decline-terms');

            if (localStorage.getItem('nycflirts_age_verified') === 'true') {
                banner.style.display = 'none';
            }

            acceptBtn.addEventListener('click', function() {
                localStorage.setItem('nycflirts_age_verified', 'true');
                banner.style.display = 'none';
            });

            declineBtn.addEventListener('click', function() {
                window.location.href = 'https://www.google.com';
            });
        });
    </script>
</body>
</html>