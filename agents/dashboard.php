<?php
/**
 * AEIMS Agents Portal - Unified Operator Dashboard
 * Cross-Domain Management Interface
 */

require_once 'includes/OperatorAuth.php';

$auth = new OperatorAuth();
$config = include 'config.php';

// Require operator login
$auth->requireLogin();

$operator = $auth->getCurrentOperator();
$operatorDomains = $auth->getOperatorDomains();
$operatorStats = $auth->getOperatorStats($_SESSION['operator_id']);

// Handle AJAX requests
if (isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['ajax_action']) {
        case 'toggle_service':
            $domain = $_POST['domain'] ?? '';
            $service = $_POST['service'] ?? '';
            $enabled = $_POST['enabled'] === 'true';
            
            // In production, this would update the AEIMS system
            $result = $auth->updateOperatorSettings($_SESSION['operator_id'], [
                "services.{$domain}.{$service}" => $enabled
            ]);
            
            echo json_encode($result);
            exit;
            
        case 'update_forwarding':
            $domain = $_POST['domain'] ?? '';
            $forwarding = $_POST['forwarding'] ?? 'direct';
            $number = $_POST['number'] ?? '';
            
            $result = $auth->updateOperatorSettings($_SESSION['operator_id'], [
                "forwarding.{$domain}.type" => $forwarding,
                "forwarding.{$domain}.number" => $number
            ]);
            
            echo json_encode($result);
            exit;
            
        case 'update_availability':
            $status = $_POST['status'] ?? 'offline';
            $domains = $_POST['domains'] ?? [];
            
            $settings = ['availability.status' => $status];
            foreach ($domains as $domain) {
                $settings["availability.domains.{$domain}"] = $status;
            }
            
            $result = $auth->updateOperatorSettings($_SESSION['operator_id'], $settings);
            echo json_encode($result);
            exit;
            
        case 'sync_settings':
            $sourceService = $_POST['source_service'] ?? '';
            $sourceDomain = $_POST['source_domain'] ?? '';
            $targetDomains = $_POST['target_domains'] ?? [];
            
            // Sync settings across domains
            $result = ['success' => true, 'synced_domains' => $targetDomains];
            echo json_encode($result);
            exit;
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    $auth->logout();
}

