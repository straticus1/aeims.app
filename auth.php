<?php
/**
 * AEIMS Authentication Status Page
 * Shows authentication status and session information
 *
 * Routes to site-specific auth.php for customer sites
 */

// Virtual Host Routing - delegate to site-specific auth
$host = $_SERVER['HTTP_HOST'] ?? '';
$host = preg_replace('/^www\./', '', $host);

if ($host === 'flirts.nyc' && file_exists(__DIR__ . '/sites/flirts.nyc/auth.php')) {
    require_once __DIR__ . '/sites/flirts.nyc/auth.php';
    exit;
}

if ($host === 'nycflirts.com' && file_exists(__DIR__ . '/sites/nycflirts.com/auth.php')) {
    require_once __DIR__ . '/sites/nycflirts.com/auth.php';
    exit;
}

// Default: AEIMS admin authentication status page
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'auth_functions.php';

$config = include 'config.php';

// Handle logout
if (isset($_GET['logout'])) {
    logout();
}

// Get user info if logged in
$userInfo = null;
$isAuthenticated = isLoggedIn();
if ($isAuthenticated) {
    $userInfo = getUserInfo();
}

// Handle different actions
$action = $_GET['action'] ?? '';
$message = '';
$messageType = '';

switch ($action) {
    case 'timeout':
        $message = 'Your session has expired. Please log in again.';
        $messageType = 'warning';
        break;
    case 'access_denied':
        $message = 'Access denied. You do not have permission to access that resource.';
        $messageType = 'error';
        break;
    case 'login_required':
        $message = 'Please log in to access this resource.';
        $messageType = 'info';
        break;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Authentication Status - <?php echo $config['site']['name']; ?></title>
    <meta name="description" content="View your authentication status and session information for the AEIMS platform.">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .auth-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .auth-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .auth-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: #1e293b;
            margin: 0 0 16px 0;
        }

        .auth-subtitle {
            font-size: 1.125rem;
            color: #64748b;
            margin: 0;
        }

        .auth-status {
            background: white;
            border-radius: 12px;
            padding: 32px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid #e2e8f0;
            margin-bottom: 32px;
        }

        .status-indicator {
            display: flex;
            align-items: center;
            margin-bottom: 24px;
        }

        .status-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 12px;
        }

        .status-dot.authenticated {
            background: #10b981;
        }

        .status-dot.unauthenticated {
            background: #ef4444;
        }

        .status-text {
            font-size: 1.25rem;
            font-weight: 600;
        }

        .status-text.authenticated {
            color: #10b981;
        }

        .status-text.unauthenticated {
            color: #ef4444;
        }

        .user-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 24px;
            margin-top: 24px;
        }

        .detail-group {
            padding: 20px;
            background: #f8fafc;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }

        .detail-label {
            font-size: 0.875rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .detail-value {
            font-size: 1rem;
            color: #1f2937;
            font-weight: 500;
        }

        .session-info {
            background: white;
            border-radius: 12px;
            padding: 32px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid #e2e8f0;
            margin-bottom: 32px;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 24px;
        }

        .actions {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            justify-content: center;
            margin-top: 32px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            padding: 12px 24px;
            border: 1px solid transparent;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-primary {
            background: #3b82f6;
            color: white;
        }

        .btn-primary:hover {
            background: #1d4ed8;
        }

        .btn-secondary {
            background: white;
            color: #374151;
            border-color: #d1d5db;
        }

        .btn-secondary:hover {
            background: #f9fafb;
        }

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .alert {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            border: 1px solid;
        }

        .alert-info {
            background: #eff6ff;
            border-color: #bfdbfe;
            color: #1e40af;
        }

        .alert-warning {
            background: #fffbeb;
            border-color: #fed7aa;
            color: #92400e;
        }

        .alert-error {
            background: #fef2f2;
            border-color: #fecaca;
            color: #991b1b;
        }

        .permissions-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .permission-item {
            padding: 8px 12px;
            margin: 4px 0;
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 6px;
            color: #0c4a6e;
            font-size: 0.875rem;
        }

        @media (max-width: 768px) {
            .auth-container {
                padding: 20px 16px;
            }

            .auth-title {
                font-size: 2rem;
            }

            .user-details {
                grid-template-columns: 1fr;
            }

            .actions {
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-header">
            <h1 class="auth-title">Authentication Status</h1>
            <p class="auth-subtitle">View your current session and authentication information</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="auth-status">
            <div class="status-indicator">
                <div class="status-dot <?= $isAuthenticated ? 'authenticated' : 'unauthenticated' ?>"></div>
                <div class="status-text <?= $isAuthenticated ? 'authenticated' : 'unauthenticated' ?>">
                    <?= $isAuthenticated ? 'Authenticated' : 'Not Authenticated' ?>
                </div>
            </div>

            <?php if ($isAuthenticated && $userInfo): ?>
                <div class="user-details">
                    <div class="detail-group">
                        <div class="detail-label">User Name</div>
                        <div class="detail-value"><?= htmlspecialchars($userInfo['name']) ?></div>
                    </div>
                    <div class="detail-group">
                        <div class="detail-label">Email</div>
                        <div class="detail-value"><?= htmlspecialchars($userInfo['email']) ?></div>
                    </div>
                    <div class="detail-group">
                        <div class="detail-label">User Type</div>
                        <div class="detail-value"><?= htmlspecialchars(ucfirst($userInfo['type'])) ?></div>
                    </div>
                    <div class="detail-group">
                        <div class="detail-label">Status</div>
                        <div class="detail-value"><?= htmlspecialchars(ucfirst($userInfo['status'])) ?></div>
                    </div>
                </div>
            <?php else: ?>
                <p>You are currently not logged in to the AEIMS platform.</p>
            <?php endif; ?>
        </div>

        <?php if ($isAuthenticated && $userInfo): ?>
            <div class="session-info">
                <h2 class="section-title">Session Information</h2>
                <div class="user-details">
                    <div class="detail-group">
                        <div class="detail-label">Login Time</div>
                        <div class="detail-value"><?= date('Y-m-d H:i:s', $userInfo['login_time']) ?></div>
                    </div>
                    <div class="detail-group">
                        <div class="detail-label">Last Activity</div>
                        <div class="detail-value"><?= date('Y-m-d H:i:s', $userInfo['last_activity']) ?></div>
                    </div>
                    <div class="detail-group">
                        <div class="detail-label">Session Duration</div>
                        <div class="detail-value"><?= gmdate('H:i:s', time() - $userInfo['login_time']) ?></div>
                    </div>
                    <div class="detail-group">
                        <div class="detail-label">User ID</div>
                        <div class="detail-value"><?= htmlspecialchars($userInfo['id']) ?></div>
                    </div>
                </div>

                <?php if (!empty($userInfo['permissions'])): ?>
                    <h3 style="margin-top: 32px; margin-bottom: 16px; font-size: 1.25rem; color: #1e293b;">Permissions</h3>
                    <ul class="permissions-list">
                        <?php foreach ($userInfo['permissions'] as $permission): ?>
                            <li class="permission-item"><?= htmlspecialchars($permission) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="actions">
            <?php if ($isAuthenticated): ?>
                <a href="dashboard.php" class="btn btn-primary">Go to Dashboard</a>
                <?php if (isAdmin()): ?>
                    <a href="admin-dashboard.php" class="btn btn-secondary">Admin Panel</a>
                <?php endif; ?>
                <a href="?logout=1" class="btn btn-danger">Logout</a>
            <?php else: ?>
                <a href="login.php" class="btn btn-primary">Login</a>
                <a href="index.php" class="btn btn-secondary">Back to Home</a>
            <?php endif; ?>
        </div>

        <div style="text-align: center; margin-top: 40px; padding-top: 32px; border-top: 1px solid #e2e8f0;">
            <p style="color: #64748b; font-size: 0.875rem; margin: 0;">
                Powered by <a href="https://afterdarksys.com" style="color: #3b82f6; text-decoration: none;"><?= $config['site']['company'] ?></a>
            </p>
        </div>
    </div>
</body>
</html>