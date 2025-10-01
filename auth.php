<?php
/**
 * Authentication and Session Management
 */

session_start();

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['username']);
}

/**
 * Check if user is admin
 */
function isAdmin() {
    return isLoggedIn() && ($_SESSION['user_type'] ?? '') === 'admin';
}

/**
 * Require login - redirect to login page if not logged in
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit();
    }
}

/**
 * Require admin access
 */
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: dashboard.php?error=access_denied');
        exit();
    }
}

/**
 * Get current user data
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }

    $accountsFile = __DIR__ . '/data/accounts.json';
    if (!file_exists($accountsFile)) {
        return null;
    }

    $accounts = json_decode(file_get_contents($accountsFile), true) ?? [];
    $username = $_SESSION['username'];

    return $accounts[$username] ?? null;
}

/**
 * Get user domains
 */
function getUserDomains($userId = null) {
    $user = $userId ? getUserById($userId) : getCurrentUser();
    if (!$user) {
        return [];
    }

    if ($user['type'] === 'admin') {
        // Admin can see all domains
        return getAllDomains();
    }

    return $user['domains'] ?? [];
}

/**
 * Get all domains (admin only)
 */
function getAllDomains() {
    $domainsFile = __DIR__ . '/data/domains.json';
    if (!file_exists($domainsFile)) {
        return [];
    }

    return json_decode(file_get_contents($domainsFile), true) ?? [];
}

/**
 * Get user by ID
 */
function getUserById($userId) {
    $accountsFile = __DIR__ . '/data/accounts.json';
    if (!file_exists($accountsFile)) {
        return null;
    }

    $accounts = json_decode(file_get_contents($accountsFile), true) ?? [];

    foreach ($accounts as $username => $user) {
        if ($user['id'] === $userId) {
            return $user;
        }
    }

    return null;
}

/**
 * Update user session activity
 */
function updateSessionActivity() {
    if (isLoggedIn()) {
        $_SESSION['last_activity'] = time();
    }
}

/**
 * Check session timeout (30 minutes)
 */
function checkSessionTimeout() {
    if (isLoggedIn()) {
        $timeout = 30 * 60; // 30 minutes
        $lastActivity = $_SESSION['last_activity'] ?? time();

        if ((time() - $lastActivity) > $timeout) {
            session_destroy();
            header('Location: login.php?timeout=1');
            exit();
        }

        updateSessionActivity();
    }
}

/**
 * Logout user
 */
function logout() {
    session_destroy();
    header('Location: login.php?logged_out=1');
    exit();
}

/**
 * Get user permissions
 */
function getUserPermissions() {
    $user = getCurrentUser();
    if (!$user) {
        return [];
    }

    return $user['permissions'] ?? [];
}

/**
 * Check if user has permission
 */
function hasPermission($permission) {
    $permissions = getUserPermissions();
    return in_array('all', $permissions) || in_array($permission, $permissions);
}

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Get formatted user info for display
 */
function getUserInfo() {
    $user = getCurrentUser();
    if (!$user) {
        return null;
    }

    return [
        'id' => $user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'type' => $user['type'],
        'status' => $user['status'] ?? 'active',
        'login_time' => $_SESSION['login_time'] ?? time(),
        'last_activity' => $_SESSION['last_activity'] ?? time(),
        'permissions' => $user['permissions'] ?? []
    ];
}

// Auto-check session timeout on every page load
checkSessionTimeout();
?>