<?php
// AEIMS Dynamic Website - Now with FULL Integration!

// Virtual Host Routing (quick fix)
$host = $_SERVER['HTTP_HOST'] ?? '';
switch (true) {
    case ($host === 'login.sexacomms.com'):
        // Login interface
        require_once 'sites/sexacomms/login.php';
        exit();

    case ($host === 'sexacomms.com' || $host === 'www.sexacomms.com'):
        // Warning page
        require_once 'sites/sexacomms/warning.php';
        exit();

    case ($host === 'flirts.nyc' || $host === 'www.flirts.nyc'):
        // Customer site
        require_once 'sites/flirts.nyc/index.php';
        exit();

    case ($host === 'nycflirts.com' || $host === 'www.nycflirts.com'):
        // Customer site
        require_once 'sites/nycflirts.com/index.php';
        exit();

    case ($host === 'idverify.aeims.app' || $host === 'idcheck.aeims.app'):
        // ID Verification Service
        require_once 'idverify/index.php';
        exit();
}

// Redirect admin.aeims.app to login page
if (isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'admin.aeims.app') {
    header('Location: https://admin.aeims.app/login.php');
    exit();
}

require_once 'includes/AeimsIntegration.php';
$config = include 'config.php';

// Get real statistics from AEIMS if available
$realStats = null;
$aeimsAvailable = false;

