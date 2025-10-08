<?php

namespace AEIMS\Services;

use Exception;
use PDO;

/**
 * Multisite Manager Service
 * Handles multi-tenant phone sex site hosting platform
 */
class MultisiteManager
{
    private string $multisiteConfigPath;
    private string $databaseConfigPath;
    private ?PDO $db = null;

    public function __construct()
    {
        $this->multisiteConfigPath = __DIR__ . '/../telephony-platform/config/multisite.json';
        $this->databaseConfigPath = __DIR__ . '/../config.php';
    }

    /**
     * Get all hosted sites
     */
    public function getAllSites(): array
    {
        $config = $this->loadMultisiteConfig();
        return $config['sites'] ?? [];
    }

    /**
     * Get specific site configuration
     */
    public function getSite(string $domain): ?array
    {
        $sites = $this->getAllSites();
        return $sites[$domain] ?? null;
    }

    /**
     * Check if site exists
     */
    public function siteExists(string $domain): bool
    {
        return $this->getSite($domain) !== null;
    }

    /**
     * Create new multisite installation
     */
    public function createSite(array $siteData): array
    {
        $config = $this->loadMultisiteConfig();
        
        $domain = $siteData['domain'];
        $siteId = $siteData['site_id'] ?? str_replace(['.', '-'], '_', $domain);
        
        // Create site configuration
        $siteConfig = [
            'site_id' => $siteId,
            'site_name' => $siteData['site_name'] ?? ucfirst($siteId),
            'site_description' => $siteData['site_description'] ?? "Adult entertainment platform - {$domain}",
            'active' => $siteData['active'] ?? true,
            'created_at' => date('Y-m-d H:i:s'),
            'domain' => $domain,
            'theme' => $this->generateSiteTheme($domain, $siteData),
            'features' => $this->getDefaultFeatures($siteData),
            'operators' => $this->getDefaultOperatorConfig($siteData),
            'billing' => $this->getDefaultBillingConfig($siteData),
            'compliance' => $this->getDefaultComplianceConfig($domain),
            'integrations' => $this->getDefaultIntegrationsConfig($siteId),
            'security' => $this->getDefaultSecurityConfig(),
            'seo' => $this->generateSeoConfig($domain, $siteData)
        ];

        // Add to multisite configuration
        $config['sites'][$domain] = $siteConfig;

        // Add development mappings
        $this->addDevelopmentMappings($config, $domain, $siteId);

        // Save configuration
        $this->saveMultisiteConfig($config);

        return $siteConfig;
    }

