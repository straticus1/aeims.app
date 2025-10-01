<?php
$config = include 'config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migrating to AEIMS - Read Me First | <?php echo $config['site']['company']; ?></title>
    <meta name="description" content="Complete migration guide for switching to AEIMS platform. Step-by-step instructions, requirements, and best practices for a smooth transition.">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/migration.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <header class="header">
        <nav class="nav">
            <div class="nav-brand">
                <a href="index.php">
                    <h1><?php echo $config['site']['name']; ?></h1>
                    <span class="brand-subtitle"><?php echo $config['site']['company']; ?></span>
                </a>
            </div>
            <ul class="nav-menu">
                <li><a href="index.php#features">Features</a></li>
                <li><a href="index.php#powered-by">Powered By</a></li>
                <li><a href="index.php#pricing">Pricing</a></li>
                <li><a href="support.php">Support</a></li>
                <li><a href="migration.php" class="active">Migration</a></li>
                <li><a href="login.php" class="login-btn">Customer Login</a></li>
            </ul>
            <div class="nav-toggle">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </nav>
    </header>

    <main>
        <section class="migration-hero">
            <div class="container">
                <div class="hero-content">
                    <h1 class="hero-title">
                        <span class="gradient-text">Migrating to AEIMS</span><br>
                        Read Me First
                    </h1>
                    <p class="hero-description">
                        Complete guide for migrating your adult entertainment platform to AEIMS.
                        Follow our step-by-step process for a seamless transition with zero downtime.
                    </p>
                    <div class="migration-highlights">
                        <div class="highlight">
                            <span class="highlight-icon">✅</span>
                            <span>Zero Downtime Migration</span>
                        </div>
                        <div class="highlight">
                            <span class="highlight-icon">🔄</span>
                            <span>Data Preservation</span>
                        </div>
                        <div class="highlight">
                            <span class="highlight-icon">🛡️</span>
                            <span>Security First</span>
                        </div>
                        <div class="highlight">
                            <span class="highlight-icon">📞</span>
                            <span>24/7 Support</span>
                        </div>
                    </div>
                    <div class="cta-buttons">
                        <a href="#prerequisites" class="btn btn-primary">Start Migration</a>
                        <a href="support.php" class="btn btn-secondary">Get Help</a>
                    </div>
                </div>
            </div>
        </section>

        <section class="migration-overview">
            <div class="container">
                <h2 class="section-title">Migration Overview</h2>
                <div class="overview-timeline">
                    <div class="timeline-item">
                        <div class="timeline-number">1</div>
                        <div class="timeline-content">
                            <h3>Pre-Migration Assessment</h3>
                            <p>Evaluate your current platform, identify requirements, and plan the migration strategy.</p>
                            <span class="timeline-duration">1-2 days</span>
                        </div>
                    </div>
                    <div class="timeline-item">
                        <div class="timeline-number">2</div>
                        <div class="timeline-content">
                            <h3>Environment Setup</h3>
                            <p>Configure AEIMS infrastructure, set up domains, and prepare telephony integration.</p>
                            <span class="timeline-duration">2-3 days</span>
                        </div>
                    </div>
                    <div class="timeline-item">
                        <div class="timeline-number">3</div>
                        <div class="timeline-content">
                            <h3>Data Migration</h3>
                            <p>Transfer user data, operator profiles, financial records, and historical analytics.</p>
                            <span class="timeline-duration">3-5 days</span>
                        </div>
                    </div>
                    <div class="timeline-item">
                        <div class="timeline-number">4</div>
                        <div class="timeline-content">
                            <h3>Testing & Validation</h3>
                            <p>Comprehensive testing of all features, integrations, and performance validation.</p>
                            <span class="timeline-duration">2-3 days</span>
                        </div>
                    </div>
                    <div class="timeline-item">
                        <div class="timeline-number">5</div>
                        <div class="timeline-content">
                            <h3>Go Live</h3>
                            <p>DNS switchover, final testing, and 24/7 monitoring during transition period.</p>
                            <span class="timeline-duration">1 day</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="prerequisites" class="prerequisites">
            <div class="container">
                <h2 class="section-title">Prerequisites & Requirements</h2>
                <div class="requirements-grid">
                    <div class="requirement-card critical">
                        <div class="requirement-header">
                            <span class="requirement-icon">🚨</span>
                            <h3>Critical Requirements</h3>
                        </div>
                        <ul class="requirement-list">
                            <li>Administrative access to current platform</li>
                            <li>Database backup capabilities</li>
                            <li>DNS management access</li>
                            <li>SSL certificate management</li>
                            <li>Payment processor API credentials</li>
                            <li>Telephony provider access</li>
                        </ul>
                    </div>

                    <div class="requirement-card recommended">
                        <div class="requirement-header">
                            <span class="requirement-icon">💡</span>
                            <h3>Recommended</h3>
                        </div>
                        <ul class="requirement-list">
                            <li>Maintenance window scheduling</li>
                            <li>User notification system</li>
                            <li>Backup domain for testing</li>
                            <li>Performance baseline metrics</li>
                            <li>Operator training materials</li>
                            <li>Customer communication plan</li>
                        </ul>
                    </div>

                    <div class="requirement-card technical">
                        <div class="requirement-header">
                            <span class="requirement-icon">⚙️</span>
                            <h3>Technical Specs</h3>
                        </div>
                        <ul class="requirement-list">
                            <li>PHP 8.2+ or compatible platform</li>
                            <li>MySQL 8.0+ or PostgreSQL 13+</li>
                            <li>Redis for session management</li>
                            <li>HTTPS/SSL configured</li>
                            <li>WebSocket support</li>
                            <li>FreeSWITCH or Asterisk access</li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        <section class="migration-checklist">
            <div class="container">
                <h2 class="section-title">Pre-Migration Checklist</h2>
                <div class="checklist-container">
                    <div class="checklist-section">
                        <h3>Data Preparation</h3>
                        <div class="checklist-items">
                            <label class="checklist-item">
                                <input type="checkbox" id="backup-database">
                                <span class="checkmark"></span>
                                Complete database backup
                            </label>
                            <label class="checklist-item">
                                <input type="checkbox" id="export-users">
                                <span class="checkmark"></span>
                                Export user accounts and profiles
                            </label>
                            <label class="checklist-item">
                                <input type="checkbox" id="financial-data">
                                <span class="checkmark"></span>
                                Export financial and billing data
                            </label>
                            <label class="checklist-item">
                                <input type="checkbox" id="operator-data">
                                <span class="checkmark"></span>
                                Export operator profiles and earnings
                            </label>
                            <label class="checklist-item">
                                <input type="checkbox" id="analytics-data">
                                <span class="checkmark"></span>
                                Export analytics and statistics
                            </label>
                        </div>
                    </div>

                    <div class="checklist-section">
                        <h3>Infrastructure</h3>
                        <div class="checklist-items">
                            <label class="checklist-item">
                                <input type="checkbox" id="dns-access">
                                <span class="checkmark"></span>
                                Verify DNS management access
                            </label>
                            <label class="checklist-item">
                                <input type="checkbox" id="ssl-certs">
                                <span class="checkmark"></span>
                                Prepare SSL certificates
                            </label>
                            <label class="checklist-item">
                                <input type="checkbox" id="telephony-config">
                                <span class="checkmark"></span>
                                Document telephony configuration
                            </label>
                            <label class="checklist-item">
                                <input type="checkbox" id="payment-apis">
                                <span class="checkmark"></span>
                                Gather payment processor APIs
                            </label>
                            <label class="checklist-item">
                                <input type="checkbox" id="third-party">
                                <span class="checkmark"></span>
                                List third-party integrations
                            </label>
                        </div>
                    </div>

                    <div class="checklist-section">
                        <h3>Communication</h3>
                        <div class="checklist-items">
                            <label class="checklist-item">
                                <input type="checkbox" id="notify-operators">
                                <span class="checkmark"></span>
                                Notify operators of migration
                            </label>
                            <label class="checklist-item">
                                <input type="checkbox" id="customer-notice">
                                <span class="checkmark"></span>
                                Send customer notifications
                            </label>
                            <label class="checklist-item">
                                <input type="checkbox" id="maintenance-window">
                                <span class="checkmark"></span>
                                Schedule maintenance window
                            </label>
                            <label class="checklist-item">
                                <input type="checkbox" id="support-plan">
                                <span class="checkmark"></span>
                                Prepare support escalation plan
                            </label>
                            <label class="checklist-item">
                                <input type="checkbox" id="rollback-plan">
                                <span class="checkmark"></span>
                                Document rollback procedures
                            </label>
                        </div>
                    </div>
                </div>

                <div class="checklist-actions">
                    <button class="btn btn-secondary" onclick="exportChecklist()">Export Checklist</button>
                    <button class="btn btn-primary" onclick="requestMigration()">Request Migration Support</button>
                </div>
            </div>
        </section>

        <section class="migration-types">
            <div class="container">
                <h2 class="section-title">Migration Types</h2>
                <div class="migration-options">
                    <div class="migration-option">
                        <div class="option-header">
                            <h3>🚀 Express Migration</h3>
                            <span class="option-duration">3-5 days</span>
                        </div>
                        <div class="option-content">
                            <p>Fast-track migration for smaller platforms with standard configurations.</p>
                            <ul class="option-features">
                                <li>Up to 5 domains</li>
                                <li>Standard integrations</li>
                                <li>Basic data migration</li>
                                <li>Email support</li>
                            </ul>
                            <div class="option-price">Starting at $2,500</div>
                        </div>
                    </div>

                    <div class="migration-option featured">
                        <div class="option-header">
                            <h3>⭐ Professional Migration</h3>
                            <span class="option-duration">7-10 days</span>
                        </div>
                        <div class="option-content">
                            <p>Comprehensive migration with custom integrations and dedicated support.</p>
                            <ul class="option-features">
                                <li>Up to 20 domains</li>
                                <li>Custom integrations</li>
                                <li>Complete data migration</li>
                                <li>Dedicated phone support</li>
                                <li>Training sessions</li>
                            </ul>
                            <div class="option-price">Starting at $7,500</div>
                        </div>
                    </div>

                    <div class="migration-option">
                        <div class="option-header">
                            <h3>🏢 Enterprise Migration</h3>
                            <span class="option-duration">2-4 weeks</span>
                        </div>
                        <div class="option-content">
                            <p>White-glove migration service for large-scale platforms with complex requirements.</p>
                            <ul class="option-features">
                                <li>Unlimited domains</li>
                                <li>Custom development</li>
                                <li>Zero-downtime migration</li>
                                <li>24/7 dedicated support</li>
                                <li>On-site assistance</li>
                                <li>Performance optimization</li>
                            </ul>
                            <div class="option-price">Custom pricing</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="common-challenges">
            <div class="container">
                <h2 class="section-title">Common Migration Challenges</h2>
                <div class="challenges-grid">
                    <div class="challenge-card">
                        <h3>🔄 Data Synchronization</h3>
                        <div class="challenge-problem">
                            <h4>Challenge:</h4>
                            <p>Keeping data synchronized during migration while maintaining live operations.</p>
                        </div>
                        <div class="challenge-solution">
                            <h4>AEIMS Solution:</h4>
                            <p>Real-time data sync with automatic rollback capabilities and verification checksums.</p>
                        </div>
                    </div>

                    <div class="challenge-card">
                        <h3>📞 Telephony Integration</h3>
                        <div class="challenge-problem">
                            <h4>Challenge:</h4>
                            <p>Migrating complex telephony configurations without service interruption.</p>
                        </div>
                        <div class="challenge-solution">
                            <h4>AEIMS Solution:</h4>
                            <p>Gradual telephony migration with parallel running systems and seamless switchover.</p>
                        </div>
                    </div>

                    <div class="challenge-card">
                        <h3>💳 Payment Processing</h3>
                        <div class="challenge-problem">
                            <h4>Challenge:</h4>
                            <p>Maintaining payment processing during platform transition.</p>
                        </div>
                        <div class="challenge-solution">
                            <h4>AEIMS Solution:</h4>
                            <p>Payment gateway bridging and real-time transaction monitoring during migration.</p>
                        </div>
                    </div>

                    <div class="challenge-card">
                        <h3>👥 User Experience</h3>
                        <div class="challenge-problem">
                            <h4>Challenge:</h4>
                            <p>Minimizing user disruption and maintaining service quality.</p>
                        </div>
                        <div class="challenge-solution">
                            <h4>AEIMS Solution:</h4>
                            <p>Phased rollout with user communication and fallback mechanisms.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="support-contact">
            <div class="container">
                <h2 class="section-title">Ready to Migrate?</h2>
                <div class="contact-options">
                    <div class="contact-option">
                        <div class="contact-icon">📧</div>
                        <h3>Email Migration Team</h3>
                        <p>Get detailed migration planning and custom quotes</p>
                        <a href="mailto:migration@aeims.app" class="btn btn-secondary">migration@aeims.app</a>
                    </div>

                    <div class="contact-option">
                        <div class="contact-icon">📞</div>
                        <h3>Schedule Consultation</h3>
                        <p>Speak directly with our migration specialists</p>
                        <a href="support.php#emergency-form" class="btn btn-primary">Schedule Call</a>
                    </div>

                    <div class="contact-option">
                        <div class="contact-icon">💬</div>
                        <h3>Live Chat Support</h3>
                        <p>Get instant answers to your migration questions</p>
                        <a href="support.php" class="btn btn-secondary">Start Chat</a>
                    </div>
                </div>

                <div class="migration-guarantee">
                    <h3>🛡️ Migration Guarantee</h3>
                    <p>We guarantee a successful migration or your money back. Our team has successfully migrated over 200+ adult entertainment platforms with 99.9% success rate.</p>
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
                    <h4>Migration</h4>
                    <ul>
                        <li><a href="#prerequisites">Prerequisites</a></li>
                        <li><a href="#migration-types">Migration Types</a></li>
                        <li><a href="support.php">Migration Support</a></li>
                        <li><a href="login.php">Customer Portal</a></li>
                    </ul>
                </div>
                <div class="footer-links">
                    <h4>Resources</h4>
                    <ul>
                        <li><a href="support.php">Support Center</a></li>
                        <li><a href="#">API Documentation</a></li>
                        <li><a href="#">Best Practices</a></li>
                        <li><a href="#">Case Studies</a></li>
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
    <script src="assets/js/migration.js"></script>
</body>
</html>