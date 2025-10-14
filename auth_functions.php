<?php
/**
 * Authentication and Session Management
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
 * SITE MANAGEMENT FUNCTIONS
 * Support for multi-site operations, cross-site linking, and username reservation
 */

/**
 * Get all available sites from aeims/sites/* directory
 */
function getAllAvailableSites() {
    $sitesPath = dirname(dirname(__DIR__)) . '/aeims/sites';
    $sites = [];

    if (!is_dir($sitesPath)) {
        return $sites;
    }

    $directories = scandir($sitesPath);
    foreach ($directories as $dir) {
        if ($dir === '.' || $dir === '..' || $dir === '_archived') {
            continue;
        }

        $fullPath = $sitesPath . '/' . $dir;
        if (is_dir($fullPath)) {
            $sites[] = [
                'domain' => $dir,
                'path' => $fullPath,
                'enabled' => true,
                'operator_dashboard_url' => getOperatorDashboardUrl($dir)
            ];
        }
    }

    return $sites;
}

/**
 * Get operator dashboard URL for a specific site
 */
function getOperatorDashboardUrl($siteDomain) {
    // This points to the telephony-platform frontend that all sites use
    $baseUrl = 'http://localhost:3000'; // Adjust based on your telephony-platform setup
    return $baseUrl . '/dashboard?site=' . urlencode($siteDomain);
}

/**
 * Get user's authorized sites
 */
function getUserAuthorizedSites($userId = null) {
    $user = $userId ? getUserById($userId) : getCurrentUser();
    if (!$user) {
        return [];
    }

    if ($user['type'] === 'admin') {
        // Admin can access all sites
        return getAllAvailableSites();
    }

    // Get user's specific site permissions
    $userSites = $user['authorized_sites'] ?? [];
    $allSites = getAllAvailableSites();

    $authorizedSites = [];
    foreach ($allSites as $site) {
        if (in_array($site['domain'], $userSites)) {
            $authorizedSites[] = $site;
        }
    }

    return $authorizedSites;
}

/**
 * Check if user has access to a specific site
 */
function userHasAccessToSite($siteDomain, $userId = null) {
    $user = $userId ? getUserById($userId) : getCurrentUser();
    if (!$user) {
        return false;
    }

    if ($user['type'] === 'admin') {
        return true;
    }

    $authorizedSites = $user['authorized_sites'] ?? [];
    return in_array($siteDomain, $authorizedSites);
}

/**
 * Add site access for user
 */
function addSiteAccessForUser($username, $siteDomain) {
    $accountsFile = __DIR__ . '/data/accounts.json';
    if (!file_exists($accountsFile)) {
        return false;
    }

    $accounts = json_decode(file_get_contents($accountsFile), true) ?? [];

    if (!isset($accounts[$username])) {
        return false;
    }

    if (!isset($accounts[$username]['authorized_sites'])) {
        $accounts[$username]['authorized_sites'] = [];
    }

    if (!in_array($siteDomain, $accounts[$username]['authorized_sites'])) {
        $accounts[$username]['authorized_sites'][] = $siteDomain;
        return file_put_contents($accountsFile, json_encode($accounts, JSON_PRETTY_PRINT));
    }

    return true;
}

/**
 * Remove site access for user
 */
function removeSiteAccessForUser($username, $siteDomain) {
    $accountsFile = __DIR__ . '/data/accounts.json';
    if (!file_exists($accountsFile)) {
        return false;
    }

    $accounts = json_decode(file_get_contents($accountsFile), true) ?? [];

    if (!isset($accounts[$username])) {
        return false;
    }

    if (isset($accounts[$username]['authorized_sites'])) {
        $accounts[$username]['authorized_sites'] = array_diff(
            $accounts[$username]['authorized_sites'],
            [$siteDomain]
        );
        $accounts[$username]['authorized_sites'] = array_values($accounts[$username]['authorized_sites']);
        return file_put_contents($accountsFile, json_encode($accounts, JSON_PRETTY_PRINT));
    }

    return true;
}

/**
 * CROSS-SITE ACCOUNT LINKING FUNCTIONS
 */

/**
 * Enable cross-site login for a user account
 */
function enableCrossSiteLogin($username) {
    $accountsFile = __DIR__ . '/data/accounts.json';
    if (!file_exists($accountsFile)) {
        return false;
    }

    $accounts = json_decode(file_get_contents($accountsFile), true) ?? [];

    if (!isset($accounts[$username])) {
        return false;
    }

    $accounts[$username]['cross_site_enabled'] = true;
    $accounts[$username]['cross_site_enabled_at'] = date('c');

    return file_put_contents($accountsFile, json_encode($accounts, JSON_PRETTY_PRINT));
}

/**
 * Disable cross-site login for a user account
 */