    /**
     * Setup database schemas for new site
     */
    public function setupDatabase(string $domain): bool
    {
        try {
            $db = $this->getDatabase();
            $siteId = str_replace(['.', '-'], '_', $domain);
            
            // Create site-specific database tables
            $tables = [
                "CREATE TABLE IF NOT EXISTS {$siteId}_users (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    username VARCHAR(255) UNIQUE NOT NULL,
                    email VARCHAR(255) UNIQUE NOT NULL,
                    password_hash VARCHAR(255) NOT NULL,
                    site_domain VARCHAR(255) NOT NULL DEFAULT '{$domain}',
                    active BOOLEAN DEFAULT TRUE,
                    total_spent DECIMAL(10,2) DEFAULT 0.00,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    last_active TIMESTAMP NULL,
                    profile_data JSON,
                    preferences JSON,
                    INDEX idx_site_domain (site_domain),
                    INDEX idx_username (username),
                    INDEX idx_email (email),
                    INDEX idx_active (active)
                )",
                
                "CREATE TABLE IF NOT EXISTS {$siteId}_operators (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(255) NOT NULL,
                    username VARCHAR(255) UNIQUE NOT NULL,
                    email VARCHAR(255) UNIQUE NOT NULL,
                    site_domain VARCHAR(255) NOT NULL DEFAULT '{$domain}',
                    category ENUM('standard', 'premium', 'vip', 'elite') DEFAULT 'standard',
                    active BOOLEAN DEFAULT TRUE,
                    online BOOLEAN DEFAULT FALSE,
                    monthly_earnings DECIMAL(10,2) DEFAULT 0.00,
                    total_earnings DECIMAL(10,2) DEFAULT 0.00,
                    commission_rate DECIMAL(4,3) DEFAULT 0.650,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    last_online TIMESTAMP NULL,
                    profile_data JSON,
                    schedule JSON,
                    INDEX idx_site_domain (site_domain),
                    INDEX idx_category (category),
                    INDEX idx_active (active),
                    INDEX idx_online (online)
                )",

                "CREATE TABLE IF NOT EXISTS {$siteId}_sessions (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    session_id VARCHAR(255) UNIQUE NOT NULL,
                    user_id INT NOT NULL,
                    operator_id INT NOT NULL,
                    site_domain VARCHAR(255) NOT NULL DEFAULT '{$domain}',
                    session_type ENUM('chat', 'voice', 'video', 'cam2cam') NOT NULL,
                    status ENUM('pending', 'active', 'ended', 'cancelled') DEFAULT 'pending',
                    duration_seconds INT DEFAULT 0,
                    cost_per_minute DECIMAL(6,2) NOT NULL,
                    total_cost DECIMAL(10,2) DEFAULT 0.00,
                    operator_earnings DECIMAL(10,2) DEFAULT 0.00,
                    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    ended_at TIMESTAMP NULL,
                    metadata JSON,
                    INDEX idx_site_domain (site_domain),
                    INDEX idx_user_id (user_id),
                    INDEX idx_operator_id (operator_id),
                    INDEX idx_status (status),
                    INDEX idx_session_type (session_type),
                    FOREIGN KEY (user_id) REFERENCES {$siteId}_users(id) ON DELETE CASCADE,
                    FOREIGN KEY (operator_id) REFERENCES {$siteId}_operators(id) ON DELETE CASCADE
                )",

