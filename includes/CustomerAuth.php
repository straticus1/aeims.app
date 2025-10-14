<?php
/**
 * AEIMS Customer Authentication System
 * File-based authentication for customer sites
 */

class CustomerAuth {
    private $dataFile;
    private $currentDomain;

    public function __construct($domain = null) {
        $this->currentDomain = $domain ?? $_SERVER['HTTP_HOST'] ?? 'nycflirts.com';
        $this->currentDomain = preg_replace('/^www\./', '', $this->currentDomain);
        $this->dataFile = __DIR__ . '/../data/customers.json';
    }

    /**
     * Check if customer is logged in
     */
    public function isLoggedIn() {
        return isset($_SESSION['customer_id']) &&
               isset($_SESSION['customer_data']);
    }

    /**
     * Authenticate customer login
     */
    public function authenticate($username, $password) {
        $customers = $this->loadCustomers();

        if (empty($customers)) {
            $this->logLoginAttempt($username, false, 'No customers found');
            return ['success' => false, 'message' => 'Authentication system error'];
        }

        // Find customer by username
        $customer = null;
        foreach ($customers as $cust) {
            if (($cust['username'] ?? '') === $username || ($cust['email'] ?? '') === $username) {
                $customer = $cust;
                break;
            }
        }

        if (!$customer) {
            $this->logLoginAttempt($username, false, 'Customer not found');
            return ['success' => false, 'message' => 'Invalid username or password'];
        }

        // Check if customer is active
        if (!($customer['active'] ?? true)) {
            $this->logLoginAttempt($username, false, 'Account inactive');
            return ['success' => false, 'message' => 'Account is not active'];
        }

        // Verify password
        if (!password_verify($password, $customer['password_hash'] ?? '')) {
            $this->logLoginAttempt($username, false, 'Invalid password');
            return ['success' => false, 'message' => 'Invalid username or password'];
        }

        // Create session
        $this->createSession($customer);

        $this->logLoginAttempt($username, true, 'Successful login');
        $this->logActivity($customer['customer_id'], 'login');

        return ['success' => true, 'redirect' => '/'];
    }

    /**
     * Register new customer
     */
    public function register($username, $email, $password) {
        $customers = $this->loadCustomers();

        // Check if username or email already exists
        foreach ($customers as $cust) {
            if (($cust['username'] ?? '') === $username) {
                return ['success' => false, 'message' => 'Username already taken'];
            }
            if (($cust['email'] ?? '') === $email) {
                return ['success' => false, 'message' => 'Email already registered'];
            }
        }

        // Create new customer
        $customerId = 'cust_' . bin2hex(random_bytes(8));
        $customer = [
            'customer_id' => $customerId,
            'username' => $username,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'site_domain' => $this->currentDomain,
            'active' => true,
            'verified' => false,
            'created_at' => date('c'),
            'billing' => [
                'credits' => 10.00 // Welcome credits
            ],
            'profile' => [
                'display_name' => $username
            ]
        ];

        // Add to customers array
        $customers[] = $customer;

        // Save to file
        $this->saveCustomers($customers);

        // Create session
        $this->createSession($customer);

        $this->logActivity($customerId, 'register');

        // Send welcome message
        $this->sendWelcomeMessage($customerId);

        return ['success' => true, 'customer' => $customer];
    }

    /**
     * Create customer session
     */
    private function createSession($customer) {
        $_SESSION['customer_id'] = $customer['customer_id'];
        $_SESSION['customer_data'] = $customer;
        $_SESSION['site_domain'] = $this->currentDomain;
        $_SESSION['customer_login_time'] = time();
        $_SESSION['customer_last_activity'] = time();
    }

    /**
     * Get current customer data
     */
    public function getCurrentCustomer() {
        if (!$this->isLoggedIn()) {
            return null;
        }

        $customerId = $_SESSION['customer_id'];
        $customers = $this->loadCustomers();

        foreach ($customers as $customer) {
            if ($customer['customer_id'] === $customerId) {
                return $customer;
            }
        }

        return null;
    }

    /**
     * Check session timeout
     */
    public function checkSessionTimeout() {
        if ($this->isLoggedIn()) {
            $timeout = 24 * 60 * 60; // 24 hours for customers
            $lastActivity = $_SESSION['customer_last_activity'] ?? time();

            if ((time() - $lastActivity) > $timeout) {
                $this->logout();
                return false;
            }

            $_SESSION['customer_last_activity'] = time();
        }

        return true;
    }

    /**
     * Logout customer
     */
    public function logout() {
        if ($this->isLoggedIn()) {
            $this->logActivity($_SESSION['customer_id'], 'logout');
        }

        session_destroy();
    }

