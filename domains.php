<?php
require_once 'auth_functions.php';
requireLogin();

$config = include 'config.php';
$user = getCurrentUser();
$userInfo = getUserInfo();

// Handle domain actions
$action = $_GET['action'] ?? 'list';
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($_POST['action']) {
        case 'add_domain':
            $result = addDomain($_POST);
            $message = $result['message'];
            $messageType = $result['success'] ? 'success' : 'error';
            break;
        case 'delete_domain':
            $result = deleteDomain($_POST['domain_id']);
            $message = $result['message'];
            $messageType = $result['success'] ? 'success' : 'error';
            break;
        case 'update_domain':
            $result = updateDomain($_POST);
            $message = $result['message'];
            $messageType = $result['success'] ? 'success' : 'error';
            break;
    }
}

// Get user domains
$userDomains = getUserDomains();

function addDomain($data) {
    // Validate required fields
    if (empty($data['domain_name']) || empty($data['domain_type'])) {
        return ['success' => false, 'message' => 'Domain name and type are required'];
    }

    // Basic domain validation
    if (!filter_var($data['domain_name'], FILTER_VALIDATE_DOMAIN)) {
        return ['success' => false, 'message' => 'Invalid domain name format'];
    }

    // Load existing domains
    $domainsFile = __DIR__ . '/data/user_domains.json';
    $domains = [];
    if (file_exists($domainsFile)) {
        $content = file_get_contents($domainsFile);
        $domains = json_decode($content, true) ?: [];
    }

    // Create data directory if it doesn't exist
    if (!is_dir(__DIR__ . '/data')) {
        mkdir(__DIR__ . '/data', 0755, true);
    }

    // Check if domain already exists
    foreach ($domains as $domain) {
        if ($domain['domain_name'] === $data['domain_name']) {
            return ['success' => false, 'message' => 'Domain already exists'];
        }
    }

    // Add new domain
    $newDomain = [
        'id' => uniqid(),
        'domain_name' => sanitize($data['domain_name']),
        'domain_type' => sanitize($data['domain_type']),
        'ssl_enabled' => isset($data['ssl_enabled']) ? true : false,
        'status' => 'active',
        'created_at' => date('Y-m-d H:i:s'),
        'user_id' => getCurrentUser()
    ];

    $domains[] = $newDomain;

    // Save domains
    if (file_put_contents($domainsFile, json_encode($domains, JSON_PRETTY_PRINT))) {
        return ['success' => true, 'message' => 'Domain added successfully'];
    }

    return ['success' => false, 'message' => 'Failed to save domain'];
}

function deleteDomain($domainId) {
    $domainsFile = __DIR__ . '/data/user_domains.json';
    if (!file_exists($domainsFile)) {
        return ['success' => false, 'message' => 'No domains found'];
    }

    $content = file_get_contents($domainsFile);
    $domains = json_decode($content, true) ?: [];

    $found = false;
    $domains = array_filter($domains, function($domain) use ($domainId, &$found) {
        if ($domain['id'] === $domainId) {
            $found = true;
            return false;
        }
        return true;
    });

    if (!$found) {
        return ['success' => false, 'message' => 'Domain not found'];
    }

    if (file_put_contents($domainsFile, json_encode(array_values($domains), JSON_PRETTY_PRINT))) {
        return ['success' => true, 'message' => 'Domain deleted successfully'];
    }

    return ['success' => false, 'message' => 'Failed to delete domain'];
}

function updateDomain($data) {
    // Implementation for updating domain
    return ['success' => true, 'message' => 'Domain updated successfully'];
}