if ($config['aeims_integration']['enabled']) {
    try {
        $aeims = new AeimsIntegration();
        $realStats = $aeims->getRealStats();
        $aeimsAvailable = true;
        
        // Override config stats with real data
        if ($realStats && $realStats['system_health'] !== 'mock_data') {
            $config['stats'] = array_merge($config['stats'], $realStats);
        }
    } catch (Exception $e) {
        // Fallback to config data if AEIMS unavailable
        error_log("AEIMS integration failed: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $config['site']['name']; ?> - <?php echo $config['site']['full_name']; ?> | <?php echo $config['site']['company']; ?></title>
    <meta name="description" content="<?php echo $config['site']['name']; ?> - The premier adult entertainment platform management system. Comprehensive pay-per-minute video, audio, call services with text/chat capabilities.">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div id="loading-overlay" class="loading-overlay">
        <div class="spinner"></div>
    </div>

    <!-- Age Verification / Cookie Consent Banner -->
    <div id="age-verification-banner" class="age-verification-banner">
        <div class="banner-content">
            <div class="banner-text">
                <strong>Age Verification & Cookie Notice</strong><br>
                By using NiteText.com, AEIMS.app, and afterdarksys.com you agree to the After Dark Systems or AEIMS.app User Agreement and understand that we use cookies and may share your information with third party providers, such as analytic partners. We will never sell your information to third parties. You must be 18 or older to continue.
            </div>
            <div class="banner-actions">
                <button id="accept-terms" class="btn btn-primary">I am 18+ and Accept</button>
                <button id="decline-terms" class="btn btn-outline">I am under 18</button>
            </div>
        </div>
    </div>

    <header class="header">
        <nav class="nav">
            <div class="nav-brand">
                <h1><?php echo $config['site']['name']; ?></h1>
                <span class="brand-subtitle"><?php echo $config['site']['company']; ?></span>
            </div>
            <ul class="nav-menu">
                <li><a href="#features">Features</a></li>
                <li><a href="#powered-by">Powered By</a></li>
                <li><a href="#pricing">Pricing</a></li>
                <li><a href="#contact">Contact</a></li>
                <li><a href="login.php" class="nav-login">Login</a></li>
            </ul>
            <div class="nav-toggle">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </nav>
    </header>

    <main>
        <section class="hero">
            <div class="container">
                <div class="hero-content">
                    <h1 class="hero-title">
                        The Premier <span class="gradient-text">Adult Entertainment</span> Platform
                    </h1>
                    <p class="hero-description">
                        <?php echo $config['site']['name']; ?> (<?php echo $config['site']['full_name']; ?>) is the world's ONLY adult platform with comprehensive device control integration. Control 15+ major brands (Lovense, WeVibe, Kiiroo) with revolutionary cross-site operator support and 100% discrete billing. Built for the future of interactive adult entertainment.
                    </p>
                    <div class="hero-stats">
                        <div class="stat">
                            <span class="stat-number" data-target="<?php echo $config['stats']['sites_powered']; ?>">0</span>
                            <span class="stat-label">Sites Powered</span>
                        </div>
                        <div class="stat">
                            <span class="stat-number" data-target="<?php echo $config['stats']['uptime']; ?>">0</span>
                            <span class="stat-label">% Uptime</span>
                        </div>
                        <div class="stat">
                            <span class="stat-number" data-target="<?php echo $config['stats']['support_hours']; ?>">0</span>
                            <span class="stat-label">/ 7 Support</span>
                        </div>
                    </div>
                    <div class="hero-cta">
                        <a href="#contact" class="btn btn-primary">License Our Platform</a>
                        <a href="#features" class="btn btn-secondary">Learn More</a>
                    </div>
                </div>
                <div class="hero-visual">
                    <div class="platform-preview">
                        <div class="preview-card">
                            <h3>Real-time Analytics</h3>
                            <div class="chart-placeholder"></div>
                        </div>
                        <div class="preview-card">
                            <h3>Multi-Provider Support</h3>
                            <div class="provider-list">
                                <span>FreeSWITCH</span>
                                <span>Asterisk</span>
                                <span>Twilio</span>
                                <span>AWS Connect</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="features" class="features">
            <div class="container">
                <h2 class="section-title">Why Choose <?php echo $config['site']['name']; ?>?</h2>
                <div class="features-grid">
                    <div class="feature-card revolutionary">
                        <div class="feature-icon">🎮</div>
                        <h3>Revolutionary Device Control</h3>
                        <p>INDUSTRY-FIRST comprehensive device integration! Control 15+ major brands (Lovense, WeVibe, Kiiroo, Magic Motion) with real-time synchronization, VR integration, and AI-powered patterns.</p>
                        <div class="feature-stat">15+ device brands supported</div>
                        <div class="feature-brands">
                            <span>Lovense</span><span>WeVibe</span><span>Kiiroo</span><span>Handy</span><span>+11 more</span>
                        </div>
                    </div>
                    <div class="feature-card highlight">
                        <div class="feature-icon">👥</div>
                        <h3>Cross-Site Operator Support</h3>
                        <p>Revolutionary single operator, multiple sites system. One operator can work across all your platforms simultaneously, maximizing earnings and operational efficiency.</p>
                        <div class="feature-stat"><?php echo $config['stats']['cross_site_operators']; ?> operators across <?php echo $config['stats']['sites_powered']; ?> sites</div>
                    </div>
                    <div class="feature-card highlight">
                        <div class="feature-icon">🛡️</div>
                        <h3>Discrete Billing</h3>
                        <p>Complete transaction privacy with non-descriptive billing descriptors. All payments appear discretely on statements to protect customer confidentiality.</p>
                        <div class="feature-stat">100% discrete transactions</div>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">🔒</div>
                        <h3>Security First</h3>
                        <p>Anonymous operator and caller protection with enterprise-grade security measures including HTTPS/WSS encryption, JWT authentication, and comprehensive audit trails.</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">📞</div>
                        <h3>Less Dropped Calls</h3>
                        <p>Advanced telephony management with FreeSWITCH, Asterisk, and cloud provider integration ensures maximum call quality and minimal disconnections.</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">💰</div>
                        <h3>Streamlined Payments</h3>
                        <p>Automated operator payment systems with real-time earnings tracking, multiple payment methods, and transparent revenue distribution.</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">📊</div>
                        <h3>Real-time Monitoring</h3>
                        <p>Comprehensive platform telemetry, performance metrics, health checks, and analytics with Grafana & Prometheus integration.</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="device-control-showcase">
            <div class="container">
                <div class="device-showcase-content">
                    <div class="device-info">
                        <h2 class="section-title">Revolutionary Device Control</h2>
                        <div class="device-subtitle">The ONLY adult platform with comprehensive device integration</div>
                        <p class="device-description">
                            AEIMS integrates with aeimsLib - our revolutionary device control library supporting 15+ major brands.
                            Create immersive, interactive experiences that no other platform can match.
                        </p>

                        <div class="device-features">
                            <div class="device-feature">
                                <span class="device-feature-icon">🎮</span>
                                <div>
                                    <h4>Real-Time Control</h4>
                                    <p>Instant device synchronization with zero lag</p>
                                </div>
                            </div>
                            <div class="device-feature">
                                <span class="device-feature-icon">🧠</span>
                                <div>
                                    <h4>AI Pattern Generation</h4>
                                    <p>Intelligent pattern creation and optimization</p>
                                </div>
                            </div>
                            <div class="device-feature">
                                <span class="device-feature-icon">🥽</span>
                                <div>
                                    <h4>VR/XR Integration</h4>
                                    <p>3D spatial control and haptic feedback</p>
                                </div>
                            </div>
                            <div class="device-feature">
                                <span class="device-feature-icon">🎵</span>
                                <div>
                                    <h4>Audio/Video Sync</h4>
                                    <p>Beat detection and media synchronization</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="device-brands-showcase">
                        <h3>Supported Device Brands</h3>
                        <div class="device-brands-grid">
                            <div class="device-brand">Lovense</div>
                            <div class="device-brand">WeVibe</div>
                            <div class="device-brand">Kiiroo</div>
                            <div class="device-brand">Magic Motion</div>
                            <div class="device-brand">Svakom</div>
                            <div class="device-brand">Vorze</div>
                            <div class="device-brand">Handy</div>
                            <div class="device-brand">PiShock</div>
                            <div class="device-brand">Satisfyer</div>
                            <div class="device-brand">Vibease</div>
                            <div class="device-brand">LoveLife</div>
                            <div class="device-brand">TCode Protocol</div>
                            <div class="device-brand featured">+3 More</div>
                        </div>

                        <div class="device-protocols">
                            <h4>Supported Protocols</h4>
                            <div class="protocol-list">
                                <span>Bluetooth LE</span>
                                <span>Buttplug.io</span>
                                <span>WebSocket</span>
                                <span>TCode</span>
                                <span>OSR</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="powered-by" class="powered-by">
            <div class="container">
                <h2 class="section-title">Sites Powered by <?php echo $config['site']['name']; ?></h2>
                <p class="section-description">Our platform currently powers multiple successful adult entertainment sites</p>
                <div class="sites-grid">
                    <?php foreach (($config['powered_sites'] ?? []) as $site): ?>
                    <div class="site-card">
                        <h3><?php echo htmlspecialchars($site['domain']); ?></h3>
                        <div class="site-theme"><?php echo htmlspecialchars($site['theme']); ?></div>
                        <p><?php echo htmlspecialchars($site['description']); ?></p>
                        <div class="site-stats">
                            <?php foreach ($site['services'] as $service): ?>
                            <span><?php echo htmlspecialchars($service); ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="powered-by-footer">
                    <p>And many more! Ready to join our network?</p>
                    <a href="#contact" class="btn btn-primary">Get Started</a>
                </div>
            </div>
        </section>

        <section id="pricing" class="pricing">
            <div class="container">
                <h2 class="section-title">Flexible Licensing Options</h2>
                <p class="section-description">Choose the licensing model that fits your business needs</p>

                <div class="pricing-tabs">
                    <button class="tab-btn active" data-tab="user-based">Per User</button>
                    <button class="tab-btn" data-tab="domain-based">Per Domain</button>
                    <button class="tab-btn" data-tab="enterprise">Enterprise</button>
                </div>

                <div class="pricing-content">
                    <div id="user-based" class="tab-content active">
                        <div class="pricing-grid">
                            <div class="pricing-card">
                                <h3>Starter</h3>
                                <div class="price">
                                    <span class="amount">$2.99</span>
                                    <span class="period">/user/month</span>
                                </div>
                                <ul class="features-list">
                                    <li>Up to 100 users</li>
                                    <li>Basic telephony features</li>
                                    <li>Text chat support</li>
                                    <li>Standard support</li>
                                    <li>Regional dial-in numbers</li>
                                </ul>
                                <a href="#contact" class="btn btn-outline">Get Started</a>
                            </div>
                            <div class="pricing-card featured">
                                <h3>Professional</h3>
                                <div class="price">
                                    <span class="amount">$1.99</span>
                                    <span class="period">/user/month</span>
                                </div>
                                <ul class="features-list">
                                    <li>Up to 1,000 users</li>
                                    <li>Cross-site operator support</li>
                                    <li>Discrete billing included</li>
                                    <li>Advanced telephony features</li>
                                    <li>Video & voice calls</li>
                                    <li>Priority support</li>
                                    <li>1-800 number included</li>
                                    <li>Advanced analytics</li>
                                </ul>
                                <a href="#contact" class="btn btn-primary">Popular Choice</a>
                            </div>
                            <div class="pricing-card">
                                <h3>Enterprise</h3>
                                <div class="price">
                                    <span class="amount">$0.99</span>
                                    <span class="period">/user/month</span>
                                </div>
                                <ul class="features-list">
                                    <li>Unlimited users</li>
                                    <li>Advanced cross-site operators</li>
                                    <li>Premium discrete billing</li>
                                    <li>Full feature access</li>
                                    <li>Multi-site management</li>
                                    <li>24/7 dedicated support</li>
                                    <li>Custom integrations</li>
                                    <li>White-label options</li>
                                </ul>
                                <a href="#contact" class="btn btn-outline">Contact Sales</a>
                            </div>
                        </div>
                    </div>

                    <div id="domain-based" class="tab-content">
                        <div class="pricing-grid">
                            <div class="pricing-card">
                                <h3>Single Domain</h3>
                                <div class="price">
                                    <span class="amount">$299</span>
                                    <span class="period">/month</span>
                                </div>
                                <ul class="features-list">
                                    <li>1 domain license</li>
                                    <li>Up to 10 operators</li>
                                    <li>Basic customization</li>
                                    <li>Standard support</li>
                                    <li>Regional numbers</li>
                                </ul>
                                <a href="#contact" class="btn btn-outline">Get Started</a>
                            </div>
                            <div class="pricing-card featured">
                                <h3>Multi-Domain</h3>
                                <div class="price">
                                    <span class="amount">$199</span>
                                    <span class="period">/domain/month</span>
                                </div>
                                <ul class="features-list">
                                    <li>3+ domain licenses</li>
                                    <li>Cross-site operator sharing</li>
                                    <li>Up to 50 operators per domain</li>
                                    <li>Discrete billing included</li>
                                    <li>Advanced customization</li>
                                    <li>Priority support</li>
                                    <li>1-800 numbers</li>
                                    <li>Cross-domain analytics</li>
                                </ul>
                                <a href="#contact" class="btn btn-primary">Best Value</a>
                            </div>
                            <div class="pricing-card">
                                <h3>Network License</h3>
                                <div class="price">
                                    <span class="amount">$99</span>
                                    <span class="period">/domain/month</span>
                                </div>
                                <ul class="features-list">
                                    <li>10+ domain licenses</li>
                                    <li>Unlimited operators</li>
                                    <li>Full white-label</li>
                                    <li>24/7 dedicated support</li>
                                    <li>Custom development</li>
                                    <li>Revenue sharing options</li>
                                </ul>
                                <a href="#contact" class="btn btn-outline">Contact Sales</a>
                            </div>
                        </div>
                    </div>

                    <div id="enterprise" class="tab-content">
                        <div class="enterprise-info">
                            <h3>Enterprise Solutions</h3>
                            <p>Custom solutions tailored to your specific needs</p>
                            <div class="enterprise-features">
                                <div class="feature-group">
                                    <h4>Infrastructure Considerations</h4>
                                    <ul>
                                        <li>How many lines will you operate?</li>
                                        <li>Expected text message volume?</li>
                                        <li>1-800 number requirements?</li>
                                        <li>Regional dial-in preferences?</li>
                                        <li>International coverage needs?</li>
                                    </ul>
                                </div>
                                <div class="feature-group">
                                    <h4>Custom Development</h4>
                                    <ul>
                                        <li>API integrations</li>
                                        <li>Custom payment processors</li>
                                        <li>Specialized compliance requirements</li>
                                        <li>Advanced analytics and reporting</li>
                                        <li>Third-party service integrations</li>
                                    </ul>
                                </div>
                            </div>
                            <a href="#contact" class="btn btn-primary">Schedule Consultation</a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="contact" class="contact">
            <div class="container">
                <h2 class="section-title">Ready to License <?php echo $config['site']['name']; ?>?</h2>
                <p class="section-description">Get in touch with our team to discuss your requirements</p>

                <div class="contact-content">
                    <div class="contact-info">
                        <h3>Get Started Today</h3>
                        <p>Our team will work with you to determine the best licensing option for your business needs.</p>
                        <div class="contact-details">
                            <div class="contact-item">
                                <strong>Email:</strong>
                                <a href="mailto:<?php echo $config['site']['contact_email']; ?>"><?php echo $config['site']['contact_email']; ?></a>
                            </div>
                            <div class="contact-item">
                                <strong>Response Time:</strong>
                                <span>Within <?php echo $config['site']['response_time']; ?></span>
                            </div>
                        </div>
                    </div>

                    <form class="contact-form" id="contactForm">
                        <div class="form-group">
                            <label for="name">Name *</label>
                            <input type="text" id="name" name="name" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email *</label>
                            <input type="email" id="email" name="email" required>
                        </div>
                        <div class="form-group">
                            <label for="company">Company</label>
                            <input type="text" id="company" name="company">
                        </div>
                        <div class="form-group">
                            <label for="users">Estimated Users</label>
                            <select id="users" name="users">
                                <option value="">Select range</option>
                                <option value="1-100">1-100 users</option>
                                <option value="101-500">101-500 users</option>
                                <option value="501-1000">501-1,000 users</option>
                                <option value="1000+">1,000+ users</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="domains">Number of Domains</label>
                            <select id="domains" name="domains">
                                <option value="">Select number</option>
                                <option value="1">1 domain</option>
                                <option value="2-5">2-5 domains</option>
                                <option value="6-10">6-10 domains</option>
                                <option value="10+">10+ domains</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="message">Message *</label>
                            <textarea id="message" name="message" rows="4" required placeholder="Tell us about your requirements..."></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Send Message</button>
                    </form>
                </div>
            </div>
        </section>
    </main>

    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-brand">
                    <h3><?php echo $config['site']['name']; ?></h3>
                    <p><?php echo $config['site']['full_name']; ?></p>
                    <p class="powered-by">Powered by <a href="https://afterdarksys.com"><?php echo $config['site']['company']; ?></a></p>
                </div>
                <div class="footer-links">
                    <h4>Platform</h4>
                    <ul>
                        <li><a href="#features">Features</a></li>
                        <li><a href="#powered-by">Sites</a></li>
                        <li><a href="#pricing">Pricing</a></li>
                        <li><a href="#contact">Contact</a></li>
                        <li><a href="login.php">Login</a></li>
                        <li><a href="become-operator.php">Become an Operator</a></li>
                        <li><a href="customer-age-verification.php">Age Verification</a></li>
                    </ul>
                </div>
                <div class="footer-links">
                    <h4>Legal & Compliance</h4>
                    <ul>
                        <li><a href="legal.php">Legal Framework</a></li>
                        <li><a href="legal.php#federal-compliance">Federal Compliance</a></li>
                        <li><a href="legal.php#privacy-data">Privacy Policy</a></li>
                        <li><a href="legal.php#reporting">Report Violations</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> <?php echo $config['site']['company']; ?>. All rights reserved.</p>
                <p><?php echo $config['site']['name']; ?> is designed and developed by Ryan Coleman</p>
            </div>
        </div>
    </footer>

    <script src="assets/js/script.js"></script>

    <!-- Age Verification Banner Styles -->
    <style>
        .age-verification-banner {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(135deg, #1e40af 0%, #3730a3 100%);
            color: white;
            z-index: 10000;
            box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.5s ease-out;
        }

        .age-verification-banner.hidden {
            transform: translateY(100%);
            opacity: 0;
            pointer-events: none;
            transition: all 0.3s ease-in-out;
        }

        @keyframes slideUp {
            from {
                transform: translateY(100%);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
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

        .banner-text {
            flex: 1;
            font-size: 0.875rem;
            line-height: 1.5;
        }

        .banner-text strong {
            color: #fbbf24;
            font-size: 1rem;
            margin-bottom: 0.5rem;
            display: inline;
        }

        .banner-actions {
            display: flex;
            gap: 0.75rem;
            flex-shrink: 0;
        }

        .banner-actions .btn {
            padding: 0.625rem 1.25rem;
            font-weight: 600;
            border-radius: 6px;
            text-decoration: none;
            cursor: pointer;
            border: none;
            font-size: 0.875rem;
            transition: all 0.2s;
            white-space: nowrap;
        }

        .banner-actions .btn-primary {
            background: #059669;
            color: white;
        }

        .banner-actions .btn-primary:hover {
            background: #047857;
            transform: translateY(-1px);
        }

        .banner-actions .btn-outline {
            background: transparent;
            color: #fbbf24;
            border: 2px solid #fbbf24;
        }

        .banner-actions .btn-outline:hover {
            background: #fbbf24;
            color: #1e40af;
        }

        @media (max-width: 768px) {
            .banner-content {
                flex-direction: column;
                text-align: center;
                gap: 1rem;
                padding: 1.25rem;
            }

            .banner-text {
                font-size: 0.8rem;
            }

            .banner-actions {
                flex-direction: column;
                width: 100%;
                max-width: 300px;
            }

            .banner-actions .btn {
                width: 100%;
            }
        }

        /* Ensure page content isn't hidden behind the banner */
        body.banner-visible {
            padding-bottom: 120px;
        }

        @media (max-width: 768px) {
            body.banner-visible {
                padding-bottom: 180px;
            }
        }
    </style>

    <!-- Age Verification Banner JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const banner = document.getElementById('age-verification-banner');
            const acceptBtn = document.getElementById('accept-terms');
            const declineBtn = document.getElementById('decline-terms');

            // Check if user has already accepted terms
            if (localStorage.getItem('aeims_age_verified') === 'true') {
                banner.classList.add('hidden');
                document.body.classList.remove('banner-visible');
            } else {
                document.body.classList.add('banner-visible');
            }

            // Handle accept button
            acceptBtn.addEventListener('click', function() {
                localStorage.setItem('aeims_age_verified', 'true');
                localStorage.setItem('aeims_terms_accepted_date', new Date().toISOString());
                banner.classList.add('hidden');
                document.body.classList.remove('banner-visible');

                // Optional: Send analytics event
                if (typeof gtag !== 'undefined') {
                    gtag('event', 'age_verification_accepted', {
                        'event_category': 'compliance',
                        'event_label': 'age_verification'
                    });
                }
            });

            // Handle decline button
            declineBtn.addEventListener('click', function() {
                // Redirect to a "you must be 18+" page or external site
                alert('You must be 18 or older to access this website. You will now be redirected to Google.');
                window.location.href = 'https://www.google.com';
            });
        });
    </script>
</body>
</html>