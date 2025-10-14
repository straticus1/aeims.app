<?php

namespace AEIMS\Services;

use Exception;

/**
 * Site Management Service
 * Handles customer-facing site creation, configuration, and management
 * UPDATED: Now uses DataLayer for PostgreSQL/JSON abstraction
 */
class SiteManager
{
    private $dataLayer;
    private string $sitesDirectory;

    public function __construct()
    {
        require_once __DIR__ . '/../includes/DataLayer.php';
        $this->dataLayer = getDataLayer();
        $this->sitesDirectory = __DIR__ . '/../sites';
    }

    public function createSite(array $siteData): array
    {
        $domain = $siteData['domain'];
        $siteId = str_replace('.', '_', $domain);

        if ($this->dataLayer->getSite($siteId)) {
            throw new Exception("Site {$domain} already exists");
        }

        $site = [
            'site_id' => $siteId,
            'domain' => $domain,
            'name' => $siteData['name'],
            'description' => $siteData['description'],
            'template' => $siteData['template'] ?? 'default',
            'active' => true,
            'created_at' => date('Y-m-d H:i:s'),
            'categories' => $this->getDefaultCategories(),
            'theme' => $this->getDefaultTheme($domain),
            'features' => $this->getDefaultFeatures(),
            'billing' => $this->getDefaultBilling(),
            'stats' => [
                'total_customers' => 0,
                'active_operators' => 0,
                'total_calls' => 0,
                'total_revenue' => 0
            ]
        ];

        $this->dataLayer->saveSite($site);
        $this->createSiteDirectory($domain);
        return $site;
    }

    private function createSiteDirectory(string $domain): void
    {
        $siteDir = $this->sitesDirectory . '/' . $domain;

        if (!is_dir($siteDir)) {
            mkdir($siteDir, 0755, true);
            mkdir($siteDir . '/assets/css', 0755, true);
            mkdir($siteDir . '/assets/js', 0755, true);
            mkdir($siteDir . '/assets/images', 0755, true);
            mkdir($siteDir . '/templates', 0755, true);
            mkdir($siteDir . '/includes', 0755, true);
            mkdir($siteDir . '/uploads/profiles', 0755, true);
            mkdir($siteDir . '/uploads/content', 0755, true);
        }
    }

    private function getDefaultCategories(): array
    {
        return [
            'women_home_alone' => ['name' => 'Women Home Alone', 'active' => true],
            'mature' => ['name' => 'Mature', 'active' => true],
            'bbw' => ['name' => 'BBW', 'active' => true],
            'latina' => ['name' => 'Latina', 'active' => true],
            'asian' => ['name' => 'Asian', 'active' => true],
            'ebony' => ['name' => 'Ebony', 'active' => true],
            'couples' => ['name' => 'Couples', 'active' => true]
        ];
    }

    private function getDefaultTheme(string $domain): array
    {
        return [
            'primary_color' => '#e74c3c',
            'secondary_color' => '#c0392b',
            'accent_color' => '#f39c12',
            'background_color' => '#1c1c1c',
            'text_color' => '#ffffff',
            'logo_url' => "/assets/{$domain}/logo.png",
            'favicon_url' => "/assets/{$domain}/favicon.ico",
            'custom_css' => "/assets/{$domain}/theme.css",
            'font_family' => 'Inter, sans-serif',
            'layout_style' => 'modern'
        ];
    }

    private function getDefaultFeatures(): array
    {
        return [
            'customer_signup' => true,
            'customer_login' => true,
            'operator_profiles' => true,
            'advanced_search' => true,
            'messaging' => true,
            'video_calls' => true,
            'chat_rooms' => true,
            'content_marketplace' => true,
            'favorites' => true,
            'notifications' => true
        ];
    }

    private function getDefaultBilling(): array
    {
        return [
            'currency' => 'USD',
            'tax_rate' => 0.0,
            'minimum_purchase' => 9.99,
            'credit_packages_enabled' => true
        ];
    }

    public function getSite(string $identifier): ?array
    {
        return $this->dataLayer->getSite($identifier);
    }

    public function getAllSites(): array
    {
        return $this->dataLayer->getAllSites();
    }

    public function updateSite(string $siteId, array $updates): array
    {
        $site = $this->dataLayer->getSite($siteId);
        
        if (!$site) {
            throw new Exception('Site not found');
        }

        foreach ($updates as $key => $value) {
            if (in_array($key, ['name', 'description', 'template', 'active', 'theme', 'features', 'billing'])) {
                $site[$key] = $value;
            }
        }

        $site['updated_at'] = date('Y-m-d H:i:s');
        $this->dataLayer->saveSite($site);
        return $site;
    }

    public function deleteSite(string $siteId): bool
    {
        $site = $this->dataLayer->getSite($siteId);
        
        if (!$site) {
            throw new Exception('Site not found');
        }

        $site['active'] = false;
        $site['deleted_at'] = date('Y-m-d H:i:s');
        $this->dataLayer->saveSite($site);
        return true;
    }
}