function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Domain Management - <?php echo $config['site']['name']; ?> Customer Portal</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
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
                    <a href="dashboard.php" class="nav-link">
                        <span class="nav-icon">📊</span>
                        <span class="nav-text">Dashboard</span>
                    </a>
                </li>

                <li class="nav-item dropdown">
                    <a href="#" class="nav-link dropdown-toggle active">
                        <span class="nav-icon">⚙️</span>
                        <span class="nav-text">Configuration</span>
                        <span class="dropdown-arrow">▼</span>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a href="setup.php">Initial Setup</a></li>
                        <li><a href="domains.php" class="active">Domain Management</a></li>
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
                    <li><a href="?logout=1">Logout</a></li>
                </ul>
            </div>
        </div>
    </header>

    <div class="dashboard-container">
        <main class="dashboard-main">
            <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?>" style="margin-bottom: 20px;">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <header class="dashboard-header">
                <div class="header-content">
                    <h1>Domain Management</h1>
                    <p>Manage your domains and SSL certificates</p>
                </div>
                <div class="header-actions">
                    <a href="#add-domain" class="btn btn-primary">Add New Domain</a>
                    <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
                </div>
            </header>

            <?php if ($action === 'add' || $action === 'edit'): ?>
            <!-- Add/Edit Domain Form -->
            <section id="add-domain" class="dashboard-card">
                <div class="card-header">
                    <h3><?php echo $action === 'edit' ? 'Edit Domain' : 'Add New Domain'; ?></h3>
                </div>
                <div class="card-content">
                    <form method="POST" class="domain-form">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="domain_name">Domain Name *</label>
                                <input type="text" id="domain_name" name="domain_name" required
                                       placeholder="example.com" <?php echo $action === 'edit' ? 'readonly' : ''; ?>>
                                <small>Enter the domain name without http:// or www</small>
                            </div>
                            <div class="form-group">
                                <label for="domain_type">Domain Type *</label>
                                <select id="domain_type" name="domain_type" required>
                                    <option value="">Select Type</option>
                                    <option value="primary">Primary Domain</option>
                                    <option value="secondary">Secondary Domain</option>
                                    <option value="redirect">Redirect Domain</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="ssl_enabled" checked>
                                <span class="checkmark"></span>
                                Enable SSL Certificate (Recommended)
                            </label>
                        </div>

                        <div class="form-actions">
                            <input type="hidden" name="action" value="add_domain">
                            <button type="submit" class="btn btn-primary">Add Domain</button>
                            <a href="domains.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </section>
            <?php endif; ?>

            <!-- Domain List -->
            <section class="dashboard-card">
                <div class="card-header">
                    <h3>Your Domains</h3>
                    <span class="domain-count"><?php echo count($userDomains); ?> domain(s)</span>
                </div>
                <div class="card-content">
                    <?php if (empty($userDomains)): ?>
                    <div class="empty-state">
                        <span class="empty-icon">🌐</span>
                        <h4>No domains configured yet</h4>
                        <p>Add your first domain to get started with AEIMS</p>
                        <a href="?action=add" class="btn btn-primary">Add Your First Domain</a>
                    </div>
                    <?php else: ?>
                    <div class="domain-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>Domain Name</th>
                                    <th>Type</th>
                                    <th>SSL Status</th>
                                    <th>Status</th>
                                    <th>Added</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($userDomains as $domain): ?>
                                <tr>
                                    <td class="domain-name-cell">
                                        <div class="domain-name"><?php echo htmlspecialchars($domain); ?></div>
                                        <div class="domain-url">https://<?php echo htmlspecialchars($domain); ?></div>
                                    </td>
                                    <td><span class="badge badge-primary">Primary</span></td>
                                    <td><span class="ssl-status ssl-active">✅ Active</span></td>
                                    <td><span class="status-badge status-active">Active</span></td>
                                    <td><?php echo date('M j, Y'); ?></td>
                                    <td class="actions">
                                        <button class="btn-icon" title="Edit">✏️</button>
                                        <button class="btn-icon" title="Delete" onclick="confirmDelete('<?php echo htmlspecialchars($domain); ?>')">🗑️</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Domain Configuration Help -->
            <section class="dashboard-card">
                <div class="card-header">
                    <h3>Domain Configuration Help</h3>
                </div>
                <div class="card-content">
                    <div class="help-grid">
                        <div class="help-item">
                            <h4>📋 DNS Configuration</h4>
                            <p>Point your domain's DNS records to our servers for full AEIMS functionality.</p>
                            <a href="support.php#knowledge-base" class="btn btn-outline btn-sm">View DNS Guide</a>
                        </div>
                        <div class="help-item">
                            <h4>🔒 SSL Certificates</h4>
                            <p>Automatically manage SSL certificates for secure connections.</p>
                            <a href="support.php#knowledge-base" class="btn btn-outline btn-sm">SSL Setup Guide</a>
                        </div>
                        <div class="help-item">
                            <h4>🔄 Domain Verification</h4>
                            <p>Verify domain ownership to enable all platform features.</p>
                            <a href="support.php#knowledge-base" class="btn btn-outline btn-sm">Verification Guide</a>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <script>
    function confirmDelete(domainName) {
        if (confirm(`Are you sure you want to delete the domain "${domainName}"? This action cannot be undone.`)) {
            // Create form to submit delete request
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="delete_domain">
                <input type="hidden" name="domain_id" value="${domainName}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    }

    // Auto-hide alerts after 5 seconds
    document.addEventListener('DOMContentLoaded', function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            }, 5000);
        });
    });
    </script>

    <script src="assets/js/dashboard.js"></script>
</body>
</html>