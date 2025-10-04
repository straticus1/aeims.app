<?php
/**
 * Site-Specific Authentication Handler
 * Handles login.sitename.com routing and user type detection
 */

require_once 'auth_functions.php';
require_once 'AeimsIntegration.php';

class SiteSpecificAuth {
    private $currentSite;
    private $userTypes = ['operator', 'customer', 'reseller', 'admin'];
    private $aeims;

    public function __construct() {
        $this->detectCurrentSite();
        $this->aeims = new AeimsIntegration();
    }

    /**
     * Detect current site from subdomain or domain
     */
    private function detectCurrentSite() {
        $host = $_SERVER['HTTP_HOST'] ?? '';

        // Handle login.sitename.com format
        if (preg_match('/^login\.(.+)$/', $host, $matches)) {
            $this->currentSite = $matches[1];
            return;
        }

        // Handle direct site access
        $sitesPath = dirname(dirname(dirname(__DIR__))) . '/aeims/sites';
        if (is_dir($sitesPath)) {
            $sites = scandir($sitesPath);
            foreach ($sites as $site) {
                if ($site !== '.' && $site !== '..' && $site !== '_archived' && is_dir($sitesPath . '/' . $site)) {
                    if (strpos($host, $site) !== false) {
                        $this->currentSite = $site;
                        return;
                    }
                }
            }
        }

        // Fallback to aeims.app
        $this->currentSite = 'aeims.app';
    }

    /**
     * Get current site
     */
    public function getCurrentSite() {
        return $this->currentSite;
    }

    /**
     * Authenticate user and determine type
     */
    public function authenticateUser($username, $password) {
        // First check AEIMS.app accounts
        $aeimsUser = $this->authenticateAeimsUser($username, $password);
        if ($aeimsUser) {
            return $aeimsUser;
        }

        // Then check site-specific accounts
        $siteUser = $this->authenticateSiteUser($username, $password);
        if ($siteUser) {
            return $siteUser;
        }

        return null;
    }

    /**
     * Authenticate against AEIMS.app central accounts
     */
    private function authenticateAeimsUser($username, $password) {
        $accountsFile = dirname(__DIR__) . '/data/accounts.json';
        if (!file_exists($accountsFile)) {
            return null;
        }

        $accounts = json_decode(file_get_contents($accountsFile), true) ?? [];

        if (!isset($accounts[$username])) {
            return null;
        }

        $user = $accounts[$username];

        // Verify password (assuming hashed passwords)
        if (!password_verify($password, $user['password'] ?? '')) {
            return null;
        }

        // Check if user has access to current site
        if (!userHasAccessToSite($this->currentSite, $user['id'])) {
            return null;
        }

        return [
            'id' => $user['id'],
            'username' => $username,
            'email' => $user['email'],
            'name' => $user['name'],
            'type' => $user['type'],
            'source' => 'aeims_central',
            'site' => $this->currentSite,
            'authorized_sites' => $user['authorized_sites'] ?? [],
            'cross_site_enabled' => $user['cross_site_enabled'] ?? false
        ];
    }

    /**
     * Authenticate against site-specific accounts
     */
    private function authenticateSiteUser($username, $password) {
        $siteDataPath = dirname(dirname(dirname(__DIR__))) . '/aeims/sites/' . $this->currentSite;
        $usersFile = $siteDataPath . '/users.json';

        if (!file_exists($usersFile)) {
            return null;
        }

        $users = json_decode(file_get_contents($usersFile), true) ?? [];

        if (!isset($users[$username])) {
            return null;
        }

        $user = $users[$username];

        // Verify password
        if (!password_verify($password, $user['password'] ?? '')) {
            return null;
        }

        return [
            'id' => $user['id'] ?? uniqid(),
            'username' => $username,
            'email' => $user['email'],
            'name' => $user['name'] ?? $username,
            'type' => $this->determineUserType($user),
            'source' => 'site_specific',
            'site' => $this->currentSite,
            'site_role' => $user['role'] ?? 'customer',
            'permissions' => $user['permissions'] ?? []
        ];
    }

    /**
     * Determine user type based on user data
     */
    private function determineUserType($user) {
        // Check explicit role first
        $role = $user['role'] ?? '';

        if (in_array($role, $this->userTypes)) {
            return $role;
        }

        // Check permissions to determine type
        $permissions = $user['permissions'] ?? [];

        if (in_array('admin', $permissions) || in_array('all', $permissions)) {
            return 'admin';
        }

        if (in_array('operator', $permissions) ||
            in_array('take_calls', $permissions) ||
            in_array('handle_chats', $permissions)) {
            return 'operator';
        }

        if (in_array('reseller', $permissions) ||
            in_array('manage_customers', $permissions)) {
            return 'reseller';
        }

        // Default to customer
        return 'customer';
    }

