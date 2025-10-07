<?php
/**
 * Admin Sites API - Missing microservice endpoints
 * Handles site management and authentication for AEIMS virtual hosts
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../database_config_unified.php';

class AdminSitesAPI {
    private $pdo;

    public function __construct() {
        try {
            $this->pdo = getDbConnection();
        } catch (Exception $e) {
            $this->jsonError(500, "Database connection failed: " . $e->getMessage());
        }
    }

    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $pathParts = explode('/', trim($path, '/'));

        // Remove 'api/admin/sites' from path parts
        $pathParts = array_slice($pathParts, 3);

        if (empty($pathParts)) {
            $this->listSites();
            return;
        }

        $domain = $pathParts[0];
        $action = $pathParts[1] ?? '';
        $subAction = $pathParts[2] ?? '';

        switch ($action) {
            case '':
                if ($method === 'GET') {
                    $this->getSite($domain);
                } else {
                    $this->jsonError(405, 'Method not allowed');
                }
                break;

            case 'auth':
                $this->handleAuth($domain, $subAction, $method);
                break;

            case 'users':
                $this->handleUsers($domain, $pathParts, $method);
                break;

            default:
                $this->jsonError(404, 'Endpoint not found');
        }
    }

    private function getSite($domain) {
        // Get site configuration - create default if not exists
        $sites = [
            'nycflirts.com' => [
                'name' => 'NYC Flirts',
                'active' => true,
                'theme' => ['primary_color' => '#ef4444']
            ],
            'flirts.nyc' => [
                'name' => 'Flirts NYC',
                'active' => true,
                'theme' => ['primary_color' => '#3b82f6']
            ],
            'sexacomms.com' => [
                'name' => 'AEIMS Admin',
                'active' => true,
                'theme' => ['primary_color' => '#8b5cf6']
            ],
            'aeims.app' => [
                'name' => 'AEIMS Platform',
                'active' => true,
                'theme' => ['primary_color' => '#10b981']
            ]
        ];

        if (!isset($sites[$domain])) {
            $this->jsonError(404, 'Site not found');
        }

        $this->jsonResponse($sites[$domain]);
    }

    private function handleAuth($domain, $action, $method) {
        switch ($action) {
            case 'login':
                if ($method === 'POST') {
                    $this->loginUser($domain);
                } else {
                    $this->jsonError(405, 'Method not allowed');
                }
                break;

            case 'register':
                if ($method === 'POST') {
                    $this->registerUser($domain);
                } else {
                    $this->jsonError(405, 'Method not allowed');
                }
                break;

            case 'logout':
                if ($method === 'POST') {
                    $this->logoutUser($domain);
                } else {
                    $this->jsonError(405, 'Method not allowed');
                }
                break;

            case 'password-reset':
                if ($method === 'POST') {
                    $this->passwordReset($domain);
                } else {
                    $this->jsonError(405, 'Method not allowed');
                }
                break;

            case 'password-reset-confirm':
                if ($method === 'POST') {
                    $this->passwordResetConfirm($domain);
                } else {
                    $this->jsonError(405, 'Method not allowed');
                }
                break;

            default:
                $this->jsonError(404, 'Auth endpoint not found');
        }
    }

    private function loginUser($domain) {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input['username'] || !$input['password']) {
            $this->jsonError(400, 'Username and password required');
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT id, username, email, password_hash, created_at
                FROM users
                WHERE username = ? AND active = true
            ");
            $stmt->execute([$input['username']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user || !password_verify($input['password'], $user['password_hash'])) {
                $this->jsonError(401, 'Invalid username or password');
            }

            // Generate SSO token
            $ssoToken = $this->generateSSOToken($user['id'], $domain);

            // Store session
            $this->storeSession($user['id'], $ssoToken, $domain);

            $this->jsonResponse([
                'success' => true,
                'customer' => [
                    'customer_id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'name' => $user['username']
                ],
                'sso_token' => $ssoToken
            ]);

        } catch (Exception $e) {
            $this->jsonError(500, 'Login failed: ' . $e->getMessage());
        }
    }

    private function registerUser($domain) {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input['username'] || !$input['email'] || !$input['password']) {
            $this->jsonError(400, 'Username, email and password required');
        }

        try {
            // Check if user exists
            $stmt = $this->pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$input['username'], $input['email']]);
            if ($stmt->fetch()) {
                $this->jsonError(409, 'Username or email already exists');
            }

            // Create user
            $passwordHash = password_hash($input['password'], PASSWORD_DEFAULT);
            $stmt = $this->pdo->prepare("
                INSERT INTO users (username, email, password_hash, created_at, active)
                VALUES (?, ?, ?, NOW(), true)
                RETURNING id
            ");
            $stmt->execute([$input['username'], $input['email'], $passwordHash]);
            $userId = $stmt->fetchColumn();

            $this->jsonResponse([
                'success' => true,
                'customer' => [
                    'customer_id' => $userId,
                    'username' => $input['username'],
                    'email' => $input['email'],
                    'name' => $input['username']
                ]
            ]);

        } catch (Exception $e) {
            $this->jsonError(500, 'Registration failed: ' . $e->getMessage());
        }
    }

    private function logoutUser($domain) {
        $input = json_decode(file_get_contents('php://input'), true);

        if ($input['sso_token']) {
            try {
                $stmt = $this->pdo->prepare("DELETE FROM sessions WHERE sso_token = ?");
                $stmt->execute([$input['sso_token']]);
            } catch (Exception $e) {
                error_log("Logout error: " . $e->getMessage());
            }
        }

        $this->jsonResponse(['success' => true]);
    }

    private function passwordReset($domain) {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input['email']) {
            $this->jsonError(400, 'Email required');
        }

        try {
            $stmt = $this->pdo->prepare("SELECT id, username FROM users WHERE email = ? AND active = true");
            $stmt->execute([$input['email']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                $this->jsonError(404, 'Email not found');
            }

            $resetToken = bin2hex(random_bytes(32));

            // Store reset token (expires in 1 hour)
            $stmt = $this->pdo->prepare("
                INSERT INTO password_resets (user_id, token, expires_at, created_at)
                VALUES (?, ?, NOW() + INTERVAL '1 hour', NOW())
                ON CONFLICT (user_id) DO UPDATE SET
                token = EXCLUDED.token,
                expires_at = EXCLUDED.expires_at,
                created_at = EXCLUDED.created_at
            ");
            $stmt->execute([$user['id'], $resetToken]);

            $this->jsonResponse([
                'success' => true,
                'user_id' => $user['id'],
                'username' => $user['username'],
                'reset_token' => $resetToken
            ]);

        } catch (Exception $e) {
            $this->jsonError(500, 'Password reset failed: ' . $e->getMessage());
        }
    }

    private function passwordResetConfirm($domain) {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input['token'] || !$input['new_password']) {
            $this->jsonError(400, 'Token and new password required');
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT pr.user_id
                FROM password_resets pr
                WHERE pr.token = ? AND pr.expires_at > NOW()
            ");
            $stmt->execute([$input['token']]);
            $reset = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$reset) {
                $this->jsonError(400, 'Invalid or expired reset token');
            }

            // Update password
            $passwordHash = password_hash($input['new_password'], PASSWORD_DEFAULT);
            $stmt = $this->pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$passwordHash, $reset['user_id']]);

            // Delete reset token
            $stmt = $this->pdo->prepare("DELETE FROM password_resets WHERE user_id = ?");
            $stmt->execute([$reset['user_id']]);

            $this->jsonResponse(['success' => true]);

        } catch (Exception $e) {
            $this->jsonError(500, 'Password reset confirmation failed: ' . $e->getMessage());
        }
    }

    private function handleUsers($domain, $pathParts, $method) {
        if (count($pathParts) < 3) {
            $this->jsonError(400, 'Invalid user endpoint');
        }

        $userId = $pathParts[2];
        $action = $pathParts[3] ?? '';

        if ($action === 'activity' && $method === 'POST') {
            $this->logUserActivity($domain, $userId);
        } else {
            $this->jsonError(404, 'User endpoint not found');
        }
    }

    private function logUserActivity($domain, $userId) {
        $input = json_decode(file_get_contents('php://input'), true);

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO user_activity (user_id, action, site, data, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $userId,
                $input['action'] ?? 'unknown',
                $domain,
                json_encode($input['data'] ?? [])
            ]);

            $this->jsonResponse(['success' => true]);

        } catch (Exception $e) {
            $this->jsonError(500, 'Activity logging failed: ' . $e->getMessage());
        }
    }

    private function generateSSOToken($userId, $domain) {
        return base64_encode(json_encode([
            'user_id' => $userId,
            'domain' => $domain,
            'expires' => time() + (24 * 60 * 60), // 24 hours
            'token' => bin2hex(random_bytes(16))
        ]));
    }

    private function storeSession($userId, $ssoToken, $domain) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO sessions (user_id, sso_token, domain, expires_at, created_at)
                VALUES (?, ?, ?, NOW() + INTERVAL '24 hours', NOW())
                ON CONFLICT (user_id, domain) DO UPDATE SET
                sso_token = EXCLUDED.sso_token,
                expires_at = EXCLUDED.expires_at,
                created_at = EXCLUDED.created_at
            ");
            $stmt->execute([$userId, $ssoToken, $domain]);
        } catch (Exception $e) {
            error_log("Session storage failed: " . $e->getMessage());
        }
    }

    private function listSites() {
        $sites = [
            'nycflirts.com' => ['name' => 'NYC Flirts', 'active' => true],
            'flirts.nyc' => ['name' => 'Flirts NYC', 'active' => true],
            'sexacomms.com' => ['name' => 'AEIMS Admin', 'active' => true],
            'aeims.app' => ['name' => 'AEIMS Platform', 'active' => true]
        ];

        $this->jsonResponse($sites);
    }

    private function jsonResponse($data, $status = 200) {
        http_response_code($status);
        echo json_encode($data);
        exit;
    }

    private function jsonError($status, $message) {
        http_response_code($status);
        echo json_encode(['error' => $message]);
        exit;
    }
}

// Handle the request
$api = new AdminSitesAPI();
$api->handleRequest();
?>