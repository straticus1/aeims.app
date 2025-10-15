<?php
/**
 * AEIMS Security Manager
 * Comprehensive security functions for session management, CSRF, input validation
 *
 * FIXES:
 * - #1: Session Fixation
 * - #3: CSRF Protection
 * - #4: Directory Traversal
 * - #2: Open Redirect
 * - #5: Race Conditions (file locking)
 * - #8: Rate Limiting
 */

class SecurityManager {
    private static $instance = null;
    private $config;

    // Rate limiting storage
    private $rateLimitFile;
    private $rateLimits = [];

    // Allowed redirect hosts
    private $allowedHosts = [
        'aeims.app',
        'www.aeims.app',
        'admin.aeims.app',
        'sexacomms.com',
        'www.sexacomms.com',
        'login.sexacomms.com',
        'flirts.nyc',
        'www.flirts.nyc',
        'nycflirts.com',
        'www.nycflirts.com'
    ];

    private function __construct() {
        $this->config = include __DIR__ . '/../config.php';
        $this->rateLimitFile = __DIR__ . '/../data/rate_limits.json';
        $this->loadRateLimits();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * FIX #1: Session Fixation Protection
     * Initialize secure session with proper configuration
     */
    public function initializeSecureSession() {
        // Don't initialize if session already started
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        // Configure secure session parameters
        $sessionConfig = $this->config['session'] ?? [
            'lifetime' => 7200,
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax'
        ];

        // Extract root domain for cookie (works for both www and non-www)
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $host = preg_replace('/:\d+$/', '', $host); // Remove port
        $cookieDomain = preg_replace('/^www\./', '', $host); // Remove www prefix
        // Add leading dot for subdomain compatibility
        if (!empty($cookieDomain) && substr_count($cookieDomain, '.') > 0) {
            $cookieDomain = '.' . $cookieDomain;
        }

        session_set_cookie_params([
            'lifetime' => $sessionConfig['lifetime'],
            'path' => '/',
            'domain' => $cookieDomain,
            'secure' => $sessionConfig['secure'] && (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'),
            'httponly' => $sessionConfig['httponly'],
            'samesite' => $sessionConfig['samesite']
        ]);

        session_name($sessionConfig['name'] ?? 'AEIMS_SESSION');
        session_start();

        // Initialize session tracking (no regeneration needed - session_start() already created random ID)
        if (!isset($_SESSION['initialized'])) {
            $_SESSION['initialized'] = true;
            $_SESSION['created_at'] = time();
            $_SESSION['last_regeneration'] = time();
        }

        // Periodically regenerate session ID (every 30 minutes)
        // Only regenerate on established sessions to avoid double Set-Cookie headers
        if (isset($_SESSION['initialized']) && isset($_SESSION['last_regeneration']) &&
            (time() - $_SESSION['last_regeneration']) > 1800) {
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }
    }

    /**
     * FIX #1: Regenerate session ID after successful login
     */
    public function regenerateSessionOnLogin() {
        if (session_status() === PHP_SESSION_ACTIVE) {
            // Store old session data
            $oldSessionData = $_SESSION;

            // Regenerate session ID
            session_regenerate_id(true);

            // Restore session data
            $_SESSION = $oldSessionData;
            $_SESSION['last_regeneration'] = time();
            $_SESSION['login_regenerated'] = true;
        }
    }

    /**
     * FIX #3: CSRF Token Generation
     */
    public function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_time'] = time();
        }

        // Regenerate token every hour
        if (isset($_SESSION['csrf_token_time']) &&
            (time() - $_SESSION['csrf_token_time']) > 3600) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_time'] = time();
        }

