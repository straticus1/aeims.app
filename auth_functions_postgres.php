<?php
/**
 * PostgreSQL-based Authentication and Session Management
 * Updated to use AEIMS Core PostgreSQL database
 */

session_start();
require_once 'database_config.php';

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['email']);
}

/**
 * Check if user is admin
 */
function isAdmin() {
    return isLoggedIn() && ($_SESSION['role'] ?? '') === 'admin';
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
 * Login user with email/password
 */
function loginUser($email, $password) {
    try {
        $pdo = getDbConnection();

        $stmt = $pdo->prepare("
            SELECT id, username, email, password_hash, role, first_name, last_name, status, email_verified
            FROM aeims_app_users
            WHERE email = ? AND status = 'active'
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name'] = $user['last_name'];
            $_SESSION['login_time'] = time();
            $_SESSION['last_activity'] = time();

            // Update last login time
            $stmt = $pdo->prepare("UPDATE aeims_app_users SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$user['id']]);

            return true;
        }

        return false;
    } catch (PDOException $e) {
        error_log("Login error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get current user data from database
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }

    try {
        $pdo = getDbConnection();

        $stmt = $pdo->prepare("
            SELECT id, username, email, role, first_name, last_name, phone, company, status,
                   email_verified, created_at, updated_at, last_login
            FROM aeims_app_users
            WHERE id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);

        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Get current user error: " . $e->getMessage());
        return null;
    }
}

/**
 * Get user by ID
 */
function getUserById($userId) {
    try {
        $pdo = getDbConnection();

        $stmt = $pdo->prepare("
            SELECT id, username, email, role, first_name, last_name, phone, company, status,
                   email_verified, created_at, updated_at, last_login
            FROM aeims_app_users
            WHERE id = ?
        ");
        $stmt->execute([$userId]);

        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Get user by ID error: " . $e->getMessage());
        return null;
    }
}

/**
 * Create new user account
 */
function createUser($userData) {
    try {
        $pdo = getDbConnection();

        // Check if user already exists
        $stmt = $pdo->prepare("SELECT id FROM aeims_app_users WHERE email = ? OR username = ?");
        $stmt->execute([$userData['email'], $userData['username']]);
        if ($stmt->fetch()) {
            return ['success' => false, 'error' => 'User already exists'];
        }

        // Insert new user
        $stmt = $pdo->prepare("
            INSERT INTO aeims_app_users (username, email, password_hash, role, first_name, last_name, phone, company, status, email_verified)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            RETURNING id
        ");

        $passwordHash = password_hash($userData['password'], PASSWORD_DEFAULT);

        $stmt->execute([
            $userData['username'],
            $userData['email'],
            $passwordHash,
            $userData['role'] ?? 'customer',
            $userData['first_name'] ?? '',
            $userData['last_name'] ?? '',
            $userData['phone'] ?? '',
            $userData['company'] ?? '',
            $userData['status'] ?? 'active',
            $userData['email_verified'] ?? false
        ]);

        $result = $stmt->fetch();
        return ['success' => true, 'user_id' => $result['id']];

    } catch (PDOException $e) {
        error_log("Create user error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Database error'];
    }
}

/**
 * Get all domains from config and database
 */
function getAllDomains() {
    try {
        $pdo = getDbConnection();

        $stmt = $pdo->query("
            SELECT domain_name, theme, description, services, status
            FROM aeims_app_domains
            ORDER BY domain_name
        ");

        $domains = [];
        while ($row = $stmt->fetch()) {
            $domains[$row['domain_name']] = [
                'domain' => $row['domain_name'],
                'theme' => $row['theme'],
                'description' => $row['description'],
                'services' => json_decode($row['services'], true),
                'status' => $row['status']
            ];
        }

        return $domains;
    } catch (PDOException $e) {
        error_log("Get all domains error: " . $e->getMessage());

        // Fallback to config file
        $config = include __DIR__ . '/config.php';
        $domains = [];

        if (isset($config['powered_sites'])) {
            foreach ($config['powered_sites'] as $site) {
                $domains[$site['domain']] = $site;
            }
        }

        return $domains;
    }
}

/**
 * Get user domains (admin sees all, others see assigned)
 */
function getUserDomains($userId = null) {
    $user = $userId ? getUserById($userId) : getCurrentUser();
    if (!$user) {
        return [];
    }

    if ($user['role'] === 'admin') {
        return getAllDomains();
    }

    // For now, return all domains - implement user-specific domain access later
    return getAllDomains();
}

/**
 * Update user session activity
 */
function updateSessionActivity() {
    if (isLoggedIn()) {
        $_SESSION['last_activity'] = time();

        // Update session in database
        try {
            $pdo = getDbConnection();
            $sessionId = session_id();

            $stmt = $pdo->prepare("
                INSERT INTO aeims_app_user_sessions (id, user_id, ip_address, user_agent, expires_at, data)
                VALUES (?, ?, ?, ?, ?, ?)
                ON CONFLICT (id) DO UPDATE SET
                    expires_at = EXCLUDED.expires_at,
                    data = EXCLUDED.data
            ");

            $expiresAt = date('Y-m-d H:i:s', time() + (30 * 60)); // 30 minutes
            $data = json_encode(['last_activity' => time()]);

            $stmt->execute([
                $sessionId,
                $_SESSION['user_id'],
                $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                $_SERVER['HTTP_USER_AGENT'] ?? '',
                $expiresAt,
                $data
            ]);
        } catch (PDOException $e) {
            error_log("Update session activity error: " . $e->getMessage());
        }
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
    if (isLoggedIn()) {
        try {
            $pdo = getDbConnection();
            $sessionId = session_id();

            // Remove session from database
            $stmt = $pdo->prepare("DELETE FROM aeims_app_user_sessions WHERE id = ?");
            $stmt->execute([$sessionId]);
        } catch (PDOException $e) {
            error_log("Logout error: " . $e->getMessage());
        }
    }

    session_destroy();
    header('Location: login.php?logged_out=1');
    exit();
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
        'username' => $user['username'],
        'email' => $user['email'],
        'name' => trim($user['first_name'] . ' ' . $user['last_name']),
        'role' => $user['role'],
        'status' => $user['status'],
        'login_time' => $_SESSION['login_time'] ?? time(),
        'last_activity' => $_SESSION['last_activity'] ?? time()
    ];
}

/**
 * Get support tickets for user
 */
function getUserSupportTickets($userId = null) {
    if (!$userId) {
        $user = getCurrentUser();
        $userId = $user['id'] ?? null;
    }

    if (!$userId) {
        return [];
    }

    try {
        $pdo = getDbConnection();

        $sql = "
            SELECT t.*, u.first_name, u.last_name, u.email as creator_email
            FROM aeims_app_support_tickets t
            LEFT JOIN aeims_app_users u ON t.user_id = u.id
        ";

        $params = [];
        if (!isAdmin()) {
            $sql .= " WHERE t.user_id = ?";
            $params[] = $userId;
        }

        $sql .= " ORDER BY t.created_at DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Get support tickets error: " . $e->getMessage());
        return [];
    }
}

/**
 * Create support ticket
 */
function createSupportTicket($ticketData) {
    try {
        $pdo = getDbConnection();

        // Generate ticket number
        $ticketNumber = 'AEIMS-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));

        $stmt = $pdo->prepare("
            INSERT INTO aeims_app_support_tickets (ticket_number, user_id, subject, message, priority, category, status)
            VALUES (?, ?, ?, ?, ?, ?, 'open')
            RETURNING id
        ");

        $stmt->execute([
            $ticketNumber,
            $ticketData['user_id'],
            $ticketData['subject'],
            $ticketData['message'],
            $ticketData['priority'] ?? 'medium',
            $ticketData['category'] ?? 'general'
        ]);

        $result = $stmt->fetch();
        return ['success' => true, 'ticket_id' => $result['id'], 'ticket_number' => $ticketNumber];

    } catch (PDOException $e) {
        error_log("Create support ticket error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Database error'];
    }
}

// Auto-check session timeout on every page load
checkSessionTimeout();

/**
 * Initialize database and migrate test data if needed
 */
function initializePostgresAuth() {
    // Initialize database schema
    if (!initializeDatabase()) {
        return false;
    }

    // Check if we need to migrate test data
    if (file_exists(__DIR__ . '/test_users.db')) {
        echo "Test SQLite database found. Run migrate_to_postgres.php to migrate data.\n";
    }

    return true;
}

// Initialize on first load
if (!testDbConnection()) {
    error_log("Failed to connect to PostgreSQL database. Please check configuration.");
}
?>