function disableCrossSiteLogin($username) {
    $accountsFile = __DIR__ . '/data/accounts.json';
    if (!file_exists($accountsFile)) {
        return false;
    }

    $accounts = json_decode(file_get_contents($accountsFile), true) ?? [];

    if (!isset($accounts[$username])) {
        return false;
    }

    $accounts[$username]['cross_site_enabled'] = false;
    $accounts[$username]['cross_site_disabled_at'] = date('c');

    return file_put_contents($accountsFile, json_encode($accounts, JSON_PRETTY_PRINT));
}

/**
 * Check if user has cross-site login enabled
 */
function userHasCrossSiteEnabled($username) {
    $accountsFile = __DIR__ . '/data/accounts.json';
    if (!file_exists($accountsFile)) {
        return false;
    }

    $accounts = json_decode(file_get_contents($accountsFile), true) ?? [];

    if (!isset($accounts[$username])) {
        return false;
    }

    return $accounts[$username]['cross_site_enabled'] ?? false;
}

/**
 * AUTOMATIC USERNAME RESERVATION FUNCTIONS
 */

/**
 * Reserve username across all sites in the network
 */
function reserveUsernameAcrossAllSites($username, $userInfo) {
    $reservationFile = __DIR__ . '/data/username_reservations.json';
    $reservations = [];

    if (file_exists($reservationFile)) {
        $reservations = json_decode(file_get_contents($reservationFile), true) ?? [];
    }

    // Initialize user reservation record if it doesn't exist
    if (!isset($reservations[$username])) {
        $reservations[$username] = [
            'original_site' => $userInfo['original_site'] ?? 'aeims.app',
            'reserved_at' => date('c'),
            'reserved_sites' => [],
            'user_id' => $userInfo['id'] ?? null,
            'email' => $userInfo['email'] ?? null
        ];
    }

    // Get all available sites and reserve username on each
    $allSites = getAllAvailableSites();
    foreach ($allSites as $site) {
        if (!in_array($site['domain'], $reservations[$username]['reserved_sites'])) {
            $reservations[$username]['reserved_sites'][] = $site['domain'];
        }
    }

    // Also reserve on sites from config.php
    $config = include __DIR__ . '/config.php';
    if (isset($config['powered_sites'])) {
        foreach ($config['powered_sites'] as $configSite) {
            if (!in_array($configSite['domain'], $reservations[$username]['reserved_sites'])) {
                $reservations[$username]['reserved_sites'][] = $configSite['domain'];
            }
        }
    }

    return file_put_contents($reservationFile, json_encode($reservations, JSON_PRETTY_PRINT));
}

/**
 * Check if username is reserved across sites
 */
function isUsernameReserved($username) {
    $reservationFile = __DIR__ . '/data/username_reservations.json';
    if (!file_exists($reservationFile)) {
        return false;
    }

    $reservations = json_decode(file_get_contents($reservationFile), true) ?? [];
    return isset($reservations[$username]);
}

/**
 * Get username reservation details
 */
function getUsernameReservationDetails($username) {
    $reservationFile = __DIR__ . '/data/username_reservations.json';
    if (!file_exists($reservationFile)) {
        return null;
    }

    $reservations = json_decode(file_get_contents($reservationFile), true) ?? [];
    return $reservations[$username] ?? null;
}

/**
 * Release username reservation (admin only)
 */
function releaseUsernameReservation($username) {
    if (!isAdmin()) {
        return false;
    }

    $reservationFile = __DIR__ . '/data/username_reservations.json';
    if (!file_exists($reservationFile)) {
        return true;
    }

    $reservations = json_decode(file_get_contents($reservationFile), true) ?? [];

    if (isset($reservations[$username])) {
        unset($reservations[$username]);
        return file_put_contents($reservationFile, json_encode($reservations, JSON_PRETTY_PRINT));
    }

    return true;
}

/**
 * SITE STATISTICS AND ANALYTICS
 */

/**
 * Get aggregated statistics for user's authorized sites
 */
