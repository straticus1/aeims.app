<?php
/**
 * AEIMS Operator Authentication System
 * Specialized authentication for cross-domain operators
 */

class OperatorAuth {
    private $config;
    private $aeimsIntegration;
    
    public function __construct() {
        session_start();
        $this->config = include dirname(__DIR__) . '/config.php';
        
        // Include AEIMS integration from parent directory
        require_once dirname(dirname(__DIR__)) . '/includes/AeimsIntegration.php';
        
        try {
            $this->aeimsIntegration = new AeimsIntegration();
        } catch (Exception $e) {
            error_log("AEIMS integration failed in OperatorAuth: " . $e->getMessage());
            $this->aeimsIntegration = null;
        }
    }
    
    /**
     * Check if operator is logged in
     */
    public function isLoggedIn() {
        return isset($_SESSION['operator_id']) && 
               isset($_SESSION['operator_username']) && 
               isset($_SESSION['operator_domains']);
    }
    
    /**
     * Authenticate operator login
     */
    public function authenticate($username, $password) {
        $operatorsFile = dirname(__DIR__) . '/data/operators.json';
        
        if (!file_exists($operatorsFile)) {
            $operators = $this->createDefaultOperators();
        } else {
            $operators = json_decode(file_get_contents($operatorsFile), true) ?? [];
        }
        
        // Find operator by email
        $operator = null;
        $foundUsername = null;
        
        foreach ($operators as $op) {
            if ($op['email'] === $username) {
                $operator = $op;
                $foundUsername = $op['email'];
                break;
            }
        }
        
        if (!$operator) {
            $this->logLoginAttempt($username, false, 'User not found');
            return ['success' => false, 'error' => 'Invalid credentials'];
        }
        
        // Check if operator is active
        if (($operator['status'] ?? 'active') !== 'active') {
            $this->logLoginAttempt($username, false, 'Account inactive');
            return ['success' => false, 'error' => 'Account is not active'];
        }
        
        // Verify password
        if (!password_verify($password, $operator['password_hash'])) {
            $this->logLoginAttempt($username, false, 'Invalid password');
            return ['success' => false, 'error' => 'Invalid credentials'];
        }
        
        // Set session
        $this->createSession($operator, $foundUsername);
        
        $this->logLoginAttempt($username, true, 'Successful login');
        
        return ['success' => true, 'redirect' => 'dashboard.php'];
    }
    
    /**
     * Create operator session
     */
    private function createSession($operator, $username) {
        $_SESSION['operator_id'] = $operator['id'];
        $_SESSION['operator_username'] = $username;
        $_SESSION['operator_name'] = $operator['name'];
        $_SESSION['operator_email'] = $operator['email'];
        $_SESSION['operator_domains'] = $operator['domains'] ?? [];
        $_SESSION['operator_services'] = $operator['services'] ?? [];
        $_SESSION['operator_verified'] = $operator['verified'] ?? false;
        $_SESSION['operator_login_time'] = time();
        $_SESSION['operator_last_activity'] = time();
    }
    
