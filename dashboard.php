<?php
require_once 'auth.php';
requireLogin();

$config = include 'config.php';
$user = getCurrentUser();
$userInfo = getUserInfo();
$userDomains = getUserDomains();

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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="dashboard-body">
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="dashboard-sidebar">
            <div class="sidebar-header">
                <a href="index.php" class="logo-link">
                    <h1 class="sidebar-logo"><?php echo $config['site']['name']; ?></h1>
                    <span class="sidebar-subtitle">Customer Portal</span>
                </a>
            </div>

            <nav class="sidebar-nav">
                <ul class="nav-list">
                    <li class="nav-item">
                        <a href="dashboard.php" class="nav-link active">
                            <span class="nav-icon">📊</span>
                            <span class="nav-text">Dashboard</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="setup.php" class="nav-link">
                            <span class="nav-icon">⚙️</span>
                            <span class="nav-text">Initial Setup</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="domains.php" class="nav-link">
                            <span class="nav-icon">🌐</span>
                            <span class="nav-text">Domain Management</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="analytics.php" class="nav-link">
                            <span class="nav-icon">📈</span>
                            <span class="nav-text">Analytics & Stats</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="tickets.php" class="nav-link">
                            <span class="nav-icon">🎫</span>
                            <span class="nav-text">Support Tickets</span>
                        </a>
                    </li>
                    <?php if (isAdmin()): ?>
                    <li class="nav-divider"></li>
                    <li class="nav-item">
                        <a href="admin-dashboard.php" class="nav-link admin-link">
                            <span class="nav-icon">👑</span>
                            <span class="nav-text">Admin Panel</span>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>

            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($userInfo['name'], 0, 2)); ?>
                    </div>
                    <div class="user-details">
                        <div class="user-name"><?php echo htmlspecialchars($userInfo['name']); ?></div>
                        <div class="user-type"><?php echo ucfirst($userInfo['type']); ?></div>
                    </div>
                </div>
                <a href="?logout=1" class="logout-btn" title="Logout">🚪</a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="dashboard-main">
            <header class="dashboard-header">
                <div class="header-content">
                    <h1>Welcome back, <?php echo htmlspecialchars(explode(' ', $userInfo['name'])[0]); ?>!</h1>
                    <p>Manage your AEIMS platform from your centralized dashboard</p>
                </div>
                <div class="header-actions">
                    <button class="btn btn-secondary" onclick="showQuickHelp()">Quick Help</button>
                    <a href="support.php" class="btn btn-primary">Get Support</a>
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
                        <div class="stat-number">1,247</div>
                        <div class="stat-label">Calls Today</div>
                    </div>
                    <div class="stat-change positive">+15.3%</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">💬</div>
                    <div class="stat-content">
                        <div class="stat-number">3,856</div>
                        <div class="stat-label">Messages Today</div>
                    </div>
                    <div class="stat-change positive">+8.7%</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">💰</div>
                    <div class="stat-content">
                        <div class="stat-number">$12,458</div>
                        <div class="stat-label">Revenue Today</div>
                    </div>
                    <div class="stat-change positive">+22.1%</div>
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
                            <a href="tickets.php?action=create" class="quick-action">
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
                        <a href="tickets.php" class="card-action">View All</a>
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
                        <a href="tickets.php?action=create" class="btn btn-outline btn-sm">Create New Ticket</a>
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

    <script src="assets/js/dashboard.js"></script>
</body>
</html>