<?php
// AEIMS Support Page
$config = include 'config.php';

// Add support-specific configuration
$support_config = [
    'support_hours' => '24/7/365',
    'response_time' => [
        'critical' => '1 hour',
        'high' => '4 hours',
        'medium' => '24 hours',
        'low' => '72 hours'
    ],
    'support_channels' => [
        'emergency' => '+1-XXX-XXX-XXXX',
        'email' => 'support@aeims.app',
        'tickets' => 'Available 24/7'
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support - <?php echo $config['site']['name']; ?> | <?php echo $config['site']['company']; ?></title>
    <meta name="description" content="Get 24/7 support for AEIMS platform. Submit tickets, access documentation, and get help with your adult entertainment platform management.">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/support.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div id="loading-overlay" class="loading-overlay">
        <div class="spinner"></div>
    </div>

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
                <li><a href="support.php" class="active">Support</a></li>
                <li><a href="migration.php">Migration</a></li>
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
        <section class="support-hero">
            <div class="container">
                <div class="hero-content">
                    <h1 class="hero-title">
                        <span class="gradient-text">24/7 Support</span> for Your Success
                    </h1>
                    <p class="hero-description">
                        Get expert help with your AEIMS platform. Our dedicated support team is available around the clock to ensure your adult entertainment business runs smoothly.
                    </p>
                    <div class="support-stats">
                        <div class="stat">
                            <span class="stat-number">< 1hr</span>
                            <span class="stat-label">Critical Response</span>
                        </div>
                        <div class="stat">
                            <span class="stat-number">24/7</span>
                            <span class="stat-label">Availability</span>
                        </div>
                        <div class="stat">
                            <span class="stat-number">99.9%</span>
                            <span class="stat-label">Resolution Rate</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="support-options">
            <div class="container">
                <h2 class="section-title">How Can We Help?</h2>
                <div class="support-grid">
                    <div class="support-card urgent">
                        <div class="support-icon">🚨</div>
                        <h3>Emergency Support</h3>
                        <p>Critical system issues affecting your live operations</p>
                        <div class="support-details">
                            <div class="response-time">Response: Within 1 hour</div>
                            <div class="contact-method">Phone: <?php echo $support_config['support_channels']['emergency']; ?></div>
                        </div>
                        <a href="#emergency-form" class="btn btn-accent">Get Emergency Help</a>
                    </div>

                    <div class="support-card">
                        <div class="support-icon">🎫</div>
                        <h3>Submit Support Ticket</h3>
                        <p>General support, feature requests, and technical assistance</p>
                        <div class="support-details">
                            <div class="response-time">Response: Within 4 hours</div>
                            <div class="contact-method">Available: 24/7/365</div>
                        </div>
                        <a href="#ticket-form" class="btn btn-primary">Create Ticket</a>
                    </div>

                    <div class="support-card">
                        <div class="support-icon">📚</div>
                        <h3>Documentation</h3>
                        <p>Self-service guides, API docs, and troubleshooting</p>
                        <div class="support-details">
                            <div class="response-time">Available: Instantly</div>
                            <div class="contact-method">Searchable Knowledge Base</div>
                        </div>
                        <a href="#knowledge-base" class="btn btn-secondary">Browse Docs</a>
                    </div>
                </div>
            </div>
        </section>

        <section class="support-levels">
            <div class="container">
                <h2 class="section-title">Support Priority Levels</h2>
                <div class="priority-grid">
                    <div class="priority-card critical">
                        <div class="priority-badge">Critical</div>
                        <h3>System Down</h3>
                        <div class="response-time">1 Hour Response</div>
                        <ul>
                            <li>Complete system outage</li>
                            <li>Payment processing failure</li>
                            <li>Security breaches</li>
                            <li>Data loss incidents</li>
                        </ul>
                    </div>
                    <div class="priority-card high">
                        <div class="priority-badge">High</div>
                        <h3>Major Issue</h3>
                        <div class="response-time">4 Hour Response</div>
                        <ul>
                            <li>Feature not working</li>
                            <li>Performance degradation</li>
                            <li>Integration failures</li>
                            <li>Call quality issues</li>
                        </ul>
                    </div>
                    <div class="priority-card medium">
                        <div class="priority-badge">Medium</div>
                        <h3>General Support</h3>
                        <div class="response-time">24 Hour Response</div>
                        <ul>
                            <li>Configuration questions</li>
                            <li>Feature requests</li>
                            <li>Training requests</li>
                            <li>Minor bug reports</li>
                        </ul>
                    </div>
                    <div class="priority-card low">
                        <div class="priority-badge">Low</div>
                        <h3>Enhancement</h3>
                        <div class="response-time">72 Hour Response</div>
                        <ul>
                            <li>Documentation updates</li>
                            <li>Cosmetic issues</li>
                            <li>General inquiries</li>
                            <li>Feedback submissions</li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        <section id="ticket-form" class="ticket-form-section">
            <div class="container">
                <h2 class="section-title">Submit Support Ticket</h2>
                <div class="form-container">
                    <form class="ticket-form" id="supportTicketForm">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="ticket-name">Name *</label>
                                <input type="text" id="ticket-name" name="name" required>
                            </div>
                            <div class="form-group">
                                <label for="ticket-email">Email *</label>
                                <input type="email" id="ticket-email" name="email" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="ticket-company">Company/Domain</label>
                                <input type="text" id="ticket-company" name="company" placeholder="Your domain or company name">
                            </div>
                            <div class="form-group">
                                <label for="ticket-priority">Priority *</label>
                                <select id="ticket-priority" name="priority" required>
                                    <option value="">Select Priority</option>
                                    <option value="critical">Critical - System Down</option>
                                    <option value="high">High - Major Issue</option>
                                    <option value="medium">Medium - General Support</option>
                                    <option value="low">Low - Enhancement</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="ticket-category">Category *</label>
                            <select id="ticket-category" name="category" required>
                                <option value="">Select Category</option>
                                <option value="technical">Technical Issue</option>
                                <option value="billing">Billing & Licensing</option>
                                <option value="feature">Feature Request</option>
                                <option value="integration">Integration Support</option>
                                <option value="training">Training & Documentation</option>
                                <option value="security">Security Concern</option>
                                <option value="other">Other</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="ticket-subject">Subject *</label>
                            <input type="text" id="ticket-subject" name="subject" required placeholder="Brief description of the issue">
                        </div>

                        <div class="form-group">
                            <label for="ticket-description">Detailed Description *</label>
                            <textarea id="ticket-description" name="description" rows="6" required placeholder="Please provide detailed information about your issue, including:
- What you were trying to do
- What happened instead
- Any error messages
- Steps to reproduce (if applicable)"></textarea>
                        </div>

                        <div class="form-group">
                            <label for="ticket-environment">Environment Information</label>
                            <textarea id="ticket-environment" name="environment" rows="3" placeholder="Browser, operating system, AEIMS version, etc."></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary">Submit Ticket</button>
                    </form>
                </div>
            </div>
        </section>

        <section id="emergency-form" class="emergency-form-section">
            <div class="container">
                <h2 class="section-title urgent-title">Emergency Support Request</h2>
                <div class="emergency-notice">
                    <p><strong>⚠️ Use this form only for critical system outages, security breaches, or complete service failures.</strong></p>
                    <p>For urgent issues, you can also call: <?php echo $support_config['support_channels']['emergency']; ?></p>
                </div>
                <div class="form-container">
                    <form class="emergency-form" id="emergencyForm">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="emergency-name">Name *</label>
                                <input type="text" id="emergency-name" name="name" required>
                            </div>
                            <div class="form-group">
                                <label for="emergency-phone">Phone Number *</label>
                                <input type="tel" id="emergency-phone" name="phone" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="emergency-email">Email *</label>
                            <input type="email" id="emergency-email" name="email" required>
                        </div>

                        <div class="form-group">
                            <label for="emergency-domain">Affected Domain(s) *</label>
                            <input type="text" id="emergency-domain" name="domain" required placeholder="List all affected domains">
                        </div>

                        <div class="form-group">
                            <label for="emergency-issue">Critical Issue Type *</label>
                            <select id="emergency-issue" name="issue_type" required>
                                <option value="">Select Issue Type</option>
                                <option value="complete_outage">Complete System Outage</option>
                                <option value="payment_failure">Payment Processing Failure</option>
                                <option value="security_breach">Security Breach</option>
                                <option value="data_loss">Data Loss</option>
                                <option value="telephony_down">Telephony System Down</option>
                                <option value="other_critical">Other Critical Issue</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="emergency-description">Issue Description *</label>
                            <textarea id="emergency-description" name="description" rows="4" required placeholder="Describe the critical issue and its impact on your business"></textarea>
                        </div>

                        <div class="form-group">
                            <label for="emergency-impact">Business Impact *</label>
                            <textarea id="emergency-impact" name="impact" rows="3" required placeholder="Describe how this is affecting your operations and customers"></textarea>
                        </div>

                        <button type="submit" class="btn btn-accent">Submit Emergency Request</button>
                    </form>
                </div>
            </div>
        </section>

        <section id="knowledge-base" class="knowledge-base">
            <div class="container">
                <h2 class="section-title">Knowledge Base</h2>
                <div class="search-container">
                    <input type="text" id="kb-search" placeholder="Search documentation..." class="kb-search">
                    <button class="search-btn">🔍</button>
                </div>

                <div class="kb-categories">
                    <div class="kb-category">
                        <h3>Getting Started</h3>
                        <ul>
                            <li><a href="migration.php">Migration Guide</a></li>
                            <li><a href="#" onclick="loginRequired()">Initial AEIMS Setup</a></li>
                            <li><a href="#" onclick="loginRequired()">Domain Configuration</a></li>
                            <li><a href="#">Quick Start Guide</a></li>
                            <li><a href="#">Best Practices</a></li>
                        </ul>
                    </div>

                    <div class="kb-category">
                        <h3>Technical Documentation</h3>
                        <ul>
                            <li><a href="#">API Reference</a></li>
                            <li><a href="#">Integration Guide</a></li>
                            <li><a href="#">Telephony Configuration</a></li>
                            <li><a href="#">Security Guidelines</a></li>
                            <li><a href="#">Performance Optimization</a></li>
                        </ul>
                    </div>

                    <div class="kb-category">
                        <h3>Platform Management</h3>
                        <ul>
                            <li><a href="#" onclick="loginRequired()">Domain Management</a></li>
                            <li><a href="#" onclick="loginRequired()">User Administration</a></li>
                            <li><a href="#" onclick="loginRequired()">Analytics & Reporting</a></li>
                            <li><a href="#">Billing & Licensing</a></li>
                            <li><a href="#">Monitoring & Alerts</a></li>
                        </ul>
                    </div>

                    <div class="kb-category">
                        <h3>Troubleshooting</h3>
                        <ul>
                            <li><a href="#">Common Issues</a></li>
                            <li><a href="#">Call Quality Problems</a></li>
                            <li><a href="#">Payment Issues</a></li>
                            <li><a href="#">Login Problems</a></li>
                            <li><a href="#">Performance Issues</a></li>
                        </ul>
                    </div>
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
                    <h4>Support</h4>
                    <ul>
                        <li><a href="#ticket-form">Submit Ticket</a></li>
                        <li><a href="#emergency-form">Emergency Support</a></li>
                        <li><a href="#knowledge-base">Documentation</a></li>
                        <li><a href="login.php">Customer Login</a></li>
                    </ul>
                </div>
                <div class="footer-links">
                    <h4>Resources</h4>
                    <ul>
                        <li><a href="migration.php">Migration Guide</a></li>
                        <li><a href="#">API Documentation</a></li>
                        <li><a href="#">System Status</a></li>
                        <li><a href="#">Security</a></li>
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
    <script src="assets/js/support.js"></script>
</body>
</html>