<?php

namespace AEIMS\Services;

use Exception;

/**
 * Site Management Service
 * Handles customer-facing site creation, configuration, and management
 */
class SiteManager
{
    private array $sites = [];
    private string $dataFile;
    private string $sitesDirectory;

    public function __construct()
    {
        $this->dataFile = __DIR__ . '/../data/sites.json';
        $this->sitesDirectory = __DIR__ . '/../sites';
        $this->loadData();
    }

    private function loadData(): void
    {
        if (file_exists($this->dataFile)) {
            $data = json_decode(file_get_contents($this->dataFile), true);
            $this->sites = $data['sites'] ?? [];
        }
    }

    private function saveData(): void
    {
        $dataDir = dirname($this->dataFile);
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }

        $data = [
            'sites' => $this->sites,
            'last_updated' => date('Y-m-d H:i:s')
        ];

        file_put_contents($this->dataFile, json_encode($data, JSON_PRETTY_PRINT));
    }

    public function createSite(array $siteData): array
    {
        $domain = $siteData['domain'];
        $siteId = str_replace('.', '_', $domain);

        if (isset($this->sites[$siteId])) {
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

        $this->sites[$siteId] = $site;
        $this->saveData();

        // Create site directory structure
        $this->createSiteDirectory($domain);

        return $site;
    }

    private function createSiteDirectory(string $domain): void
    {
        $siteDir = $this->sitesDirectory . '/' . $domain;

        if (!is_dir($siteDir)) {
            mkdir($siteDir, 0755, true);

            // Create subdirectories
            mkdir($siteDir . '/assets', 0755, true);
            mkdir($siteDir . '/assets/css', 0755, true);
            mkdir($siteDir . '/assets/js', 0755, true);
            mkdir($siteDir . '/assets/images', 0755, true);
            mkdir($siteDir . '/templates', 0755, true);
            mkdir($siteDir . '/includes', 0755, true);
            mkdir($siteDir . '/uploads', 0755, true);
            mkdir($siteDir . '/uploads/profiles', 0755, true);
            mkdir($siteDir . '/uploads/content', 0755, true);
        }
    }

    private function getDefaultCategories(): array
    {
        return [
            'women_home_alone' => ['name' => 'Women Home Alone', 'active' => true],
            'sex' => ['name' => 'Sex', 'active' => true],
            'oral_sex' => ['name' => 'Oral Sex', 'active' => true],
            'mature' => ['name' => 'Mature', 'active' => true],
            'anal_sex' => ['name' => 'Anal Sex', 'active' => true],
            'sex_toys' => ['name' => 'Sex Toys', 'active' => true],
            'bbw' => ['name' => 'BBW', 'active' => true],
            'housewives' => ['name' => 'Housewives', 'active' => true],
            'latina' => ['name' => 'Latina', 'active' => true],
            'asian' => ['name' => 'Asian', 'active' => true],
            'ebony' => ['name' => 'Ebony', 'active' => true],
            'lesbians_bisexual_gay' => ['name' => 'Lesbians/BiSexual/Gay', 'active' => true],
            'others' => ['name' => 'Others', 'active' => true],
            'couples' => ['name' => 'Couples', 'active' => true],
            'spanish' => ['name' => 'Spanish', 'active' => true],
            'find_men' => ['name' => 'Find Men', 'active' => true],
            'transgender' => ['name' => 'Transgender', 'active' => true]
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
            'messaging_system' => true,
            'paid_messages' => true,
            'payment_system' => true,
            'reviews_ratings' => true,
            'content_sales' => true,
            'toy_integration' => true,
            'video_calls' => true,
            'phone_calls' => true,
            'chat' => true,
            'profile_pictures' => true,
            'rich_text_editor' => true,
            'ai_ad_creation' => true
        ];
    }

    private function getDefaultBilling(): array
    {
        return [
            'currency' => 'USD',
            'payment_methods' => ['credit_card', 'crypto', 'paypal'],
            'minimum_purchase' => 10.00,
            'call_rates' => [
                'standard' => 1.99,
                'premium' => 2.99,
                'vip' => 4.99,
                'elite' => 6.99
            ],
            'message_rates' => [
                'standard' => 0.50,
                'premium' => 1.00,
                'media' => 2.00
            ],
            'content_rates' => [
                'photo' => 5.00,
                'video' => 15.00,
                'custom' => 25.00
            ],
            'commission_rates' => [
                'operator' => 0.65,
                'site' => 0.35
            ]
        ];
    }

    public function getSite(string $domain): ?array
    {
        $siteId = str_replace('.', '_', $domain);
        return $this->sites[$siteId] ?? null;
    }

    public function getAllSites(): array
    {
        return array_values($this->sites);
    }

    public function updateSite(string $domain, array $updates): array
    {
        $siteId = str_replace('.', '_', $domain);

        if (!isset($this->sites[$siteId])) {
            throw new Exception("Site {$domain} not found");
        }

        foreach ($updates as $key => $value) {
            if (in_array($key, ['name', 'description', 'active', 'theme', 'features', 'billing', 'categories'])) {
                $this->sites[$siteId][$key] = $value;
            }
        }

        $this->sites[$siteId]['updated_at'] = date('Y-m-d H:i:s');
        $this->saveData();

        return $this->sites[$siteId];
    }

    public function addOperatorToSite(string $domain, string $operatorId, array $categories = []): bool
    {
        $site = $this->getSite($domain);
        if (!$site) {
            throw new Exception("Site {$domain} not found");
        }

        $siteId = str_replace('.', '_', $domain);

        if (!isset($this->sites[$siteId]['operators'])) {
            $this->sites[$siteId]['operators'] = [];
        }

        $this->sites[$siteId]['operators'][$operatorId] = [
            'operator_id' => $operatorId,
            'categories' => $categories,
            'active' => true,
            'joined_at' => date('Y-m-d H:i:s')
        ];

        $this->sites[$siteId]['stats']['active_operators'] = count($this->sites[$siteId]['operators']);
        $this->saveData();

        return true;
    }

    public function getSiteOperators(string $domain): array
    {
        $site = $this->getSite($domain);
        return $site['operators'] ?? [];
    }

    public function getSitesByOperator(string $operatorId): array
    {
        $operatorSites = [];

        foreach ($this->sites as $site) {
            if (isset($site['operators'][$operatorId])) {
                $operatorSites[] = $site;
            }
        }

        return $operatorSites;
    }

    public function updateSiteStats(string $domain, array $stats): void
    {
        $siteId = str_replace('.', '_', $domain);

        if (isset($this->sites[$siteId])) {
            foreach ($stats as $key => $value) {
                if (isset($this->sites[$siteId]['stats'][$key])) {
                    $this->sites[$siteId]['stats'][$key] = $value;
                }
            }
            $this->saveData();
        }
    }

    public function deleteSite(string $domain): bool
    {
        $siteId = str_replace('.', '_', $domain);

        if (!isset($this->sites[$siteId])) {
            throw new Exception("Site {$domain} not found");
        }

        // Archive the site instead of deleting
        $this->sites[$siteId]['active'] = false;
        $this->sites[$siteId]['deleted_at'] = date('Y-m-d H:i:s');

        $this->saveData();
        return true;
    }
}