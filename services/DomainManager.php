<?php

namespace AEIMS\Services;

use Exception;

/**
 * Domain Manager Service
 * Handles domain configuration management and persistence
 */
class DomainManager
{
    private string $domainsConfigPath;
    private string $multisiteConfigPath;
    private array $domainsCache = [];

    public function __construct()
    {
        $this->domainsConfigPath = __DIR__ . '/../config/domains.json';
        $this->multisiteConfigPath = __DIR__ . '/../telephony-platform/config/multisite.json';
    }

    /**
     * Get all domains
     */
    public function getAllDomains(): array
    {
        $domainsConfig = $this->loadDomainsConfig();
        $multisiteConfig = $this->loadMultisiteConfig();
        
        $domains = [];
        
        if (isset($domainsConfig['aiems_domains'])) {
            foreach ($domainsConfig['aiems_domains'] as $domainId => $config) {
                $domain = $config['domain'];
                $multisiteData = $multisiteConfig['sites'][$domain] ?? [];
                
                $domains[] = array_merge($config, [
                    'domain_id' => $domainId,
                    'multisite_config' => $multisiteData,
                    'ssl_enabled' => true, // Default to SSL enabled
                    'nginx_enabled' => $this->isNginxSiteEnabled($domain)
                ]);
            }
        }

        return $domains;
    }

    /**
     * Get specific domain configuration
     */
    public function getDomain(string $domain): ?array
    {
        $domains = $this->getAllDomains();
        
        foreach ($domains as $domainConfig) {
            if ($domainConfig['domain'] === $domain) {
                return $domainConfig;
            }
        }

        return null;
    }

    /**
     * Check if domain exists
     */
    public function domainExists(string $domain): bool
    {
        return $this->getDomain($domain) !== null;
    }

    /**
     * Add new domain
     */
    public function addDomain(array $domainData): array
    {
        $domainsConfig = $this->loadDomainsConfig();
        $multisiteConfig = $this->loadMultisiteConfig();

        $domainId = $domainData['domain_id'];
        $domain = $domainData['domain'];

        // Add to domains.json
        if (!isset($domainsConfig['aiems_domains'])) {
            $domainsConfig['aiems_domains'] = [];
        }

        $domainsConfig['aiems_domains'][$domainId] = [
            'domain' => $domain,
            'active' => $domainData['active'] ?? true
        ];

        // Add to multisite configuration if provided
        if (isset($domainData['multisite_config']) || 
            isset($domainData['features']) || 
            isset($domainData['theme']) || 
            isset($domainData['billing'])) {
            
            $siteId = str_replace('domain_', '', $domainId);
            
            $multisiteConfig['sites'][$domain] = [
                'site_id' => $siteId,
                'site_name' => $domainData['site_name'] ?? ucfirst($siteId),
                'site_description' => $domainData['site_description'] ?? "Adult entertainment platform - {$domain}",
                'active' => $domainData['active'] ?? true,
                'created_at' => date('Y-m-d'),
                'theme' => $domainData['theme'] ?? $this->getDefaultTheme($domain),
                'features' => $domainData['features'] ?? $this->getDefaultFeatures(),
                'operators' => $domainData['operators'] ?? $this->getDefaultOperators(),
                'billing' => $domainData['billing'] ?? $this->getDefaultBilling(),
                'compliance' => $domainData['compliance'] ?? $this->getDefaultCompliance($domain),
                'integrations' => $domainData['integrations'] ?? $this->getDefaultIntegrations($siteId),
                'security' => $domainData['security'] ?? $this->getDefaultSecurity(),
                'seo' => $domainData['seo'] ?? $this->getDefaultSeo($domain)
            ];

            // Add development mappings
            if (isset($multisiteConfig['development']['local_development']['domain_mapping'])) {
                $multisiteConfig['development']['local_development']['domain_mapping']["{$siteId}.local"] = $domain;
            }

            if (isset($multisiteConfig['development']['staging']['subdomain_mapping'])) {
                $multisiteConfig['development']['staging']['subdomain_mapping']["{$siteId}-staging.aeims.dev"] = $domain;
            }
        }

        // Save configurations
        $this->saveDomainsConfig($domainsConfig);
        $this->saveMultisiteConfig($multisiteConfig);

        return $this->getDomain($domain);
    }

