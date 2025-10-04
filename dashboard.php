<?php
require_once 'auth_functions.php';
require_once 'includes/AeimsIntegration.php';
require_once 'includes/AeimsApiClient.php';
requireLogin();

$config = include 'config.php';
$user = getCurrentUser();
$userInfo = getUserInfo();

// Initialize AEIMS integration
try {
    $aeims = new AeimsIntegration();
    $aeimsApi = new AeimsApiClient();
    $aeimsAvailable = true;
    
    // Get real user domains from AEIMS
    $realStats = $aeims->getRealStats();
    $userDomains = getUserDomains(); // This could be enhanced to use AEIMS data
} catch (Exception $e) {
    $aeimsAvailable = false;
    $realStats = null;
    $userDomains = getUserDomains();
}

// Handle logout
if (isset($_GET['logout'])) {
    logout();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo $config['site']['name']; ?> Customer Portal</title>
    <meta name="description" content="AEIMS customer dashboard. Manage your domains, view statistics, and access support tools.">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/multi-site.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="dashboard-body">
    <!-- Top Navigation -->
    <header class="top-nav">
        <div class="nav-brand">
            <a href="index.php" class="logo-link">
                <h1 class="nav-logo"><?php echo $config['site']['name']; ?></h1>
                <span class="nav-subtitle">Customer Portal</span>
            </a>
        </div>

        <nav class="main-nav">
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="dashboard.php" class="nav-link active">
                        <span class="nav-icon">📊</span>
                        <span class="nav-text">Dashboard</span>
                    </a>
                </li>

                <li class="nav-item dropdown">
                    <a href="#" class="nav-link dropdown-toggle">
                        <span class="nav-icon">⚙️</span>
                        <span class="nav-text">Configuration</span>
                        <span class="dropdown-arrow">▼</span>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a href="setup.php">Initial Setup</a></li>
                        <li><a href="domains.php">Domain Management</a></li>
                    </ul>
                </li>

                <li class="nav-item dropdown">
                    <a href="#" class="nav-link dropdown-toggle">
                        <span class="nav-icon">📈</span>
                        <span class="nav-text">Analytics</span>
                        <span class="dropdown-arrow">▼</span>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a href="analytics.php">View Analytics</a></li>
                        <li><a href="reports.php">Reports</a></li>
                        <li><a href="exports.php">Data Export</a></li>
                    </ul>
                </li>

                <li class="nav-item dropdown">
                    <a href="#" class="nav-link dropdown-toggle">
                        <span class="nav-icon">🎫</span>
                        <span class="nav-text">Support</span>
                        <span class="dropdown-arrow">▼</span>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a href="ticket-manager.php">Support Tickets</a></li>
                        <li><a href="support.php">Contact Support</a></li>
                        <li><a href="support.php#knowledge-base">Knowledge Base</a></li>
                    </ul>
                </li>

                <?php if (isAdmin()): ?>
                <li class="nav-item">
                    <a href="admin-dashboard.php" class="nav-link admin-link">
                        <span class="nav-icon">👑</span>
                        <span class="nav-text">Admin Panel</span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>

        <div class="user-menu">
            <div class="user-dropdown">
                <button class="user-btn">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($userInfo['name'], 0, 2)); ?>
                    </div>
                    <div class="user-details">
                        <div class="user-name"><?php echo htmlspecialchars($userInfo['name']); ?></div>
                        <div class="user-type"><?php echo ucfirst($userInfo['type']); ?></div>
                    </div>
                    <span class="dropdown-arrow">▼</span>
                </button>
                <ul class="user-dropdown-menu">
                    <li><a href="profile.php">Profile Settings</a></li>
                    <li><a href="security.php">Security</a></li>
                    <li><a href="auth.php">🔐 Authentication Status</a></li>
                    <li class="divider"></li>
                    <li><a href="?logout=1">Logout</a></li>
                </ul>
            </div>
        </div>
    </header>

    <div class="dashboard-container">
        <!-- Main Content -->
        <main class="dashboard-main">
            <header class="dashboard-header">
                <div class="header-content">
                    <h1>Welcome back, <?php echo htmlspecialchars(explode(' ', $userInfo['name'])[0]); ?>!</h1>
                    <p>Manage your AEIMS platform from your centralized dashboard</p>
                </div>
                <div class="header-actions">
                    <a href="operator-profile.php" class="btn btn-secondary">👤 Profile</a>
                    <button class="btn btn-secondary" onclick="showQuickHelp()">Quick Help</button>
                    <a href="support.php" class="btn btn-primary">Get Support</a>
                    <a href="?logout=1" class="btn btn-outline" style="color: #ef4444; border-color: #ef4444;">Logout</a>
                </div>
            </header>

            <!-- Quick Stats -->
            <section class="quick-stats">
                <div class="stat-card">
                    <div class="stat-icon">🌐</div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo count($userDomains); ?></div>
                        <div class="stat-label">Active Domains</div>
                    </div>
                    <div class="stat-change positive">+2 this month</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">📞</div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $aeimsAvailable && $realStats ? number_format($realStats['total_calls_today']) : '1,247'; ?></div>
                        <div class="stat-label">Calls Today</div>
                    </div>
                    <div class="stat-change <?php echo $aeimsAvailable ? 'positive' : 'neutral'; ?>">+15.3%</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">💬</div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $aeimsAvailable && $realStats ? number_format($realStats['messages_today']) : '3,856'; ?></div>
                        <div class="stat-label">Messages Today</div>
                    </div>
                    <div class="stat-change <?php echo $aeimsAvailable ? 'positive' : 'neutral'; ?>">+8.7%</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">💰</div>
                    <div class="stat-content">
                        <div class="stat-number">$<?php echo $aeimsAvailable && $realStats ? number_format($realStats['revenue_today']) : '12,458'; ?></div>
                        <div class="stat-label">Revenue Today</div>
                    </div>
                    <div class="stat-change <?php echo $aeimsAvailable ? 'positive' : 'neutral'; ?>">+22.1%</div>
                </div>
            </section>

            <!-- Dashboard Grid -->
            <div class="dashboard-grid">
                <!-- Domain Overview -->
                <section class="dashboard-card">
                    <div class="card-header">
                        <h3>Your Domains</h3>
                        <a href="domains.php" class="card-action">Manage All</a>
                    </div>
                    <div class="card-content">
                        <?php if (empty($userDomains)): ?>
                        <div class="empty-state">
                            <span class="empty-icon">🌐</span>
                            <h4>No domains configured yet</h4>
                            <p>Add your first domain to get started with AEIMS</p>
                            <a href="domains.php?action=add" class="btn btn-primary">Add Domain</a>
                        </div>
                        <?php else: ?>
                        <div class="domain-list">
                            <?php foreach (array_slice($userDomains, 0, 3) as $domain): ?>
                            <div class="domain-item">
                                <div class="domain-info">
                                    <div class="domain-name"><?php echo htmlspecialchars($domain); ?></div>
                                    <div class="domain-status active">Active</div>
                                </div>
                                <div class="domain-stats">
                                    <span class="domain-stat">127 calls</span>
                                    <span class="domain-stat">89% uptime</span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php if (count($userDomains) > 3): ?>
                            <div class="domain-item more">
                                <a href="domains.php">View <?php echo count($userDomains) - 3; ?> more domains...</a>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- Recent Activity -->
                <section class="dashboard-card">
                    <div class="card-header">
                        <h3>Recent Activity</h3>
                        <a href="analytics.php" class="card-action">View All</a>
                    </div>
                    <div class="card-content">
                        <div class="activity-list">
                            <div class="activity-item">
                                <div class="activity-icon success">✅</div>
                                <div class="activity-content">
                                    <div class="activity-title">Domain verification completed</div>
                                    <div class="activity-time">2 hours ago</div>
                                </div>
                            </div>
                            <div class="activity-item">
                                <div class="activity-icon info">📞</div>
                                <div class="activity-content">
                                    <div class="activity-title">Peak call volume reached</div>
                                    <div class="activity-time">4 hours ago</div>
                                </div>
                            </div>
                            <div class="activity-item">
                                <div class="activity-icon warning">⚠️</div>
                                <div class="activity-content">
                                    <div class="activity-title">SSL certificate expires in 30 days</div>
                                    <div class="activity-time">1 day ago</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Quick Actions -->
                <section class="dashboard-card">
                    <div class="card-header">
                        <h3>Quick Actions</h3>
                    </div>
                    <div class="card-content">
                        <div class="quick-actions">
                            <a href="domains.php?action=add" class="quick-action">
                                <span class="action-icon">➕</span>
                                <span class="action-text">Add New Domain</span>
                            </a>
                            <a href="analytics.php" class="quick-action">
                                <span class="action-icon">📊</span>
                                <span class="action-text">View Analytics</span>
                            </a>
                            <a href="support.php#ticket-form" class="quick-action">
                                <span class="action-icon">🎫</span>
                                <span class="action-text">Open Support Ticket</span>
                            </a>
                            <a href="setup.php" class="quick-action">
                                <span class="action-icon">⚙️</span>
                                <span class="action-text">Platform Setup</span>
                            </a>
                        </div>
                    </div>
                </section>

                <!-- System Status -->
                <section class="dashboard-card">
                    <div class="card-header">
                        <h3>System Status</h3>
                        <div class="status-indicator online">All Systems Operational</div>
                    </div>
                    <div class="card-content">
                        <div class="status-list">
                            <div class="status-item">
                                <div class="status-service">Telephony Services</div>
                                <div class="status-badge operational">Operational</div>
                            </div>
                            <div class="status-item">
                                <div class="status-service">Payment Processing</div>
                                <div class="status-badge operational">Operational</div>
                            </div>
                            <div class="status-item">
                                <div class="status-service">Chat Services</div>
                                <div class="status-badge operational">Operational</div>
                            </div>
                            <div class="status-item">
                                <div class="status-service">Analytics API</div>
                                <div class="status-badge operational">Operational</div>
                            </div>
                        </div>
                        <div class="uptime-info">
                            <small>99.9% uptime over the last 30 days</small>
                        </div>
                    </div>
                </section>

                <!-- Support Tickets -->
                <section class="dashboard-card">
                    <div class="card-header">
                        <h3>Support Tickets</h3>
                        <a href="ticket-manager.php" class="card-action">View All</a>
                    </div>
                    <div class="card-content">
                        <div class="ticket-summary">
                            <div class="ticket-stat">
                                <div class="ticket-count">2</div>
                                <div class="ticket-label">Open Tickets</div>
                            </div>
                            <div class="ticket-stat">
                                <div class="ticket-count">1</div>
                                <div class="ticket-label">Pending Response</div>
                            </div>
                            <div class="ticket-stat">
                                <div class="ticket-count">15</div>
                                <div class="ticket-label">Resolved This Month</div>
                            </div>
                        </div>
                        <a href="support.php#ticket-form" class="btn btn-outline btn-sm">Create New Ticket</a>
                    </div>
                </section>

                <!-- Performance Chart -->
                <section class="dashboard-card chart-card">
                    <div class="card-header">
                        <h3>Performance Overview</h3>
                        <div class="chart-controls">
                            <button class="chart-btn active" data-period="24h">24H</button>
                            <button class="chart-btn" data-period="7d">7D</button>
                            <button class="chart-btn" data-period="30d">30D</button>
                        </div>
                    </div>
                    <div class="card-content">
                        <div class="chart-container">
                            <canvas id="performanceChart" width="400" height="200"></canvas>
                        </div>
                    </div>
                </section>
            </div>
        </main>
    </div>

    <!-- Quick Help Modal -->
    <div id="quickHelpModal" class="modal hidden">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Quick Help</h3>
                <button class="modal-close" onclick="hideQuickHelp()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="help-sections">
                    <div class="help-section">
                        <h4>Getting Started</h4>
                        <ul>
                            <li><a href="setup.php">Complete initial platform setup</a></li>
                            <li><a href="domains.php?action=add">Add your first domain</a></li>
                            <li><a href="migration.php">Migrate from existing platform</a></li>
                        </ul>
                    </div>
                    <div class="help-section">
                        <h4>Common Tasks</h4>
                        <ul>
                            <li><a href="domains.php">Manage domains and SSL certificates</a></li>
                            <li><a href="analytics.php">View detailed analytics and reports</a></li>
                            <li><a href="tickets.php">Contact support for assistance</a></li>
                        </ul>
                    </div>
                    <div class="help-section">
                        <h4>Need Help?</h4>
                        <ul>
                            <li><a href="support.php">Browse knowledge base</a></li>
                            <li><a href="support.php#ticket-form">Submit support ticket</a></li>
                            <li><a href="mailto:<?php echo $config['site']['contact_email']; ?>">Email support directly</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Cross-Site Settings Modal -->
    <div id="crossSiteModal" class="modal hidden">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Cross-Site Account Settings</h3>
                <button class="modal-close" onclick="hideCrossSiteSettings()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="cross-site-info">
                    <p>Manage your cross-site login and account linking preferences.</p>

                    <div class="setting-group">
                        <h4>Cross-Site Login</h4>
                        <p>Enable this to use the same login credentials across all authorized sites.</p>
                        <label class="toggle-switch">
                            <input type="checkbox" id="crossSiteEnabled"
                                   <?php echo userHasCrossSiteEnabled($user['username'] ?? '') ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                        <span class="toggle-label">Cross-site login enabled</span>
                    </div>

                    <div class="setting-group">
                        <h4>Authorized Sites</h4>
                        <div class="authorized-sites-list">
                            <?php foreach ($authorizedSites as $site): ?>
                            <div class="authorized-site">
                                <span class="site-domain"><?php echo htmlspecialchars($site['domain']); ?></span>
                                <span class="site-status active">✅ Active</span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="setting-group">
                        <h4>Username Reservation</h4>
                        <p>Your username "<?php echo htmlspecialchars($user['username'] ?? 'N/A'); ?>" is automatically reserved across all sites in the network.</p>
                        <?php if (isUsernameReserved($user['username'] ?? '')): ?>
                        <div class="reservation-status success">
                            ✅ Username reserved across <?php echo count(getUsernameReservationDetails($user['username'] ?? '')['reserved_sites'] ?? []); ?> sites
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="modal-actions">
                    <button class="btn btn-primary" onclick="saveCrossSiteSettings()">Save Settings</button>
                    <button class="btn btn-secondary" onclick="hideCrossSiteSettings()">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/dashboard.js"></script>
    <script>
    function showCrossSiteSettings() {
        document.getElementById('crossSiteModal').classList.remove('hidden');
    }

    function hideCrossSiteSettings() {
        document.getElementById('crossSiteModal').classList.add('hidden');
    }

    function saveCrossSiteSettings() {
        const crossSiteEnabled = document.getElementById('crossSiteEnabled').checked;

        // Send AJAX request to update settings
        fetch('api/update-cross-site-settings.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                cross_site_enabled: crossSiteEnabled
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                hideCrossSiteSettings();
                // Show success message
                showNotification('Cross-site settings updated successfully', 'success');
            } else {
                showNotification('Failed to update settings: ' + data.error, 'error');
            }
        })
        .catch(error => {
            showNotification('Network error: ' + error.message, 'error');
        });
    }

    function showNotification(message, type) {
        // Simple notification system
        const notification = document.createElement('div');
        notification.className = 'notification ' + type;
        notification.textContent = message;
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px;
            border-radius: 5px;
            color: white;
            z-index: 10000;
            background-color: ${type === 'success' ? '#10b981' : '#ef4444'};
        `;

        document.body.appendChild(notification);

        setTimeout(() => {
            notification.remove();
        }, 3000);
    }
    </script>
</body>
</html>