    /**
     * Get redirect URL based on user type
     */
    public function getRedirectUrl($user) {
        $userType = $user['type'];
        $site = $user['site'];

        switch ($userType) {
            case 'operator':
                // Operators go to the telephony platform frontend
                return $this->getOperatorDashboardUrl($site);

            case 'admin':
                // Admins go to AEIMS.app admin panel
                if ($user['source'] === 'aeims_central') {
                    return 'https://aeims.app/admin-dashboard.php';
                }
                // Site-specific admins go to site admin
                return $this->getSiteAdminUrl($site);

            case 'reseller':
                // Resellers go to reseller dashboard
                return $this->getResellerDashboardUrl($site);

            case 'customer':
            default:
                // Customers go to customer dashboard
                return $this->getCustomerDashboardUrl($site);
        }
    }

    /**
     * Get operator dashboard URL (telephony platform)
     */
    private function getOperatorDashboardUrl($site) {
        $telephonyUrl = $this->aeims->getApiEndpoint('operator-dashboard');
        return $telephonyUrl . '?site=' . urlencode($site);
    }

    /**
     * Get site admin URL
     */
    private function getSiteAdminUrl($site) {
        return 'https://' . $site . '/admin/';
    }

    /**
     * Get reseller dashboard URL
     */
    private function getResellerDashboardUrl($site) {
        return 'https://' . $site . '/reseller/';
    }

    /**
     * Get customer dashboard URL
     */
    private function getCustomerDashboardUrl($site) {
        return 'https://' . $site . '/customer/';
    }

    /**
     * Create user session
     */
    public function createSession($user) {
        session_start();

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['user_type'] = $user['type'];
        $_SESSION['current_site'] = $user['site'];
        $_SESSION['user_source'] = $user['source'];
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();

        // Store site-specific data
        if (isset($user['authorized_sites'])) {
            $_SESSION['authorized_sites'] = $user['authorized_sites'];
        }

        if (isset($user['cross_site_enabled'])) {
            $_SESSION['cross_site_enabled'] = $user['cross_site_enabled'];
        }

        return true;
    }

    /**
     * Get site configuration
     */
    public function getSiteConfig() {
        $siteDataPath = dirname(dirname(dirname(__DIR__))) . '/aeims/sites/' . $this->currentSite;
        $configFile = $siteDataPath . '/config.json';

        if (file_exists($configFile)) {
            return json_decode(file_get_contents($configFile), true) ?? [];
        }

        return [
            'site_name' => $this->currentSite,
            'theme' => 'default',
            'features' => ['chat', 'voice', 'video'],
            'login_logo' => '/assets/images/logo.png'
        ];
    }

    /**
     * Initialize site-specific user data files
     */
    public function initializeSiteData() {
        $siteDataPath = dirname(dirname(dirname(__DIR__))) . '/aeims/sites/' . $this->currentSite;

        if (!is_dir($siteDataPath)) {
            mkdir($siteDataPath, 0755, true);
        }

        // Initialize users.json
        $usersFile = $siteDataPath . '/users.json';
        if (!file_exists($usersFile)) {
            file_put_contents($usersFile, json_encode([], JSON_PRETTY_PRINT));
        }

        // Initialize config.json
        $configFile = $siteDataPath . '/config.json';
        if (!file_exists($configFile)) {
            $defaultConfig = [
                'site_name' => $this->currentSite,
                'theme' => 'default',
                'features' => ['chat', 'voice', 'video'],
                'login_logo' => '/assets/images/logo.png',
                'telephony_platform_url' => 'http://localhost:3000',
                'enabled' => true
            ];
            file_put_contents($configFile, json_encode($defaultConfig, JSON_PRETTY_PRINT));
        }

        return true;
    }

    /**
     * Register new user for current site
     */
    public function registerUser($userData) {
        $this->initializeSiteData();

        $siteDataPath = dirname(dirname(dirname(__DIR__))) . '/aeims/sites/' . $this->currentSite;
        $usersFile = $siteDataPath . '/users.json';

        $users = json_decode(file_get_contents($usersFile), true) ?? [];

        // Check if username exists
        if (isset($users[$userData['username']])) {
            return ['error' => 'Username already exists'];
        }

        // Hash password
        $userData['password'] = password_hash($userData['password'], PASSWORD_DEFAULT);
        $userData['created_at'] = date('c');
        $userData['site'] = $this->currentSite;
        $userData['id'] = uniqid();

        // Set default role if not specified
        if (!isset($userData['role'])) {
            $userData['role'] = 'customer';
        }

        $users[$userData['username']] = $userData;

        if (file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT))) {
            // Reserve username across all sites if this is enabled
            if (isset($_POST['reserve_username']) && $_POST['reserve_username']) {
                reserveUsernameAcrossAllSites($userData['username'], $userData);
            }

            return ['success' => true, 'user' => $userData];
        }

        return ['error' => 'Failed to create user'];
    }
}