    /**
     * Update domain configuration
     */
    public function updateDomain(string $domain, array $updateData): array
    {
        $domainsConfig = $this->loadDomainsConfig();
        $multisiteConfig = $this->loadMultisiteConfig();

        // Find and update in domains.json
        foreach ($domainsConfig['aiems_domains'] as $domainId => &$config) {
            if ($config['domain'] === $domain) {
                if (isset($updateData['active'])) {
                    $config['active'] = $updateData['active'];
                }
                break;
            }
        }

        // Update multisite configuration
        if (isset($multisiteConfig['sites'][$domain])) {
            $siteConfig = &$multisiteConfig['sites'][$domain];
            
            foreach (['active', 'site_name', 'site_description', 'theme', 'features', 'operators', 'billing', 'compliance', 'integrations', 'security', 'seo'] as $key) {
                if (isset($updateData[$key])) {
                    if (is_array($updateData[$key]) && is_array($siteConfig[$key])) {
                        $siteConfig[$key] = array_merge($siteConfig[$key], $updateData[$key]);
                    } else {
                        $siteConfig[$key] = $updateData[$key];
                    }
                }
            }
        }

        // Save configurations
        $this->saveDomainsConfig($domainsConfig);
        $this->saveMultisiteConfig($multisiteConfig);

        return $this->getDomain($domain);
    }

    /**
     * Remove domain
     */
    public function removeDomain(string $domain): bool
    {
        $domainsConfig = $this->loadDomainsConfig();
        $multisiteConfig = $this->loadMultisiteConfig();

        // Remove from domains.json
        foreach ($domainsConfig['aiems_domains'] as $domainId => $config) {
            if ($config['domain'] === $domain) {
                unset($domainsConfig['aiems_domains'][$domainId]);
                break;
            }
        }

        // Remove from multisite configuration
        if (isset($multisiteConfig['sites'][$domain])) {
            $siteId = $multisiteConfig['sites'][$domain]['site_id'];
            unset($multisiteConfig['sites'][$domain]);

            // Remove development mappings
            if (isset($multisiteConfig['development']['local_development']['domain_mapping']["{$siteId}.local"])) {
                unset($multisiteConfig['development']['local_development']['domain_mapping']["{$siteId}.local"]);
            }

            if (isset($multisiteConfig['development']['staging']['subdomain_mapping']["{$siteId}-staging.aeims.dev"])) {
                unset($multisiteConfig['development']['staging']['subdomain_mapping']["{$siteId}-staging.aeims.dev"]);
            }
        }

        // Save configurations
        $this->saveDomainsConfig($domainsConfig);
        $this->saveMultisiteConfig($multisiteConfig);

        return true;
    }

    /**
     * Validate domain format
     */
    public function validateDomainFormat(string $domain): bool
    {
        return filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;
    }

    /**
     * Check if nginx site is enabled
     */
    private function isNginxSiteEnabled(string $domain): bool
    {
        $sitesEnabledPath = $_ENV['NGINX_SITES_ENABLED'] ?? '/etc/nginx/sites-enabled';
        $enabledFile = "{$sitesEnabledPath}/{$domain}";
        
        return file_exists($enabledFile) || is_link($enabledFile);
    }

    /**
     * Load domains configuration
     */
    private function loadDomainsConfig(): array
    {
        if (!file_exists($this->domainsConfigPath)) {
            return ['aiems_domains' => []];
        }

        $content = file_get_contents($this->domainsConfigPath);
        return json_decode($content, true) ?: ['aiems_domains' => []];
    }

    /**
     * Save domains configuration
     */
    private function saveDomainsConfig(array $config): bool
    {
        $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        return file_put_contents($this->domainsConfigPath, $json) !== false;
    }

    /**
     * Load multisite configuration
     */
    private function loadMultisiteConfig(): array
    {
        if (!file_exists($this->multisiteConfigPath)) {
            return ['sites' => [], 'development' => ['local_development' => ['domain_mapping' => []], 'staging' => ['subdomain_mapping' => []]]];
        }

        $content = file_get_contents($this->multisiteConfigPath);
        return json_decode($content, true) ?: ['sites' => []];
    }

