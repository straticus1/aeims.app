<?php
/**
 * Geo-Location Manager
 * Detects EU users for GDPR compliance
 * Manages geo-based content restrictions
 */

class GeoLocationManager {
    private static $instance = null;

    // EU member states + EEA countries
    private $euCountries = [
        'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR',
        'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL',
        'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE',
        'IS', 'LI', 'NO' // EEA countries
    ];

    // US states with age verification laws
    private $restrictedUSStates = [
        'FL', 'LA', 'AR', 'MS', 'TX', 'UT', 'VA', 'MT'
    ];

    private $cacheFile;
    private $cache = [];

    private function __construct() {
        $this->cacheFile = __DIR__ . '/../data/geo_cache.json';
        $this->loadCache();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function loadCache() {
        if (file_exists($this->cacheFile)) {
            $this->cache = json_decode(file_get_contents($this->cacheFile), true) ?? [];

            // Clean old cache entries (older than 24 hours)
            $cutoff = time() - (24 * 3600);
            foreach ($this->cache as $ip => $data) {
                if ($data['timestamp'] < $cutoff) {
                    unset($this->cache[$ip]);
                }
            }
        }
    }

    private function saveCache() {
        file_put_contents($this->cacheFile, json_encode($this->cache, JSON_PRETTY_PRINT));
    }

    /**
     * Get location data for IP address
     */
    public function getLocationData($ip = null) {
        if ($ip === null) {
            $ip = $this->getClientIP();
        }

        // Check cache first
        if (isset($this->cache[$ip])) {
            return $this->cache[$ip]['data'];
        }

        // Detect local/private IPs
        if ($this->isLocalIP($ip)) {
            return [
                'country_code' => 'US',
                'country_name' => 'United States',
                'region' => 'Unknown',
                'city' => 'Local',
                'is_eu' => false,
                'is_restricted_us_state' => false,
                'source' => 'local_detection'
            ];
        }

        // Try multiple geo-location services
        $location = $this->geolocateIP($ip);

        // Cache the result
        $this->cache[$ip] = [
            'data' => $location,
            'timestamp' => time()
        ];
        $this->saveCache();

        return $location;
    }

    /**
     * Check if user is from EU
     */
    public function isEUUser($ip = null) {
        $location = $this->getLocationData($ip);
        return $location['is_eu'] ?? false;
    }

    /**
     * Check if user is from restricted US state
     */
    public function isRestrictedUSState($ip = null) {
        $location = $this->getLocationData($ip);
        return $location['is_restricted_us_state'] ?? false;
    }

    /**
     * Get user's country code
     */
    public function getCountryCode($ip = null) {
        $location = $this->getLocationData($ip);
        return $location['country_code'] ?? 'UNKNOWN';
    }

    /**
     * Get client IP address (handles proxies)
     */
    private function getClientIP() {
        $ip = null;

        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // Handle multiple IPs in X-Forwarded-For
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
        } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $ip = $_SERVER['HTTP_X_REAL_IP'];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        // Validate IP
        if ($ip && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            // If private/reserved IP, use fallback
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        }

        return $ip;
    }

    /**
     * Check if IP is local/private
     */
    private function isLocalIP($ip) {
        return !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }

    /**
     * Geolocate IP using multiple services
     */
    private function geolocateIP($ip) {
        // Try ip-api.com (free, no API key required)
        $location = $this->tryIPAPI($ip);
        if ($location) {
            return $location;
        }

        // Fallback: Try ipapi.co
        $location = $this->tryIPAPICo($ip);
        if ($location) {
            return $location;
        }

        // Ultimate fallback
        return [
            'country_code' => 'UNKNOWN',
            'country_name' => 'Unknown',
            'region' => 'Unknown',
            'city' => 'Unknown',
            'is_eu' => false,
            'is_restricted_us_state' => false,
            'source' => 'fallback'
        ];
    }

    /**
     * Try ip-api.com service
     */
    private function tryIPAPI($ip) {
        try {
            $url = "http://ip-api.com/json/{$ip}?fields=status,country,countryCode,region,regionName,city";
            $context = stream_context_create([
                'http' => [
                    'timeout' => 2,
                    'ignore_errors' => true
                ]
            ]);

            $response = @file_get_contents($url, false, $context);
            if ($response === false) {
                return null;
            }

            $data = json_decode($response, true);
            if (!$data || ($data['status'] ?? '') !== 'success') {
                return null;
            }

            $countryCode = $data['countryCode'] ?? '';
            $regionCode = $data['region'] ?? '';

            return [
                'country_code' => $countryCode,
                'country_name' => $data['country'] ?? 'Unknown',
                'region' => $regionCode,
                'region_name' => $data['regionName'] ?? '',
                'city' => $data['city'] ?? 'Unknown',
                'is_eu' => in_array($countryCode, $this->euCountries),
                'is_restricted_us_state' => ($countryCode === 'US' && in_array($regionCode, $this->restrictedUSStates)),
                'source' => 'ip-api.com'
            ];
        } catch (Exception $e) {
            error_log("IP geolocation failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Try ipapi.co service
     */
    private function tryIPAPICo($ip) {
        try {
            $url = "https://ipapi.co/{$ip}/json/";
            $context = stream_context_create([
                'http' => [
                    'timeout' => 2,
                    'ignore_errors' => true
                ]
            ]);

            $response = @file_get_contents($url, false, $context);
            if ($response === false) {
                return null;
            }

            $data = json_decode($response, true);
            if (!$data || isset($data['error'])) {
                return null;
            }

            $countryCode = $data['country_code'] ?? '';
            $regionCode = $data['region_code'] ?? '';

            return [
                'country_code' => $countryCode,
                'country_name' => $data['country_name'] ?? 'Unknown',
                'region' => $regionCode,
                'region_name' => $data['region'] ?? '',
                'city' => $data['city'] ?? 'Unknown',
                'is_eu' => in_array($countryCode, $this->euCountries),
                'is_restricted_us_state' => ($countryCode === 'US' && in_array($regionCode, $this->restrictedUSStates)),
                'source' => 'ipapi.co'
            ];
        } catch (Exception $e) {
            error_log("IP geolocation failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Show GDPR consent banner for EU users
     */
    public function requireGDPRConsent() {
        if ($this->isEUUser() && !isset($_COOKIE['gdpr_consent'])) {
            return true;
        }
        return false;
    }

    /**
     * Show enhanced age verification for restricted states
     */
    public function requireEnhancedAgeVerification() {
        return $this->isRestrictedUSState();
    }

    /**
     * Get localized legal requirements
     */
    public function getLegalRequirements() {
        $location = $this->getLocationData();
        $requirements = [];

        if ($location['is_eu']) {
            $requirements[] = 'GDPR consent required';
            $requirements[] = 'Right to data portability';
            $requirements[] = 'Right to be forgotten';
        }

        if ($location['is_restricted_us_state']) {
            $requirements[] = 'Enhanced age verification required';
            $requirements[] = 'State-specific content filtering';
        }

        if ($location['country_code'] === 'US') {
            $requirements[] = '18 U.S.C. § 2257 compliance';
            $requirements[] = 'FOSTA compliance';
        }

        return $requirements;
    }
}