        return $_SESSION['csrf_token'];
    }

    /**
     * FIX #3: CSRF Token Verification
     */
    public function verifyCSRFToken($token) {
        if (!isset($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }

        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * FIX #3: Get CSRF HTML input field
     */
    public function getCSRFField() {
        $token = $this->generateCSRFToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * FIX #3: Validate CSRF or die
     */
    public function requireCSRF() {
        $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';

        if (!$this->verifyCSRFToken($token)) {
            http_response_code(403);
            die(json_encode([
                'success' => false,
                'error' => 'Invalid CSRF token. Please refresh the page and try again.'
            ]));
        }
    }

    /**
     * FIX #4: Secure File Path Validation
     * Prevents directory traversal attacks
     */
    public function validateFilePath($path, $allowedDirectory) {
        // Remove any null bytes
        $path = str_replace("\0", '', $path);

        // Get the real path
        $realPath = realpath($allowedDirectory . '/' . basename($path));
        $realAllowedDir = realpath($allowedDirectory);

        // Check if file exists and is within allowed directory
        if ($realPath === false || $realAllowedDir === false) {
            return false;
        }

        // Ensure the real path starts with the allowed directory
        if (strpos($realPath, $realAllowedDir) !== 0) {
            return false;
        }

        return $realPath;
    }

    /**
     * FIX #2: Secure Redirect Validation
     * Prevents open redirect vulnerabilities
     */
    public function validateRedirectURL($url) {
        // Empty URL
        if (empty($url)) {
            return false;
        }

        // Allow relative URLs that start with /
        if ($url[0] === '/') {
            // But not protocol-relative URLs
            if (isset($url[1]) && $url[1] === '/') {
                return false;
            }
            return true;
        }

        // Parse the URL
        $parsed = parse_url($url);

        if ($parsed === false || !isset($parsed['host'])) {
            return false;
        }

        // Check if host is in allowed list
        $host = strtolower($parsed['host']);

        return in_array($host, $this->allowedHosts, true);
    }

    /**
     * FIX #2: Safe redirect with validation
     */
    public function safeRedirect($url, $default = '/') {
        // Write session data before redirect
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        if ($this->validateRedirectURL($url)) {
            header('Location: ' . $url);
            exit();
        } else {
            // Log suspicious redirect attempt
            error_log("Suspicious redirect attempt: " . $url);
            header('Location: ' . $default);
            exit();
        }
    }

    /**
     * FIX #8: Rate Limiting
     */
    private function loadRateLimits() {
        if (file_exists($this->rateLimitFile)) {
            $this->rateLimits = json_decode(file_get_contents($this->rateLimitFile), true) ?? [];
        }
    }

    private function saveRateLimits() {
        $dataDir = dirname($this->rateLimitFile);
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }
        file_put_contents($this->rateLimitFile, json_encode($this->rateLimits, JSON_PRETTY_PRINT));
    }

    /**
     * FIX #8: Check rate limit
     * @param string $identifier - IP address or username
     * @param string $action - Action type (login, api_call, etc.)
     * @param int $maxAttempts - Maximum attempts allowed
     * @param int $windowSeconds - Time window in seconds
     * @return bool True if within limit, false if exceeded
     */
    public function checkRateLimit($identifier, $action, $maxAttempts = 5, $windowSeconds = 300) {
        $key = $action . ':' . $identifier;
        $now = time();

        // Initialize if doesn't exist
        if (!isset($this->rateLimits[$key])) {
            $this->rateLimits[$key] = [
                'attempts' => [],
                'blocked_until' => 0
            ];
        }

        $limit = &$this->rateLimits[$key];

        // Check if currently blocked
        if ($limit['blocked_until'] > $now) {
            return false;
        }

        // Clean old attempts outside the window
        $limit['attempts'] = array_filter($limit['attempts'], function($timestamp) use ($now, $windowSeconds) {
            return ($now - $timestamp) < $windowSeconds;
        });

        // Check if limit exceeded
        if (count($limit['attempts']) >= $maxAttempts) {
            // Block for the window duration
            $limit['blocked_until'] = $now + $windowSeconds;
            $this->saveRateLimits();
            return false;
        }

        // Add current attempt
        $limit['attempts'][] = $now;
        $this->saveRateLimits();

        return true;
    }

    /**
     * Get remaining attempts
     */
    public function getRemainingAttempts($identifier, $action, $maxAttempts = 5) {
        $key = $action . ':' . $identifier;

        if (!isset($this->rateLimits[$key])) {
            return $maxAttempts;
        }

        $attempts = count($this->rateLimits[$key]['attempts']);
        return max(0, $maxAttempts - $attempts);
    }

    /**
     * Reset rate limit for identifier
     */
    public function resetRateLimit($identifier, $action) {
        $key = $action . ':' . $identifier;
        unset($this->rateLimits[$key]);
        $this->saveRateLimits();
    }

    /**
     * FIX #5: Safe File Operations with Locking
     */
    public function safeFileRead($filepath) {
        if (!file_exists($filepath)) {
            return null;
        }

        $fp = fopen($filepath, 'r');
        if (!$fp) {
            return null;
        }

        if (flock($fp, LOCK_SH)) {
            $contents = stream_get_contents($fp);
            flock($fp, LOCK_UN);
            fclose($fp);
            return $contents;
        }

        fclose($fp);
        return null;
    }

    /**
     * FIX #5: Safe File Write with Locking
     */
    public function safeFileWrite($filepath, $data) {
        $dir = dirname($filepath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $fp = fopen($filepath, 'c+');
        if (!$fp) {
            return false;
        }

        if (flock($fp, LOCK_EX)) {
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, $data);
            fflush($fp);
            flock($fp, LOCK_UN);
            fclose($fp);
            return true;
        }

        fclose($fp);
        return false;
    }

    /**
     * FIX #5: Safe JSON Read with Locking
     */
    public function safeJSONRead($filepath) {
        $contents = $this->safeFileRead($filepath);
        if ($contents === null) {
            return null;
        }

        return json_decode($contents, true);
    }

    /**
     * FIX #5: Safe JSON Write with Locking
     */
    public function safeJSONWrite($filepath, $data) {
        $json = json_encode($data, JSON_PRETTY_PRINT);
        return $this->safeFileWrite($filepath, $json);
    }

    /**
     * Input Sanitization
     */
    public function sanitizeInput($input, $type = 'string') {
        switch ($type) {
            case 'email':
                return filter_var(trim($input), FILTER_SANITIZE_EMAIL);

            case 'int':
                return filter_var($input, FILTER_SANITIZE_NUMBER_INT);

            case 'float':
                return filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

            case 'url':
                return filter_var(trim($input), FILTER_SANITIZE_URL);

            case 'string':
            default:
                return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
        }
    }

    /**
     * Validate strong password
     */
    public function validatePassword($password) {
        $errors = [];

        if (strlen($password) < 10) {
            $errors[] = 'Password must be at least 10 characters long';
        }

        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter';
        }

        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter';
        }

        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number';
        }

        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = 'Password must contain at least one special character';
        }

        // Check against common passwords
        $commonPasswords = ['password123', 'admin123', 'Welcome123', '1234567890'];
        if (in_array(strtolower($password), array_map('strtolower', $commonPasswords))) {
            $errors[] = 'Password is too common. Please choose a stronger password';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Add allowed redirect host
     */
    public function addAllowedHost($host) {
        if (!in_array($host, $this->allowedHosts)) {
            $this->allowedHosts[] = $host;
        }
    }

    /**
     * Get security headers
     */
    public function getSecurityHeaders() {
        return [
            'X-Frame-Options' => 'DENY',
            'X-Content-Type-Options' => 'nosniff',
            'X-XSS-Protection' => '1; mode=block',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()',
            'Content-Security-Policy' => "default-src 'self'; script-src 'self' 'unsafe-inline' https://fonts.googleapis.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https:; connect-src 'self'"
        ];
    }

    /**
     * Apply security headers
     */
    public function applySecurityHeaders() {
        if (headers_sent()) {
            return;
        }

        foreach ($this->getSecurityHeaders() as $header => $value) {
            header("$header: $value");
        }
    }
}

// Global helper functions
function getSecurityManager() {
    return SecurityManager::getInstance();
}

function csrf_field() {
    return getSecurityManager()->getCSRFField();
}

function csrf_token() {
    return getSecurityManager()->generateCSRFToken();
}

function verify_csrf() {
    return getSecurityManager()->requireCSRF();
}