// Get current availability status
$currentStatus = $operator['settings']['availability']['status'] ?? 'offline';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Operator Dashboard - <?php echo $config['portal']['name']; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #1e1b4b 0%, #3730a3 100%);
            min-height: 100vh;
            color: #e5e7eb;
        }

        .dashboard-container {
            display: grid;
            grid-template-columns: 280px 1fr;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            background: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(10px);
            border-right: 1px solid rgba(148, 163, 184, 0.1);
            padding: 30px 0;
        }

        .sidebar-header {
            padding: 0 30px;
            margin-bottom: 40px;
        }

        .operator-info {
            text-align: center;
            padding: 20px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            margin: 0 20px 30px;
        }

        .operator-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: bold;
            color: white;
            margin: 0 auto 15px;
        }

        .operator-name {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .operator-status {
            font-size: 0.9rem;
            opacity: 0.7;
        }

        .nav-menu {
            list-style: none;
        }

        .nav-item {
            margin-bottom: 5px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 12px 30px;
            text-decoration: none;
            color: #cbd5e1;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .nav-link:hover, .nav-link.active {
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
            border-right: 3px solid #667eea;
        }

        .nav-icon {
            margin-right: 12px;
            font-size: 1.1rem;
        }

        /* Main Content */
        .main-content {
            padding: 30px;
            overflow-y: auto;
        }

        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .header-title h1 {
            font-size: 2rem;
            margin-bottom: 5px;
            color: white;
        }

        .header-title p {
            opacity: 0.7;
        }

        .availability-control {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .status-selector {
            position: relative;
        }

        .status-button {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            color: white;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .status-button:hover {
            background: rgba(255, 255, 255, 0.15);
        }

        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }

        .status-online { background: #22c55e; }
        .status-busy { background: #f59e0b; }
        .status-away { background: #6b7280; }
        .status-offline { background: #ef4444; }
        .status-do_not_disturb { background: #7c3aed; }

        /* Dashboard Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 25px;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .stat-icon {
            font-size: 1.5rem;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: white;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 0.9rem;
            opacity: 0.7;
        }

        .stat-change {
            font-size: 0.85rem;
            font-weight: 500;
        }

        .stat-change.positive { color: #22c55e; }
        .stat-change.negative { color: #ef4444; }

        /* Service Controls */
        .services-section {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 30px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: white;
        }

        .domain-selector {
            padding: 8px 12px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            color: white;
            font-weight: 500;
            cursor: pointer;
        }

        .service-controls {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }

        .service-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 20px;
        }

        .service-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 15px;
        }

        .service-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .service-icon {
            font-size: 1.3rem;
        }

        .service-details h4 {
            color: white;
            margin-bottom: 3px;
        }

        .service-details p {
            font-size: 0.85rem;
            opacity: 0.7;
        }

        /* Toggle Switch */
        .toggle-switch {
            position: relative;
            width: 50px;
            height: 24px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .toggle-switch.active {
            background: #667eea;
        }

        .toggle-slider {
            position: absolute;
            top: 2px;
            left: 2px;
            width: 20px;
            height: 20px;
            background: white;
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .toggle-switch.active .toggle-slider {
            transform: translateX(26px);
        }

        .service-settings {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .setting-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .setting-label {
            font-size: 0.9rem;
            opacity: 0.8;
        }

        .setting-input {
            padding: 6px 10px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 6px;
            color: white;
            font-size: 0.9rem;
            width: 120px;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 30px;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.15);
        }

        .btn-success {
            background: #22c55e;
            color: white;
        }

        .btn-success:hover {
            background: #16a34a;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 1000;
        }

        .modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: linear-gradient(135deg, #1e1b4b 0%, #3730a3 100%);
            padding: 30px;
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            min-width: 500px;
            max-width: 90%;
        }

        .modal-header {
            margin-bottom: 20px;
        }

        .modal-header h3 {
            color: white;
            font-size: 1.5rem;
            margin-bottom: 5px;
        }

        .modal-body {
            margin-bottom: 25px;
        }

        .modal-footer {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .dashboard-container {
                grid-template-columns: 1fr;
            }

            .sidebar {
                position: fixed;
                top: 0;
                left: -280px;
                width: 280px;
                height: 100%;
                z-index: 1000;
                transition: left 0.3s ease;
            }

            .sidebar.mobile-open {
                left: 0;
            }

            .main-content {
                padding: 20px;
            }

            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="operator-info">
                    <div class="operator-avatar">
                        <?php echo strtoupper(substr($operator['name'], 0, 2)); ?>
                    </div>
                    <div class="operator-name"><?php echo htmlspecialchars($operator['name']); ?></div>
                    <div class="operator-status">
                        <span class="status-indicator status-<?php echo $currentStatus; ?>"></span>
                        <?php echo ucfirst(str_replace('_', ' ', $currentStatus)); ?>
                    </div>
                </div>
            </div>

            <nav class="sidebar-nav">
                <ul class="nav-menu">
                    <li class="nav-item">
                        <a href="dashboard.php" class="nav-link active">
                            <span class="nav-icon">📊</span>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="profile.php" class="nav-link">
                            <span class="nav-icon">👤</span>
                            <span>Profile Management</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="services.php" class="nav-link">
                            <span class="nav-icon">⚙️</span>
                            <span>Service Settings</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="earnings.php" class="nav-link">
                            <span class="nav-icon">💰</span>
                            <span>Earnings & Analytics</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="content.php" class="nav-link">
                            <span class="nav-icon">🛍️</span>
                            <span>Content & Sales</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="schedule.php" class="nav-link">
                            <span class="nav-icon">📅</span>
                            <span>Schedule</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="?logout=1" class="nav-link">
                            <span class="nav-icon">🚪</span>
                            <span>Logout</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="dashboard-header">
                <div class="header-title">
                    <h1>Welcome back, <?php echo htmlspecialchars(explode(' ', $operator['name'])[0]); ?>!</h1>
                    <p>Manage your presence across <?php echo count($operatorDomains); ?> domains</p>
                </div>

                <div class="availability-control">
                    <div class="status-selector">
                        <button class="status-button" onclick="showAvailabilityModal()">
                            <span class="status-indicator status-<?php echo $currentStatus; ?>"></span>
                            <?php echo ucfirst(str_replace('_', ' ', $currentStatus)); ?>
                            <span>▼</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Stats Overview -->
            <div class="dashboard-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-icon">📞</span>
                        <span class="stat-change positive">+12%</span>
                    </div>
                    <div class="stat-value"><?php echo $operatorStats['calls_today']; ?></div>
                    <div class="stat-label">Calls Today</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-icon">💬</span>
                        <span class="stat-change positive">+8%</span>
                    </div>
                    <div class="stat-value"><?php echo $operatorStats['texts_today']; ?></div>
                    <div class="stat-label">Messages Today</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-icon">💰</span>
                        <span class="stat-change positive">+15%</span>
                    </div>
                    <div class="stat-value">$<?php echo $operatorStats['earnings_today']; ?></div>
                    <div class="stat-label">Earnings Today</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-icon">⭐</span>
                        <span class="stat-change positive">+0.1</span>
                    </div>
                    <div class="stat-value"><?php echo $operatorStats['rating']; ?></div>
                    <div class="stat-label">Average Rating</div>
                </div>
            </div>

            <!-- Service Controls -->
            <div class="services-section">
                <div class="section-header">
                    <h2 class="section-title">Service Controls</h2>
                    <select class="domain-selector" id="domainSelector">
                        <option value="all">All Domains</option>
                        <?php foreach ($operatorDomains as $domain => $info): ?>
                            <option value="<?php echo $domain; ?>"><?php echo $info['name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="service-controls">
                    <?php foreach ($config['services'] as $serviceId => $service): ?>
                        <?php if (in_array($serviceId, $operator['services'] ?? [])): ?>
                            <div class="service-card">
                                <div class="service-header">
                                    <div class="service-info">
                                        <span class="service-icon"><?php echo $service['icon']; ?></span>
                                        <div class="service-details">
                                            <h4><?php echo $service['name']; ?></h4>
                                            <p><?php echo $service['description']; ?></p>
                                        </div>
                                    </div>
                                    <div class="toggle-switch active" onclick="toggleService('<?php echo $serviceId; ?>')">
                                        <div class="toggle-slider"></div>
                                    </div>
                                </div>

                                <div class="service-settings">
                                    <div class="setting-item">
                                        <span class="setting-label">Forwarding:</span>
                                        <select class="setting-input" onchange="updateForwarding('<?php echo $serviceId; ?>', this.value)">
                                            <option value="direct">Direct</option>
                                            <option value="voicemail">Voicemail</option>
                                            <option value="queue">Queue</option>
                                            <option value="forward_number">Forward</option>
                                        </select>
                                    </div>
                                    <?php if ($serviceId === 'calls'): ?>
                                        <div class="setting-item">
                                            <span class="setting-label">Forward Number:</span>
                                            <input type="tel" class="setting-input" placeholder="+1-555-0000" />
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <button class="btn btn-primary" onclick="showSyncModal()">
                    🔄 Sync Settings Across Domains
                </button>
                <a href="profile.php" class="btn btn-secondary">
                    ✏️ Edit Profile
                </a>
                <button class="btn btn-success" onclick="showAnalyticsModal()">
                    📊 View Detailed Analytics
                </button>
            </div>
        </main>
    </div>

    <!-- Modals -->
    <div id="availabilityModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Update Availability</h3>
                <p>Change your status across all domains</p>
            </div>
            <div class="modal-body">
                <?php foreach ($config['availability']['status_options'] as $status => $info): ?>
                    <div class="setting-item">
                        <label>
                            <input type="radio" name="availability_status" value="<?php echo $status; ?>" 
                                   <?php echo $status === $currentStatus ? 'checked' : ''; ?>>
                            <span class="status-indicator status-<?php echo $status; ?>"></span>
                            <?php echo $info['label']; ?>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('availabilityModal')">Cancel</button>
                <button class="btn btn-primary" onclick="updateAvailability()">Update Status</button>
            </div>
        </div>
    </div>

    <script>
        // Service toggle functionality
        function toggleService(serviceId) {
            const toggle = event.target.closest('.toggle-switch');
            const isActive = toggle.classList.contains('active');
            const domain = document.getElementById('domainSelector').value;
            
            // Toggle UI
            toggle.classList.toggle('active');
            
            // Send AJAX request
            fetch('dashboard.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    ajax_action: 'toggle_service',
                    service: serviceId,
                    domain: domain,
                    enabled: !isActive
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(`${serviceId} ${!isActive ? 'enabled' : 'disabled'}`, 'success');
                } else {
                    // Revert on error
                    toggle.classList.toggle('active');
                    showNotification('Failed to update service', 'error');
                }
            });
        }

        // Modal functions
        function showAvailabilityModal() {
            document.getElementById('availabilityModal').style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function updateAvailability() {
            const status = document.querySelector('input[name="availability_status"]:checked').value;
            const domains = Object.keys(<?php echo json_encode($operatorDomains); ?>);
            
            fetch('dashboard.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    ajax_action: 'update_availability',
                    status: status,
                    domains: domains
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    showNotification('Failed to update availability', 'error');
                }
            });
        }

        // Notification system
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.textContent = message;
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 12px 20px;
                background: ${type === 'success' ? '#22c55e' : type === 'error' ? '#ef4444' : '#3b82f6'};
                color: white;
                border-radius: 8px;
                z-index: 10000;
                animation: slideIn 0.3s ease;
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 3000);
        }

        // Close modals when clicking outside
        window.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        });

        // Auto-refresh stats every 30 seconds
        setInterval(() => {
            location.reload();
        }, 30000);
    </script>
</body>
</html>