    /**
     * Save multisite configuration
     */
    private function saveMultisiteConfig(array $config): bool
    {
        $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        return file_put_contents($this->multisiteConfigPath, $json) !== false;
    }

    /**
     * Get default theme configuration
     */
    private function getDefaultTheme(string $domain): array
    {
        return [
            'primary_color' => '#e74c3c',
            'secondary_color' => '#c0392b', 
            'accent_color' => '#f39c12',
            'background_color' => '#1c1c1c',
            'text_color' => '#ffffff',
            'logo_url' => "/assets/" . str_replace(['www.', '.'], ['', '/'], $domain) . "/logo.png",
            'logo_dark_url' => "/assets/" . str_replace(['www.', '.'], ['', '/'], $domain) . "/logo-dark.png",
            'favicon_url' => "/assets/" . str_replace(['www.', '.'], ['', '/'], $domain) . "/favicon.ico",
            'custom_css' => "/assets/" . str_replace(['www.', '.'], ['', '/'], $domain) . "/theme.css",
            'font_family' => 'Inter, sans-serif',
            'layout_style' => 'modern'
        ];
    }

    /**
     * Get default features configuration
     */
    private function getDefaultFeatures(): array
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
            'pay_per_view' => true
        ];
    }

    /**
     * Get default operators configuration
     */
    private function getDefaultOperators(): array
    {
        return [
            'allowed_operators' => ['op1', 'op2', 'op3', 'op4', 'op5'],
            'auto_assign' => true,
            'require_approval' => false,
            'separate_profiles' => true,
            'max_operators' => 50,
            'operator_categories' => ['premium', 'vip', 'standard'],
            'default_category' => 'standard',
            'commission_rate' => 0.65,
            'bonus_system' => true
        ];
    }

    /**
     * Get default billing configuration
     */
    private function getDefaultBilling(): array
    {
        return [
            'currency' => 'USD',
            'payment_methods' => ['credit_card', 'crypto', 'paypal'],
            'pricing_model' => 'per_minute',
            'base_rate' => 2.99,
            'premium_rate' => 4.99,
            'vip_rate' => 7.99,
            'minimum_purchase' => 10,
            'bonus_credits' => ['50' => 5, '100' => 15, '200' => 40],
            'refund_policy' => '7_days',
            'chargeback_protection' => true
        ];
    }

    /**
     * Get default compliance configuration
     */
    private function getDefaultCompliance(string $domain): array
    {
        return [
            'age_verification' => true,
            'geo_restrictions' => ['US', 'CA', 'UK', 'AU', 'DE'],
            'geo_blocks' => ['CN', 'RU', 'NK'],
            'content_filtering' => 'strict',
            'privacy_policy' => '/' . str_replace(['www.', '.'], ['', '/'], $domain) . '/privacy',
            'terms_of_service' => '/' . str_replace(['www.', '.'], ['', '/'], $domain) . '/terms',
            'dmca_policy' => '/' . str_replace(['www.', '.'], ['', '/'], $domain) . '/dmca',
            'cookie_policy' => '/' . str_replace(['www.', '.'], ['', '/'], $domain) . '/cookies',
            'data_retention_days' => 365,
            'gdpr_compliant' => true,
            'ccpa_compliant' => true,
            'content_moderation' => 'strict'
        ];
    }

    /**
     * Get default integrations configuration
     */
    private function getDefaultIntegrations(string $siteId): array
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

    /**
     * Get default security configuration
     */
    private function getDefaultSecurity(): array
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

    /**
     * Get default SEO configuration
     */
    private function getDefaultSeo(string $domain): array
    {
        $siteName = ucwords(str_replace(['.com', '.love', '.nyc', '.live'], '', $domain));
        
        return [
            'site_title' => "{$siteName} - Adult Entertainment Platform",
            'meta_description' => "Experience premium adult entertainment on {$siteName} with verified models, video calls, and exclusive content.",
            'keywords' => [
                strtolower($siteName),
                'adult entertainment',
                'video calls',
                'cam models',
                'live chat'
            ],
            'robots_txt' => 'allow',
            'sitemap_enabled' => true,
            'schema_markup' => true
        ];
    }
}