    /**
     * Get current operator data
     */
    public function getCurrentOperator() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        return $this->loadOperatorById($_SESSION['operator_id']);
    }
    
    /**
     * Load operator by ID
     */
    private function loadOperatorById($operatorId) {
        $operatorsFile = dirname(__DIR__) . '/data/operators.json';
        
        if (!file_exists($operatorsFile)) {
            return null;
        }
        
        $operators = json_decode(file_get_contents($operatorsFile), true) ?? [];
        
        foreach ($operators as $operator) {
            if ($operator['id'] === $operatorId) {
                return $operator;
            }
        }
        
        return null;
    }
    
    /**
     * Get operator domains
     */
    public function getOperatorDomains() {
        if (!$this->isLoggedIn()) {
            return [];
        }
        
        $operator = $this->getCurrentOperator();
        if (!$operator || !isset($operator['domains'])) {
            return [];
        }
        
        $operatorDomains = $operator['domains'];
        $availableDomains = $this->config['domains'];
        
        // Filter available domains by operator access
        $accessibleDomains = [];
        foreach ($operatorDomains as $domain => $info) {
            if (isset($availableDomains[$domain]) && $info['active']) {
                $accessibleDomains[$domain] = $availableDomains[$domain];
            }
        }
        
        return $accessibleDomains;
    }
    
    /**
     * Check session timeout
     */
    public function checkSessionTimeout() {
        if ($this->isLoggedIn()) {
            $timeout = 8 * 60 * 60; // 8 hours for operators
            $lastActivity = $_SESSION['operator_last_activity'] ?? time();
            
            if ((time() - $lastActivity) > $timeout) {
                $this->logout();
                return false;
            }
            
            $_SESSION['operator_last_activity'] = time();
        }
        
        return true;
    }
    
    /**
     * Logout operator
     */
    public function logout() {
        if ($this->isLoggedIn()) {
            $this->logLoginAttempt($_SESSION['operator_username'], true, 'Logout');
        }
        
        session_destroy();
        header('Location: login.php?logged_out=1');
        exit();
    }
    
    /**
     * Require operator login
     */
    public function requireLogin() {
        if (!$this->checkSessionTimeout() || !$this->isLoggedIn()) {
            header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
            exit();
        }
    }
    
    /**
     * Load operator accounts
     */
    private function loadOperators() {
        $operatorsFile = dirname(__DIR__) . '/data/operators.json';
        
        if (!file_exists($operatorsFile)) {
            return $this->createDefaultOperators();
        }
        
        return json_decode(file_get_contents($operatorsFile), true) ?? [];
    }
    
    /**
     * Create default operator accounts for demo
     */
    private function createDefaultOperators() {
        $defaultOperators = [
            'sarah_jones' => [
                'id' => 'op_001',
                'name' => 'Sarah Jones',
                'email' => 'sarah@example.com',
                'password' => password_hash('demo123', PASSWORD_DEFAULT),
                'phone' => '+1-555-0101',
                'status' => 'active',
                'verified' => true,
                'created_at' => date('c'),
                'domains' => ['beastybitches.com', 'cavern.love', 'nycflirts.com'],
                'services' => ['calls', 'text', 'chat', 'video'],
                'profile' => [
                    'bio' => 'Experienced and friendly operator specializing in intimate conversations.',
                    'age' => 28,
                    'location' => 'New York, NY',
                    'specialties' => ['GFE', 'Roleplay', 'Domination']
                ],
                'settings' => [
                    'calls_enabled' => true,
                    'text_enabled' => true,
                    'chat_enabled' => true,
                    'video_enabled' => true
                ]
            ],
            'maya_red' => [
                'id' => 'op_002', 
                'name' => 'Maya Red',
                'email' => 'maya@example.com',
                'password' => password_hash('demo456', PASSWORD_DEFAULT),
                'phone' => '+1-555-0102',
                'status' => 'active',
                'verified' => true,
                'created_at' => date('c'),
                'domains' => ['holyflirts.com', 'dommecats.com', 'fantasyflirts.live'],
                'services' => ['calls', 'text', 'chat', 'video', 'domination'],
                'profile' => [
                    'bio' => 'Dominant queen who knows how to make you submit and beg for more.',
                    'age' => 32,
                    'location' => 'Los Angeles, CA',
                    'specialties' => ['Domination', 'Fetish', 'Humiliation']
                ],
                'settings' => [
                    'calls_enabled' => true,
                    'text_enabled' => true,
                    'chat_enabled' => true,
                    'video_enabled' => true
                ]
            ],
            'luna_night' => [
                'id' => 'op_003',
                'name' => 'Luna Night',
                'email' => 'luna@example.com', 
                'password' => password_hash('demo789', PASSWORD_DEFAULT),
                'phone' => '+1-555-0103',
                'status' => 'active',
                'verified' => true,
                'created_at' => date('c'),
                'domains' => ['latenite.love', 'nitetext.com', 'cavern.love'],
                'services' => ['text', 'chat', 'calls'],
                'profile' => [
                    'bio' => 'Your late-night companion for intimate text conversations and calls.',
                    'age' => 25,
                    'location' => 'Miami, FL', 
                    'specialties' => ['Texting', 'Late Night', 'Emotional Support']
                ],
                'settings' => [
                    'calls_enabled' => true,
                    'text_enabled' => true,
                    'chat_enabled' => true,
                    'video_enabled' => false
                ]
            ]
        ];
        
        $operatorsFile = dirname(__DIR__) . '/data/operators.json';
        $dataDir = dirname($operatorsFile);
        
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }
        
        file_put_contents($operatorsFile, json_encode($defaultOperators, JSON_PRETTY_PRINT));
        
        return $defaultOperators;
    }
    
    /**
     * Update operator settings
     */
    public function updateOperatorSettings($operatorId, $settings) {
        $operators = $this->loadOperators();
        
        foreach ($operators as $username => &$operator) {
            if ($operator['id'] === $operatorId) {
                $operator['settings'] = array_merge($operator['settings'] ?? [], $settings);
                $operator['updated_at'] = date('c');
                
                // Save to file
                $operatorsFile = dirname(__DIR__) . '/data/operators.json';
                file_put_contents($operatorsFile, json_encode($operators, JSON_PRETTY_PRINT));
                
                // Update session if it's current operator
                if ($_SESSION['operator_id'] === $operatorId) {
                    // Update relevant session data
                }
                
                return ['success' => true];
            }
        }
        
        return ['success' => false, 'error' => 'Operator not found'];
    }
    
    /**
     * Log login attempts
     */
    private function logLoginAttempt($username, $success, $reason = '') {
        $logEntry = [
            'timestamp' => date('c'),
            'username' => $username,
            'success' => $success,
            'reason' => $reason,
            'ip' => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ];
        
        $logFile = dirname(__DIR__) . '/data/operator-login.log';
        $logDir = dirname($logFile);
        
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        file_put_contents($logFile, json_encode($logEntry) . "\n", FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Get operator statistics
     */
    public function getOperatorStats($operatorId, $domain = null) {
        // In production, this would query the AEIMS system
        if ($this->aeimsIntegration && $this->aeimsIntegration->isAeimsAvailable()) {
            // Try to get real stats from AEIMS
            $stats = $this->aeimsIntegration->executeCommand('operator:stats', [$operatorId]);
            
            if (!isset($stats['error'])) {
                return $stats;
            }
        }
        
        // Fallback to mock stats
        return [
            'calls_today' => rand(5, 25),
            'texts_today' => rand(20, 100),
            'chat_sessions' => rand(3, 15),
            'earnings_today' => rand(50, 300),
            'earnings_week' => rand(200, 1500),
            'earnings_month' => rand(800, 5000),
            'rating' => round(rand(42, 50) / 10, 1),
            'total_customers' => rand(50, 200),
            'repeat_customers' => rand(20, 80)
        ];
    }
    
    /**
     * Generate CSRF token
     */
    public function generateCSRFToken() {
        if (!isset($_SESSION['operator_csrf_token'])) {
            $_SESSION['operator_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['operator_csrf_token'];
    }
    
    /**
     * Verify CSRF token
     */
    public function verifyCSRFToken($token) {
        return isset($_SESSION['operator_csrf_token']) && 
               hash_equals($_SESSION['operator_csrf_token'], $token);
    }
}