function getUserSiteStatistics($userId = null, $selectedSite = null) {
    $user = $userId ? getUserById($userId) : getCurrentUser();
    if (!$user) {
        return [];
    }

    $authorizedSites = getUserAuthorizedSites($userId);
    $domainsData = getAllDomains();

    $stats = [
        'total_sites' => 0,
        'total_revenue' => 0,
        'total_calls' => 0,
        'total_messages' => 0,
        'total_operators' => 0,
        'total_active_users' => 0,
        'average_uptime' => 0,
        'sites' => []
    ];

    $uptimeSum = 0;
    $siteCount = 0;

    foreach ($authorizedSites as $site) {
        $domain = $site['domain'];

        // If specific site selected, only include that site
        if ($selectedSite && $selectedSite !== 'ALL' && $selectedSite !== $domain) {
            continue;
        }

        if (isset($domainsData[$domain])) {
            $siteData = $domainsData[$domain];

            $stats['total_sites']++;
            $stats['total_revenue'] += $siteData['monthly_revenue'] ?? 0;
            $stats['total_calls'] += $siteData['monthly_calls'] ?? 0;
            $stats['total_messages'] += $siteData['monthly_messages'] ?? 0;
            $stats['total_operators'] += $siteData['operators_count'] ?? 0;
            $stats['total_active_users'] += $siteData['active_users'] ?? 0;

            $uptime = $siteData['uptime_percentage'] ?? 99.9;
            $uptimeSum += $uptime;
            $siteCount++;

            $stats['sites'][$domain] = [
                'domain' => $domain,
                'theme' => $siteData['theme'] ?? $domain,
                'revenue' => $siteData['monthly_revenue'] ?? 0,
                'calls' => $siteData['monthly_calls'] ?? 0,
                'messages' => $siteData['monthly_messages'] ?? 0,
                'operators' => $siteData['operators_count'] ?? 0,
                'active_users' => $siteData['active_users'] ?? 0,
                'uptime' => $uptime,
                'status' => $siteData['status'] ?? 'unknown',
                'operator_dashboard_url' => getOperatorDashboardUrl($domain)
            ];
        }
    }

    if ($siteCount > 0) {
        $stats['average_uptime'] = $uptimeSum / $siteCount;
    }

    return $stats;
}

/**
 * Get site selector options for dashboard
 */
function getSiteSelectorOptions($userId = null) {
    $authorizedSites = getUserAuthorizedSites($userId);
    $options = [
        'ALL' => [
            'label' => 'All Sites',
            'description' => 'View aggregated statistics from all authorized sites'
        ]
    ];

    foreach ($authorizedSites as $site) {
        $options[$site['domain']] = [
            'label' => $site['domain'],
            'description' => 'View statistics for ' . $site['domain'],
            'operator_dashboard_url' => $site['operator_dashboard_url']
        ];
    }

    return $options;
}

/**
 * CENTRALIZED BILLING INTEGRATION
 */

/**
 * Check if user has centralized billing enabled
 */
function userHasCentralizedBilling($userId = null) {
    $user = $userId ? getUserById($userId) : getCurrentUser();
    if (!$user) {
        return false;
    }

    return $user['centralized_billing'] ?? false;
}

/**
 * Enable centralized billing for user
 */
function enableCentralizedBilling($username) {
    $accountsFile = __DIR__ . '/data/accounts.json';
    if (!file_exists($accountsFile)) {
        return false;
    }

    $accounts = json_decode(file_get_contents($accountsFile), true) ?? [];

    if (!isset($accounts[$username])) {
        return false;
    }

    $accounts[$username]['centralized_billing'] = true;
    $accounts[$username]['centralized_billing_enabled_at'] = date('c');

    return file_put_contents($accountsFile, json_encode($accounts, JSON_PRETTY_PRINT));
}

/**
 * UTILITY FUNCTIONS FOR SITE MANAGEMENT
 */

/**
 * Validate site domain format
 */
function isValidSiteDomain($domain) {
    return filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;
}

/**
 * Get site configuration from various sources
 */
function getSiteConfiguration($siteDomain) {
    // First check domains.json
    $domainsData = getAllDomains();
    if (isset($domainsData[$siteDomain])) {
        return $domainsData[$siteDomain];
    }

    // Then check config.php powered_sites
    $config = include __DIR__ . '/config.php';
    if (isset($config['powered_sites'])) {
        foreach ($config['powered_sites'] as $site) {
            if ($site['domain'] === $siteDomain) {
                return $site;
            }
        }
    }

    return null;
}

/**
 * Initialize user account with site reservations
 */
function initializeUserAccountWithSiteReservations($username, $userInfo) {
    // Reserve username across all sites
    reserveUsernameAcrossAllSites($username, $userInfo);

    // Enable centralized billing by default
    enableCentralizedBilling($username);

    // Cross-site login is disabled by default (user must enable)
    disableCrossSiteLogin($username);

    return true;
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

/**
 * CREATE REQUIRED DATA DIRECTORIES AND FILES
 */
function initializeSiteManagementData() {
    $dataDir = __DIR__ . '/data';

    // Ensure data directory exists
    if (!is_dir($dataDir)) {
        mkdir($dataDir, 0755, true);
    }

    // Initialize username_reservations.json if it doesn't exist
    $reservationFile = $dataDir . '/username_reservations.json';
    if (!file_exists($reservationFile)) {
        file_put_contents($reservationFile, json_encode([], JSON_PRETTY_PRINT));
    }

    // Initialize accounts.json if it doesn't exist
    $accountsFile = $dataDir . '/accounts.json';
    if (!file_exists($accountsFile)) {
        file_put_contents($accountsFile, json_encode([], JSON_PRETTY_PRINT));
    }
}

// Initialize data files
initializeSiteManagementData();
?>