<?php
// AEIMS Legal and Compliance Information Page
require_once 'includes/AeimsIntegration.php';
$config = include 'config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Legal and Compliance - <?php echo $config['site']['name']; ?> | <?php echo $config['site']['company']; ?></title>
    <meta name="description" content="Comprehensive legal and compliance information for <?php echo $config['site']['name']; ?> - Federal, state, and international regulatory compliance.">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <header class="header">
        <nav class="nav">
            <div class="nav-brand">
                <h1><a href="index.php"><?php echo $config['site']['name']; ?></a></h1>
                <span class="brand-subtitle"><?php echo $config['site']['company']; ?></span>
            </div>
            <ul class="nav-menu">
                <li><a href="index.php#features">Features</a></li>
                <li><a href="index.php#powered-by">Powered By</a></li>
                <li><a href="index.php#pricing">Pricing</a></li>
                <li><a href="index.php#contact">Contact</a></li>
                <li><a href="legal.php" class="active">Legal</a></li>
            </ul>
            <div class="nav-toggle">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </nav>
    </header>

    <main class="legal-main">
        <div class="container">
            <div class="legal-header">
                <h1>Legal and Compliance Framework</h1>
                <p class="legal-subtitle">Comprehensive regulatory compliance for adult entertainment platforms</p>
                <p class="last-updated">Last Updated: <?php echo date('F j, Y'); ?></p>
            </div>

            <div class="legal-nav">
                <ul class="legal-toc">
                    <li><a href="#federal-compliance">Federal Compliance</a></li>
                    <li><a href="#state-compliance">State Compliance</a></li>
                    <li><a href="#privacy-data">Privacy & Data Protection</a></li>
                    <li><a href="#content-policies">Content Policies</a></li>
                    <li><a href="#enforcement">Enforcement & Monitoring</a></li>
                    <li><a href="#reporting">Reporting & Contact</a></li>
                </ul>
            </div>

            <section id="federal-compliance" class="legal-section">
                <h2>Federal Law Compliance</h2>

                <div class="compliance-item">
                    <h3>18 U.S.C. § 2257 - Record-Keeping Requirements</h3>
                    <div class="compliance-content">
                        <p><strong>Full Compliance Implemented:</strong> All content creators and performers must provide government-issued identification and age verification documentation.</p>
                        <ul>
                            <li>Mandatory record-keeping for all performers</li>
                            <li>Real-time age verification system</li>
                            <li>Secure storage of required documentation</li>
                            <li>Regular auditing and compliance reporting</li>
                            <li>Custodian of Records: Available upon request</li>
                        </ul>
                        <div class="compliance-status active">✓ Fully Compliant</div>
                    </div>
                </div>

                <div class="compliance-item">
                    <h3>FOSTA Act (Fight Online Sex Trafficking Act)</h3>
                    <div class="compliance-content">
                        <p><strong>Zero-Tolerance Policy:</strong> Strict anti-trafficking measures with real-time monitoring and immediate reporting systems.</p>
                        <ul>
                            <li>Advanced AI-powered content screening</li>
                            <li>Real-time suspicious activity detection</li>
                            <li>Automatic FBI and NCMEC reporting</li>
                            <li>Emergency response protocols (5-minute cycles)</li>
                            <li>Law enforcement integration and cooperation</li>
                            <li>Comprehensive audit trails</li>
                        </ul>
                        <div class="compliance-status active">✓ Zero-Tolerance Enforcement</div>
                    </div>
                </div>

                <div class="compliance-item">
                    <h3>Mann Act Compliance</h3>
                    <div class="compliance-content">
                        <p><strong>Anti-Trafficking Protections:</strong> Comprehensive monitoring of interstate activities and transportation-related content.</p>
                        <ul>
                            <li>Interstate activity monitoring</li>
                            <li>Transportation content screening</li>
                            <li>Suspicious activity reporting</li>
                            <li>Automated law enforcement alerts</li>
                        </ul>
                        <div class="compliance-status active">✓ Fully Monitored</div>
                    </div>
                </div>

                <div class="compliance-item">
                    <h3>TAKE IT DOWN Act</h3>
                    <div class="compliance-content">
                        <p><strong>Non-Consensual Content Prevention:</strong> Advanced systems for detecting and removing non-consensual intimate images.</p>
                        <ul>
                            <li>Hash-based content detection</li>
                            <li>24/7 takedown request processing</li>
                            <li>Victim support and assistance</li>
                            <li>Automated content blocking</li>
                        </ul>
                        <div class="compliance-status active">✓ Real-time Protection</div>
                    </div>
                </div>

                <div class="compliance-item">
                    <h3>Federal Obscenity Laws</h3>
                    <div class="compliance-content">
                        <p><strong>Content Standards:</strong> All content is reviewed for compliance with federal obscenity standards and community guidelines.</p>
                        <ul>
                            <li>Miller Test compliance</li>
                            <li>Community standards review</li>
                            <li>Content moderation protocols</li>
                            <li>Geographic content restrictions</li>
                        </ul>
                        <div class="compliance-status active">✓ Standards Enforced</div>
                    </div>
                </div>
            </section>

            <section id="state-compliance" class="legal-section">
                <h2>State Law Compliance</h2>

                <div class="compliance-item">
                    <h3>Florida Compliance (HB 3, § 847.011, § 847.01)</h3>
                    <div class="compliance-content">
                        <p><strong>Florida-Specific Protections:</strong> Enhanced age verification and content restrictions for Florida residents.</p>
                        <ul>
                            <li>IP-based geolocation detection</li>
                            <li>Enhanced age verification for Florida users</li>
                            <li>Strict content filtering and blocking</li>
                            <li>Florida Statute § 847.011 compliance</li>
                            <li>Florida Statute § 847.01 obscenity compliance</li>
                        </ul>
                        <div class="compliance-status active">✓ State-Specific Controls</div>
                    </div>
                </div>

                <div class="compliance-item">
                    <h3>New York SHIELD Act</h3>
                    <div class="compliance-content">
                        <p><strong>Data Protection:</strong> Enhanced cybersecurity and data breach notification requirements.</p>
                        <ul>
                            <li>Advanced encryption protocols</li>
                            <li>Real-time breach detection</li>
                            <li>Automatic notification systems</li>
                            <li>Comprehensive audit logging</li>
                        </ul>
                        <div class="compliance-status active">✓ Enhanced Security</div>
                    </div>
                </div>
            </section>

            <section id="privacy-data" class="legal-section">
                <h2>Privacy and Data Protection</h2>

                <div class="compliance-item">
                    <h3>GDPR Compliance</h3>
                    <div class="compliance-content">
                        <p><strong>European Union Data Protection:</strong> Full compliance with General Data Protection Regulation.</p>
                        <ul>
                            <li>Right to be forgotten implementation</li>
                            <li>Data portability and access rights</li>
                            <li>Consent management systems</li>
                            <li>Data Processing Impact Assessments</li>
                            <li>EU representative appointed</li>
                        </ul>
                        <div class="compliance-status active">✓ GDPR Compliant</div>
                    </div>
                </div>

                <div class="compliance-item">
                    <h3>Data Security Standards</h3>
                    <div class="compliance-content">
                        <ul>
                            <li>End-to-end encryption for all data</li>
                            <li>Multi-factor authentication</li>
                            <li>Regular security audits and penetration testing</li>
                            <li>SOC 2 Type II compliance</li>
                            <li>AWS security best practices</li>
                        </ul>
                    </div>
                </div>
            </section>

            <section id="content-policies" class="legal-section">
                <h2>Content Moderation Policies</h2>

                <div class="compliance-item">
                    <h3>Automated Content Screening</h3>
                    <div class="compliance-content">
                        <ul>
                            <li>AI-powered content analysis using AWS Rekognition</li>
                            <li>Real-time inappropriate content detection</li>
                            <li>Natural language processing for text content</li>
                            <li>Automated flagging and review systems</li>
                        </ul>
                    </div>
                </div>

                <div class="compliance-item">
                    <h3>Human Review Process</h3>
                    <div class="compliance-content">
                        <ul>
                            <li>24/7 human moderation team</li>
                            <li>Escalation procedures for complex cases</li>
                            <li>Appeal and review processes</li>
                            <li>Regular training on legal compliance</li>
                        </ul>
                    </div>
                </div>
            </section>

            <section id="enforcement" class="legal-section">
                <h2>Enforcement and Monitoring</h2>

                <div class="compliance-item">
                    <h3>Real-Time Monitoring Systems</h3>
                    <div class="compliance-content">
                        <ul>
                            <li>Continuous platform monitoring</li>
                            <li>Automated alert systems</li>
                            <li>Immediate response protocols</li>
                            <li>Comprehensive logging and auditing</li>
                        </ul>
                    </div>
                </div>

                <div class="compliance-item">
                    <h3>Law Enforcement Cooperation</h3>
                    <div class="compliance-content">
                        <ul>
                            <li>Direct reporting channels to FBI and NCMEC</li>
                            <li>Rapid response to law enforcement requests</li>
                            <li>Evidence preservation protocols</li>
                            <li>Legal process compliance procedures</li>
                        </ul>
                    </div>
                </div>
            </section>

            <section id="reporting" class="legal-section">
                <h2>Reporting and Contact Information</h2>

                <div class="compliance-item">
                    <h3>Report Violations</h3>
                    <div class="compliance-content">
                        <p>If you encounter content that violates our policies or applicable laws, please report it immediately:</p>
                        <ul>
                            <li><strong>Emergency Reporting:</strong> <a href="mailto:emergency@<?php echo $config['site']['domain'] ?? 'aeims.app'; ?>">emergency@<?php echo $config['site']['domain'] ?? 'aeims.app'; ?></a></li>
                            <li><strong>General Compliance:</strong> <a href="mailto:compliance@<?php echo $config['site']['domain'] ?? 'aeims.app'; ?>">compliance@<?php echo $config['site']['domain'] ?? 'aeims.app'; ?></a></li>
                            <li><strong>Privacy Concerns:</strong> <a href="mailto:privacy@<?php echo $config['site']['domain'] ?? 'aeims.app'; ?>">privacy@<?php echo $config['site']['domain'] ?? 'aeims.app'; ?></a></li>
                            <li><strong>DMCA Takedowns:</strong> <a href="mailto:dmca@<?php echo $config['site']['domain'] ?? 'aeims.app'; ?>">dmca@<?php echo $config['site']['domain'] ?? 'aeims.app'; ?></a></li>
                        </ul>
                    </div>
                </div>

                <div class="compliance-item">
                    <h3>Custodian of Records</h3>
                    <div class="compliance-content">
                        <p>For 18 U.S.C. § 2257 record requests:</p>
                        <address>
                            <strong><?php echo $config['site']['company']; ?></strong><br>
                            Custodian of Records<br>
                            Available upon legal request<br>
                            Email: <a href="mailto:records@<?php echo $config['site']['domain'] ?? 'aeims.app'; ?>">records@<?php echo $config['site']['domain'] ?? 'aeims.app'; ?></a>
                        </address>
                    </div>
                </div>

                <div class="compliance-item">
                    <h3>Legal Notice</h3>
                    <div class="compliance-content">
                        <p>This platform operates in strict compliance with all applicable federal, state, and international laws. We maintain comprehensive monitoring, reporting, and enforcement systems to ensure the safety and legal compliance of all content and activities on our platform.</p>
                        <p><strong>Zero Tolerance:</strong> We have a zero-tolerance policy for illegal content, trafficking, exploitation, or non-consensual material. All violations are immediately reported to appropriate authorities.</p>
                    </div>
                </div>
            </section>

            <div class="legal-footer">
                <p>This compliance framework is regularly updated to reflect changes in applicable laws and regulations. For the most current information, please check this page regularly or contact our compliance team.</p>
                <p><strong>Document Version:</strong> 1.0 | <strong>Effective Date:</strong> <?php echo date('F j, Y'); ?></p>
            </div>
        </div>
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
                        <li><a href="index.php#features">Features</a></li>
                        <li><a href="index.php#powered-by">Sites</a></li>
                        <li><a href="index.php#pricing">Pricing</a></li>
                        <li><a href="index.php#contact">Contact</a></li>
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
</body>
</html>