    /**
     * Require customer login
     */
    public function requireLogin() {
        if (!$this->checkSessionTimeout() || !$this->isLoggedIn()) {
            header('Location: /?login=required');
            exit();
        }
    }

    /**
     * Load customers from file
     */
    private function loadCustomers() {
        if (!file_exists($this->dataFile)) {
            return [];
        }

        $data = json_decode(file_get_contents($this->dataFile), true);

        // Handle nested structure (customers.cust_xxx) or flat array
        if (isset($data['customers']) && is_array($data['customers'])) {
            // Convert object structure to array
            $customers = [];
            foreach ($data['customers'] as $key => $customer) {
                if (is_array($customer)) {
                    $customers[] = $customer;
                }
            }
            return $customers;
        }

        return is_array($data) ? $data : [];
    }

    /**
     * Save customers to file
     */
    private function saveCustomers($customers) {
        $dataDir = dirname($this->dataFile);

        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }

        // Read existing structure to preserve format
        $existingData = [];
        if (file_exists($this->dataFile)) {
            $existingData = json_decode(file_get_contents($this->dataFile), true) ?? [];
        }

        // If existing has 'customers' key, maintain that structure
        if (isset($existingData['customers'])) {
            $customersObj = [];
            foreach ($customers as $customer) {
                $customersObj[$customer['customer_id']] = $customer;
            }
            $existingData['customers'] = $customersObj;
            $data = $existingData;
        } else {
            $data = ['customers' => $customers];
        }

        file_put_contents($this->dataFile, json_encode($data, JSON_PRETTY_PRINT));
    }

    /**
     * Log login attempts
     */
    private function logLoginAttempt($username, $success, $reason = '') {
        $logEntry = [
            'timestamp' => date('c'),
            'site' => $this->currentDomain,
            'username' => $username,
            'success' => $success,
            'reason' => $reason,
            'ip' => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ];

        $logFile = dirname($this->dataFile) . '/customer-login.log';
        $logDir = dirname($logFile);

        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        file_put_contents($logFile, json_encode($logEntry) . "\n", FILE_APPEND | LOCK_EX);
    }

    /**
     * Log customer activity
     */
    private function logActivity($customerId, $action, $data = []) {
        $activityFile = dirname($this->dataFile) . '/customer_activity.json';

        $activities = [];
        if (file_exists($activityFile)) {
            $activities = json_decode(file_get_contents($activityFile), true) ?? [];
        }

        $activity = [
            'timestamp' => date('c'),
            'customer_id' => $customerId,
            'action' => $action,
            'site' => $this->currentDomain,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'data' => $data
        ];

        $activities[] = $activity;

        // Keep only last 10000 activities
        if (count($activities) > 10000) {
            $activities = array_slice($activities, -10000);
        }

        file_put_contents($activityFile, json_encode($activities, JSON_PRETTY_PRINT));
    }

    /**
     * Validate email format
     */
    public function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Send welcome message to new customer
     */
    private function sendWelcomeMessage($customerId) {
        // Load site manager to get site name
        require_once __DIR__ . '/../services/SiteManager.php';
        $siteManager = new \AEIMS\Services\SiteManager();

        try {
            $site = $siteManager->getSite($this->currentDomain);
            $siteName = $site['name'] ?? $this->currentDomain;
        } catch (Exception $e) {
            $siteName = $this->currentDomain;
        }

        // Create welcome message
        $messagesFile = dirname($this->dataFile) . '/messages.json';
        $messages = [];

        if (file_exists($messagesFile)) {
            $messages = json_decode(file_get_contents($messagesFile), true) ?? [];
        }

        $welcomeMessage = [
            'message_id' => uniqid('msg_', true),
            'conversation_id' => 'system_welcome_' . $customerId,
            'sender_id' => 'system',
            'sender_type' => 'system',
            'sender_name' => $siteName . ' Team',
            'recipient_id' => $customerId,
            'content' => "Welcome to {$siteName}! 🎉\n\nThis is a test message. Thank you for joining us!\n\nWe're excited to have you here. Feel free to explore our features:\n• Browse our operators\n• Join private rooms\n• Send messages\n• And much more!\n\nIf you have any questions, our support team is here to help.\n\nEnjoy your experience! ✨",
            'timestamp' => date('Y-m-d H:i:s'),
            'read' => false,
            'type' => 'system_welcome'
        ];

        $messages[] = $welcomeMessage;
        file_put_contents($messagesFile, json_encode($messages, JSON_PRETTY_PRINT));

        return true;
    }
}
