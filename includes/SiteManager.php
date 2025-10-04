<?php
/**
 * Site Manager Class
 * Advanced site management functionality for AEIMS multi-site operations
 */

class SiteManager {
    private $aeimsPath;
    private $dataPath;
    private $configPath;

    public function __construct() {
        $this->aeimsPath = dirname(dirname(dirname(__DIR__))) . '/aeims';
        $this->dataPath = dirname(__DIR__) . '/data';
        $this->configPath = dirname(__DIR__) . '/config.php';
    }

    /**
     * SITE DISCOVERY AND MANAGEMENT
     */

    /**
     * Discover all sites from both aeims/sites/* and configuration
     */
    public function discoverAllSites() {
        $sites = [];

        // Discover from aeims/sites/* directory
        $fileSystemSites = $this->discoverSitesFromFilesystem();

        // Get sites from configuration
        $configSites = $this->getSitesFromConfig();

        // Merge and deduplicate
        $allDomains = array_unique(array_merge(
            array_column($fileSystemSites, 'domain'),
            array_column($configSites, 'domain')
        ));

        foreach ($allDomains as $domain) {
            $sites[$domain] = $this->buildSiteInfo($domain, $fileSystemSites, $configSites);
        }

        return $sites;
    }

    /**
     * Discover sites from aeims/sites/* filesystem
     */
    private function discoverSitesFromFilesystem() {
        $sitesPath = $this->aeimsPath . '/sites';
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
                    'source' => 'filesystem',
                    'has_auth' => file_exists($fullPath . '/auth.php'),
                    'has_dashboard' => file_exists($fullPath . '/dashboard.php'),
                    'has_sso' => is_dir($fullPath . '/sso'),
                    'last_modified' => filemtime($fullPath)
                ];
            }
        }

        return $sites;
    }

    /**
     * Get sites from configuration files
     */
    private function getSitesFromConfig() {
        $sites = [];

        if (file_exists($this->configPath)) {
            $config = include $this->configPath;

            if (isset($config['powered_sites'])) {
                foreach ($config['powered_sites'] as $site) {
                    $sites[] = array_merge($site, ['source' => 'config']);
                }
            }
        }

        // Also check domains.json
        $domainsFile = $this->dataPath . '/domains.json';
        if (file_exists($domainsFile)) {
            $domains = json_decode(file_get_contents($domainsFile), true) ?? [];

            foreach ($domains as $domain => $info) {
                $sites[] = array_merge($info, [
                    'domain' => $domain,
                    'source' => 'domains_json'
                ]);
            }
        }

        return $sites;
    }

    /**
     * Build comprehensive site information
     */
    private function buildSiteInfo($domain, $fileSystemSites, $configSites) {
        $info = [
            'domain' => $domain,
            'sources' => [],
            'status' => 'unknown',
            'has_filesystem' => false,
            'has_config' => false,
            'operator_dashboard_url' => $this->getOperatorDashboardUrl($domain),
            'telephony_frontend_url' => $this->getTelephonyFrontendUrl($domain)
        ];

        // Check filesystem sites
        foreach ($fileSystemSites as $site) {
            if ($site['domain'] === $domain) {
                $info['sources'][] = 'filesystem';
                $info['has_filesystem'] = true;
                $info['filesystem_path'] = $site['path'];
                $info['has_auth'] = $site['has_auth'];
                $info['has_dashboard'] = $site['has_dashboard'];
                $info['has_sso'] = $site['has_sso'];
                $info['last_modified'] = $site['last_modified'];
                $info['status'] = 'active';
                break;
            }
        }

        // Check config sites
        foreach ($configSites as $site) {
            if ($site['domain'] === $domain) {
                $info['sources'][] = $site['source'];
                $info['has_config'] = true;
                $info['theme'] = $site['theme'] ?? $domain;
                $info['description'] = $site['description'] ?? '';
                $info['services'] = $site['services'] ?? [];

                // From domains.json
                if ($site['source'] === 'domains_json') {\n                    $info['monthly_revenue'] = $site['monthly_revenue'] ?? 0;
                    $info['monthly_calls'] = $site['monthly_calls'] ?? 0;
                    $info['monthly_messages'] = $site['monthly_messages'] ?? 0;
                    $info['operators_count'] = $site['operators_count'] ?? 0;
                    $info['active_users'] = $site['active_users'] ?? 0;
                    $info['uptime_percentage'] = $site['uptime_percentage'] ?? 99.9;
                    $info['ssl_status'] = $site['ssl_status'] ?? 'unknown';
                    $info['ssl_expires'] = $site['ssl_expires'] ?? null;
                    $info['status'] = $site['status'] ?? 'unknown';
                }
                break;
            }
        }

        return $info;
    }

    /**
     * Get operator dashboard URL for telephony platform
     */
    public function getOperatorDashboardUrl($siteDomain) {
        // This should point to the telephony-platform frontend
        $baseUrl = 'http://localhost:3000'; // Adjust based on your setup
        return $baseUrl . '/dashboard?site=' . urlencode($siteDomain);
    }

    /**
     * Get telephony frontend URL
     */
    public function getTelephonyFrontendUrl($siteDomain) {
        // Points to the main telephony frontend that all sites use
        $baseUrl = 'http://localhost:3000';
        return $baseUrl . '?domain=' . urlencode($siteDomain);
    }

    /**
     * CROSS-SITE USER ACCOUNT MANAGEMENT
     */

    /**
     * Link user account across multiple sites
     */
    public function linkUserAccountAcrossSites($username, $targetSites = null) {
        if ($targetSites === null) {
            $targetSites = array_keys($this->discoverAllSites());
        }

        $linkedAccountsFile = $this->dataPath . '/linked_accounts.json';
        $linkedAccounts = [];

        if (file_exists($linkedAccountsFile)) {
            $linkedAccounts = json_decode(file_get_contents($linkedAccountsFile), true) ?? [];
        }

        if (!isset($linkedAccounts[$username])) {
            $linkedAccounts[$username] = [\n                'primary_site' => 'aeims.app',
                'linked_sites' => [],
                'created_at' => date('c'),
                'cross_site_enabled' => false
            ];
        }

        foreach ($targetSites as $site) {
            if (!in_array($site, $linkedAccounts[$username]['linked_sites'])) {
                $linkedAccounts[$username]['linked_sites'][] = $site;
            }
        }

        $linkedAccounts[$username]['updated_at'] = date('c');

        return file_put_contents($linkedAccountsFile, json_encode($linkedAccounts, JSON_PRETTY_PRINT));
    }

    /**
     * Enable cross-site login for user
     */
    public function enableCrossSiteLoginForUser($username) {
        $linkedAccountsFile = $this->dataPath . '/linked_accounts.json';
        $linkedAccounts = [];

        if (file_exists($linkedAccountsFile)) {
            $linkedAccounts = json_decode(file_get_contents($linkedAccountsFile), true) ?? [];
        }

        if (isset($linkedAccounts[$username])) {
            $linkedAccounts[$username]['cross_site_enabled'] = true;
            $linkedAccounts[$username]['cross_site_enabled_at'] = date('c');

            return file_put_contents($linkedAccountsFile, json_encode($linkedAccounts, JSON_PRETTY_PRINT));
        }

        return false;
    }

    /**
     * Get user's linked sites
     */
    public function getUserLinkedSites($username) {
        $linkedAccountsFile = $this->dataPath . '/linked_accounts.json';

        if (!file_exists($linkedAccountsFile)) {
            return [];
        }

        $linkedAccounts = json_decode(file_get_contents($linkedAccountsFile), true) ?? [];

        return $linkedAccounts[$username]['linked_sites'] ?? [];
    }

    /**
     * USERNAME RESERVATION SYSTEM
     */

    /**
     * Reserve username across entire network
     */
    public function reserveUsernameAcrossNetwork($username, $userInfo = []) {
        $reservationFile = $this->dataPath . '/username_reservations.json';
        $reservations = [];

        if (file_exists($reservationFile)) {
            $reservations = json_decode(file_get_contents($reservationFile), true) ?? [];
        }

        $allSites = $this->discoverAllSites();

        $reservations[$username] = [
            'reserved_at' => date('c'),
            'reserved_by' => $userInfo['id'] ?? 'system',
            'original_site' => $userInfo['original_site'] ?? 'aeims.app',
            'reserved_sites' => array_keys($allSites),
            'reservation_status' => 'active',
            'user_email' => $userInfo['email'] ?? null,
            'can_modify' => $userInfo['can_modify'] ?? true
        ];

        return file_put_contents($reservationFile, json_encode($reservations, JSON_PRETTY_PRINT));
    }

    /**
     * Check username availability across network
     */
    public function checkUsernameAvailability($username) {
        $reservationFile = $this->dataPath . '/username_reservations.json';

        if (!file_exists($reservationFile)) {
            return [
                'available' => true,
                'reserved' => false,
                'sites' => []
            ];
        }

        $reservations = json_decode(file_get_contents($reservationFile), true) ?? [];

        if (isset($reservations[$username])) {
            return [
                'available' => false,
                'reserved' => true,
                'reservation_details' => $reservations[$username],
                'sites' => $reservations[$username]['reserved_sites'] ?? []
            ];
        }

        return [
            'available' => true,
            'reserved' => false,
            'sites' => []
        ];
    }

    /**
     * SITE STATISTICS AND ANALYTICS
     */

    /**
     * Get comprehensive site statistics
     */
    public function getSiteStatistics($siteDomain = null, $userId = null) {
        $allSites = $this->discoverAllSites();

        if ($siteDomain && $siteDomain !== 'ALL') {
            $sites = [$siteDomain => $allSites[$siteDomain] ?? []];
        } else {
            // Filter by user permissions if provided
            if ($userId) {
                require_once dirname(__DIR__) . '/auth_functions.php';
                $authorizedSites = getUserAuthorizedSites($userId);
                $authorizedDomains = array_column($authorizedSites, 'domain');
                $sites = array_intersect_key($allSites, array_flip($authorizedDomains));
            } else {
                $sites = $allSites;
            }
        }

        $stats = [
            'total_sites' => count($sites),
            'total_revenue' => 0,
            'total_calls' => 0,
            'total_messages' => 0,
            'total_operators' => 0,
            'total_active_users' => 0,
            'average_uptime' => 0,
            'sites_with_filesystem' => 0,
            'sites_with_config' => 0,
            'sites' => []
        ];

        $uptimeSum = 0;
        $uptimeCount = 0;

        foreach ($sites as $domain => $site) {
            $stats['total_revenue'] += $site['monthly_revenue'] ?? 0;
            $stats['total_calls'] += $site['monthly_calls'] ?? 0;
            $stats['total_messages'] += $site['monthly_messages'] ?? 0;
            $stats['total_operators'] += $site['operators_count'] ?? 0;
            $stats['total_active_users'] += $site['active_users'] ?? 0;

            if ($site['has_filesystem']) {
                $stats['sites_with_filesystem']++;
            }
            if ($site['has_config']) {
                $stats['sites_with_config']++;
            }

            if (isset($site['uptime_percentage'])) {
                $uptimeSum += $site['uptime_percentage'];
                $uptimeCount++;
            }

            $stats['sites'][$domain] = [
                'domain' => $domain,
                'theme' => $site['theme'] ?? $domain,
                'status' => $site['status'] ?? 'unknown',
                'has_filesystem' => $site['has_filesystem'],
                'has_config' => $site['has_config'],
                'operator_dashboard_url' => $site['operator_dashboard_url'],
                'telephony_frontend_url' => $site['telephony_frontend_url'],
                'revenue' => $site['monthly_revenue'] ?? 0,
                'calls' => $site['monthly_calls'] ?? 0,
                'messages' => $site['monthly_messages'] ?? 0,
                'operators' => $site['operators_count'] ?? 0,
                'active_users' => $site['active_users'] ?? 0,
                'uptime' => $site['uptime_percentage'] ?? null,
                'services' => $site['services'] ?? []
            ];
        }

        if ($uptimeCount > 0) {
            $stats['average_uptime'] = $uptimeSum / $uptimeCount;
        }

        return $stats;
    }

    /**
     * UTILITY FUNCTIONS
     */

    /**
     * Initialize all data files
     */
    public function initializeDataFiles() {
        if (!is_dir($this->dataPath)) {
            mkdir($this->dataPath, 0755, true);
        }

        $files = [
            'username_reservations.json' => [],
            'linked_accounts.json' => [],
            'site_preferences.json' => [],
            'cross_site_sessions.json' => []
        ];

        foreach ($files as $filename => $defaultContent) {
            $filepath = $this->dataPath . '/' . $filename;
            if (!file_exists($filepath)) {
                file_put_contents($filepath, json_encode($defaultContent, JSON_PRETTY_PRINT));
            }
        }
    }

    /**
     * Health check for site management system
     */
    public function healthCheck() {
        $health = [
            'status' => 'healthy',
            'checks' => [],
            'sites_discovered' => 0,
            'data_files_status' => 'ok',
            'aeims_integration' => 'ok'
        ];

        // Check if aeims directory exists
        $health['checks']['aeims_directory'] = is_dir($this->aeimsPath);

        // Check if data directory exists and is writable
        $health['checks']['data_directory'] = is_dir($this->dataPath) && is_writable($this->dataPath);

        // Count discovered sites
        $allSites = $this->discoverAllSites();
        $health['sites_discovered'] = count($allSites);

        // Check data files
        $requiredFiles = [
            'username_reservations.json',
            'linked_accounts.json',
            'domains.json'
        ];

        foreach ($requiredFiles as $file) {
            $filepath = $this->dataPath . '/' . $file;
            $health['checks']['data_files'][$file] = file_exists($filepath) && is_readable($filepath);
        }

        // Overall health status
        $allChecks = array_merge(
            [$health['checks']['aeims_directory'], $health['checks']['data_directory']],
            array_values($health['checks']['data_files'] ?? [])
        );

        if (in_array(false, $allChecks)) {
            $health['status'] = 'degraded';
        }

        return $health;
    }
}
?>