                "CREATE TABLE IF NOT EXISTS {$siteId}_payments (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    session_id INT NULL,
                    site_domain VARCHAR(255) NOT NULL DEFAULT '{$domain}',
                    payment_type ENUM('session', 'credits', 'subscription', 'tip') NOT NULL,
                    amount DECIMAL(10,2) NOT NULL,
                    currency VARCHAR(3) DEFAULT 'USD',
                    status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
                    payment_method VARCHAR(50),
                    transaction_id VARCHAR(255),
                    processor_response JSON,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    processed_at TIMESTAMP NULL,
                    INDEX idx_site_domain (site_domain),
                    INDEX idx_user_id (user_id),
                    INDEX idx_session_id (session_id),
                    INDEX idx_status (status),
                    INDEX idx_payment_type (payment_type),
                    FOREIGN KEY (user_id) REFERENCES {$siteId}_users(id) ON DELETE CASCADE,
                    FOREIGN KEY (session_id) REFERENCES {$siteId}_sessions(id) ON DELETE SET NULL
                )",

                "CREATE TABLE IF NOT EXISTS {$siteId}_site_stats (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    site_domain VARCHAR(255) NOT NULL DEFAULT '{$domain}',
                    date DATE NOT NULL,
                    active_users INT DEFAULT 0,
                    active_operators INT DEFAULT 0,
                    total_sessions INT DEFAULT 0,
                    total_revenue DECIMAL(10,2) DEFAULT 0.00,
                    average_session_duration INT DEFAULT 0,
                    stats_data JSON,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_site_date (site_domain, date),
                    INDEX idx_site_domain (site_domain),
                    INDEX idx_date (date)
                )"
            ];

            foreach ($tables as $sql) {
                $db->exec($sql);
            }

            // Insert initial site stats record
            $this->initializeSiteStats($domain);
            
            return true;
        } catch (Exception $e) {
            error_log("Failed to setup database for site {$domain}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get user count for specific site
     */
    public function getUserCount(string $domain): int
    {
        try {
            $db = $this->getDatabase();
            $siteId = str_replace(['.', '-'], '_', $domain);
            
            $stmt = $db->prepare("SELECT COUNT(*) FROM {$siteId}_users WHERE active = 1");
            $stmt->execute();
            
            return (int) $stmt->fetchColumn();
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Get operator count for specific site
     */
    public function getOperatorCount(string $domain): int
    {
        try {
            $db = $this->getDatabase();
            $siteId = str_replace(['.', '-'], '_', $domain);
            
            $stmt = $db->prepare("SELECT COUNT(*) FROM {$siteId}_operators WHERE active = 1");
            $stmt->execute();
            
            return (int) $stmt->fetchColumn();
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Get monthly revenue for specific site
     */
    public function getMonthlyRevenue(string $domain): float
    {
        try {
            $db = $this->getDatabase();
            $siteId = str_replace(['.', '-'], '_', $domain);
            
            $stmt = $db->prepare("
                SELECT SUM(amount) 
                FROM {$siteId}_payments 
                WHERE status = 'completed' 
                AND YEAR(created_at) = YEAR(CURDATE())
                AND MONTH(created_at) = MONTH(CURDATE())
            ");
            $stmt->execute();
            
            return (float) ($stmt->fetchColumn() ?? 0.00);
        } catch (Exception $e) {
            return 0.00;
        }
    }

    /**
     * Get total revenue for specific site
     */
    public function getTotalRevenue(string $domain): float
    {
        try {
            $db = $this->getDatabase();
            $siteId = str_replace(['.', '-'], '_', $domain);
            
            $stmt = $db->prepare("
                SELECT SUM(amount) 
                FROM {$siteId}_payments 
                WHERE status = 'completed'
            ");
            $stmt->execute();
            
            return (float) ($stmt->fetchColumn() ?? 0.00);
        } catch (Exception $e) {
            return 0.00;
        }
    }

    /**
     * Get users for specific site
     */
    public function getSiteUsers(string $domain, int $limit = 50): array
    {
        try {
            $db = $this->getDatabase();
            $siteId = str_replace(['.', '-'], '_', $domain);
            
            $stmt = $db->prepare("
                SELECT id, username, email, active, total_spent, created_at, last_active
                FROM {$siteId}_users 
                ORDER BY total_spent DESC, created_at DESC 
                LIMIT :limit
            ");
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Get operators for specific site
     */
    public function getSiteOperators(string $domain, int $limit = 50): array
    {
        try {
            $db = $this->getDatabase();
            $siteId = str_replace(['.', '-'], '_', $domain);
            
            $stmt = $db->prepare("
                SELECT id, name, username, email, category, active, online, 
                       monthly_earnings, total_earnings, created_at, last_online
                FROM {$siteId}_operators 
                ORDER BY monthly_earnings DESC, created_at DESC 
                LIMIT :limit
            ");
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Get platform-wide statistics
     */
    public function getPlatformStats(): array
    {
        $sites = $this->getAllSites();
        $stats = [
            'total_sites' => count($sites),
            'active_sites' => 0,
            'total_users' => 0,
            'total_operators' => 0,
            'monthly_revenue' => 0.00,
            'total_revenue' => 0.00,
            'top_sites' => []
        ];

        $siteRevenues = [];

        foreach ($sites as $domain => $site) {
            if ($site['active']) {
                $stats['active_sites']++;
            }

            $users = $this->getUserCount($domain);
            $operators = $this->getOperatorCount($domain);
            $monthlyRevenue = $this->getMonthlyRevenue($domain);
            $totalRevenue = $this->getTotalRevenue($domain);

            $stats['total_users'] += $users;
            $stats['total_operators'] += $operators;
            $stats['monthly_revenue'] += $monthlyRevenue;
            $stats['total_revenue'] += $totalRevenue;

            $siteRevenues[] = [
                'domain' => $domain,
                'monthly_revenue' => $monthlyRevenue
            ];
        }

        // Sort by revenue and get top sites
        usort($siteRevenues, function($a, $b) {
            return $b['monthly_revenue'] <=> $a['monthly_revenue'];
        });

        $stats['top_sites'] = array_slice($siteRevenues, 0, 5);

        return $stats;
    }

    /**
     * Initialize site statistics
     */
    private function initializeSiteStats(string $domain): bool
    {
        try {
            $db = $this->getDatabase();
            $siteId = str_replace(['.', '-'], '_', $domain);
            
            $stmt = $db->prepare("
                INSERT IGNORE INTO {$siteId}_site_stats 
                (site_domain, date, active_users, active_operators, total_sessions, total_revenue)
                VALUES (:domain, CURDATE(), 0, 0, 0, 0.00)
            ");
            $stmt->bindValue(':domain', $domain);
            $stmt->execute();
            
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Generate site theme configuration
     */
    private function generateSiteTheme(string $domain, array $siteData): array
    {
        // Domain-specific color schemes
        $colorSchemes = [
            'sexacomms.com' => ['#e74c3c', '#c0392b', '#f39c12'],
            'flirts.nyc' => ['#9b59b6', '#8e44ad', '#e91e63'],
            'nycflirts.com' => ['#3498db', '#2980b9', '#f39c12'],
            'latenite.love' => ['#2c3e50', '#34495e', '#e74c3c'],
            'default' => ['#e74c3c', '#c0392b', '#f39c12']
        ];

        $colors = $colorSchemes[$domain] ?? $colorSchemes['default'];

        return [
            'primary_color' => $colors[0],
            'secondary_color' => $colors[1],
            'accent_color' => $colors[2],
            'background_color' => '#1c1c1c',
            'text_color' => '#ffffff',
            'logo_url' => "/assets/" . str_replace(['.', '-'], ['/', '_'], $domain) . "/logo.png",
            'logo_dark_url' => "/assets/" . str_replace(['.', '-'], ['/', '_'], $domain) . "/logo-dark.png",
            'favicon_url' => "/assets/" . str_replace(['.', '-'], ['/', '_'], $domain) . "/favicon.ico",
            'custom_css' => "/assets/" . str_replace(['.', '-'], ['/', '_'], $domain) . "/theme.css",
            'font_family' => 'Inter, sans-serif',
            'layout_style' => $siteData['template'] ?? 'modern'
        ];
    }

    /**
     * Get default features configuration
     */
    private function getDefaultFeatures(array $siteData = []): array
    {
        return [
            'video_calls' => true,
            'phone_calls' => true,
            'chat' => true,
            'cam2cam' => true,
            'group_shows' => true,
            'private_shows' => true,
            'gifts' => true,
            'tipping' => true,
            'recordings' => true,
            'favorites' => true,
            'blocking' => true,
            'reporting' => true,
            'premium_content' => true,
            'subscription_model' => true,
            'pay_per_view' => true,
            'mobile_app' => true,
            'social_features' => true
        ];
    }

    // Database connection helper
    private function getDatabase(): PDO
    {
        if ($this->db === null) {
            // Load database configuration
            if (file_exists($this->databaseConfigPath)) {
                $config = require $this->databaseConfigPath;
                $dbConfig = $config['database'] ?? [];
            } else {
                $dbConfig = [
                    'host' => $_ENV['DB_HOST'] ?? 'localhost',
                    'name' => $_ENV['DB_NAME'] ?? 'aeims',
                    'user' => $_ENV['DB_USER'] ?? 'root',
                    'password' => $_ENV['DB_PASSWORD'] ?? ''
                ];
            }

            $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['name']};charset=utf8mb4";
            $this->db = new PDO($dsn, $dbConfig['user'], $dbConfig['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
        }

        return $this->db;
    }

    // Configuration file helpers
    private function loadMultisiteConfig(): array
    {
        if (!file_exists($this->multisiteConfigPath)) {
            return ['sites' => []];
        }

        $content = file_get_contents($this->multisiteConfigPath);
        return json_decode($content, true) ?: ['sites' => []];
    }

    private function saveMultisiteConfig(array $config): bool
    {
        $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        return file_put_contents($this->multisiteConfigPath, $json) !== false;
    }

    private function addDevelopmentMappings(array &$config, string $domain, string $siteId): void
    {
        // Add development mappings if they don't exist
        if (!isset($config['development'])) {
            $config['development'] = [
                'local_development' => ['domain_mapping' => []],
                'staging' => ['subdomain_mapping' => []]
            ];
        }

        $config['development']['local_development']['domain_mapping']["{$siteId}.local"] = $domain;
        $config['development']['staging']['subdomain_mapping']["{$siteId}-staging.aeims.dev"] = $domain;
    }

    // Additional helper methods for default configurations
    private function getDefaultOperatorConfig(array $siteData = []): array
    {
        return [
            'allowed_operators' => ['all'],
            'auto_assign' => true,
            'require_approval' => false,
            'separate_profiles' => true,
            'max_operators' => 100,
            'operator_categories' => ['standard', 'premium', 'vip', 'elite'],
            'default_category' => 'standard',
            'commission_rate' => 0.65,
            'bonus_system' => true
        ];
    }

    private function getDefaultBillingConfig(array $siteData = []): array
    {
        return [
            'currency' => 'USD',
            'payment_methods' => ['credit_card', 'crypto', 'paypal'],
            'pricing_model' => 'per_minute',
            'base_rate' => 2.99,
            'premium_rate' => 4.99,
            'vip_rate' => 7.99,
            'elite_rate' => 9.99,
            'minimum_purchase' => 10,
            'bonus_credits' => ['50' => 5, '100' => 15, '200' => 40],
            'refund_policy' => '7_days',
            'chargeback_protection' => true
        ];
    }

    private function getDefaultComplianceConfig(string $domain): array
    {
        return [
            'age_verification' => true,
            'geo_restrictions' => ['US', 'CA', 'UK', 'AU', 'DE'],
            'geo_blocks' => ['CN', 'RU', 'NK'],
            'content_filtering' => 'strict',
            'privacy_policy' => '/' . str_replace(['.', '-'], ['/', '_'], $domain) . '/privacy',
            'terms_of_service' => '/' . str_replace(['.', '-'], ['/', '_'], $domain) . '/terms',
            'dmca_policy' => '/' . str_replace(['.', '-'], ['/', '_'], $domain) . '/dmca',
            'cookie_policy' => '/' . str_replace(['.', '-'], ['/', '_'], $domain) . '/cookies',
            'data_retention_days' => 365,
            'gdpr_compliant' => true,
            'ccpa_compliant' => true,
            'content_moderation' => 'strict'
        ];
    }

    private function getDefaultIntegrationsConfig(string $siteId): array
    {
        return [
            'payment_processor' => "stripe_{$siteId}",
            'backup_processor' => "authorize_net_{$siteId}",
            'analytics' => "google_analytics_{$siteId}",
            'chat_provider' => 'custom',
            'video_provider' => 'agora',
            'cdn' => "cloudflare_{$siteId}",
            'email_service' => "sendgrid_{$siteId}",
            'sms_provider' => "twilio_{$siteId}",
            'captcha_service' => "recaptcha_{$siteId}",
            'fraud_detection' => 'kount'
        ];
    }

    private function getDefaultSecurityConfig(): array
    {
        return [
            'ssl_required' => true,
            'two_factor_auth' => false,
            'password_policy' => 'strong',
            'session_timeout' => 90,
            'max_login_attempts' => 5,
            'ip_whitelist' => [],
            'rate_limiting' => true,
            'captcha_on_signup' => true,
            'email_verification' => true,
            'phone_verification' => false
        ];
    }

    private function generateSeoConfig(string $domain, array $siteData): array
    {
        $siteName = $siteData['site_name'] ?? ucwords(str_replace(['.com', '.love', '.nyc'], '', $domain));
        
        return [
            'site_title' => "{$siteName} - Premium Adult Entertainment",
            'meta_description' => "Experience premium adult entertainment on {$siteName} with verified models, video calls, and exclusive content.",
            'keywords' => [
                strtolower($siteName),
                'adult entertainment',
                'video calls',
                'phone sex',
                'cam models',
                'live chat'
            ],
            'robots_txt' => 'allow',
            'sitemap_enabled' => true,
            'schema_markup' => true
        ];
    }
}