<?php
/**
 * AEIMS API Client
 * Connects to the AEIMS REST API (../aeims/api/)
 */

class AeimsApiClient {
    private $baseUrl;
    private $apiKey;
    private $timeout;
    
    public function __construct($baseUrl = null, $apiKey = null) {
        $this->baseUrl = $baseUrl ?: $this->getDefaultApiUrl();
        $this->apiKey = $apiKey ?: $this->getApiKey();
        $this->timeout = 30;
    }
    
    /**
     * Get default API URL
     */
    private function getDefaultApiUrl() {
        // Check if AEIMS API is running locally
        $possibleUrls = [
            'http://localhost:3000/api',
            'http://127.0.0.1:3000/api',
            'https://api.aeims.local/api'
        ];
        
        foreach ($possibleUrls as $url) {
            if ($this->testConnection($url)) {
                return $url;
            }
        }
        
        return 'http://localhost:3000/api'; // Default fallback
    }
    
    /**
     * Get API key from environment or config
     */
    private function getApiKey() {
        // Try environment variable first
        $apiKey = getenv('AEIMS_API_KEY');
        if ($apiKey) {
            return $apiKey;
        }
        
        // Try config file
        $configFile = dirname(__DIR__) . '/aeims/config/api.php';
        if (file_exists($configFile)) {
            $config = include $configFile;
            return $config['api_key'] ?? null;
        }
        
        return null;
    }
    
    /**
     * Test connection to API
     */
    private function testConnection($url) {
        $context = stream_context_create([
            'http' => [
                'timeout' => 2,
                'method' => 'GET'
            ]
        ]);
        
        $result = @file_get_contents($url . '/health', false, $context);
        return $result !== false;
    }
    
    /**
     * Make HTTP request to AEIMS API
     */
    private function makeRequest($endpoint, $method = 'GET', $data = null) {
        $url = $this->baseUrl . '/' . ltrim($endpoint, '/');
        
        $options = [
            'http' => [
                'method' => $method,
                'timeout' => $this->timeout,
                'header' => [
                    'Content-Type: application/json',
                    'User-Agent: aeims.app/1.0'
                ]
            ]
        ];
        
        if ($this->apiKey) {
            $options['http']['header'][] = 'Authorization: Bearer ' . $this->apiKey;
        }
        
        if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $options['http']['content'] = json_encode($data);
        }
        
        $context = stream_context_create($options);
        $response = @file_get_contents($url, false, $context);
        
        if ($response === false) {
            return [
                'error' => 'API request failed',
                'url' => $url,
                'method' => $method
            ];
        }
        
        $decoded = json_decode($response, true);
        if ($decoded === null) {
            return [
                'error' => 'Invalid JSON response',
                'raw_response' => $response
            ];
        }
        
        return $decoded;
    }
    
    /**
     * Health check
     */
    public function health() {
        return $this->makeRequest('/health');
    }
    
    /**
     * Domain Management API calls
     */
    public function getDomains() {
        return $this->makeRequest('/domains');
    }
    
    public function getDomain($domain) {
        return $this->makeRequest("/domains/" . urlencode($domain));
    }
    
    public function createDomain($domain, $config = []) {
        return $this->makeRequest('/domains', 'POST', [
            'domain' => $domain,
            'config' => $config
        ]);
    }
    
    public function updateDomainStatus($domain, $status) {
        return $this->makeRequest("/domains/" . urlencode($domain) . "/status", 'PUT', [
            'status' => $status
        ]);
    }
    
    public function deleteDomain($domain) {
        return $this->makeRequest("/domains/" . urlencode($domain), 'DELETE');
    }
    
    /**
     * Statistics API calls
     */
    public function getSystemStats() {
        return $this->makeRequest('/stats/system');
    }
    
    public function getDomainStats($domain) {
        return $this->makeRequest("/stats/domains/" . urlencode($domain));
    }
    
    public function getCallStats($timeframe = '24h') {
        return $this->makeRequest("/stats/calls?timeframe=" . urlencode($timeframe));
    }
    
    public function getOperatorStats() {
        return $this->makeRequest('/stats/operators');
    }
    
    /**
     * Call Management API calls
     */
    public function getActiveCalls() {
        return $this->makeRequest('/calls/active');
    }
    
    public function getCall($callId) {
        return $this->makeRequest("/calls/" . urlencode($callId));
    }
    
    public function muteCall($callId, $participant = null) {
        $data = ['action' => 'mute'];
        if ($participant) {
            $data['participant'] = $participant;
        }
        return $this->makeRequest("/calls/" . urlencode($callId) . "/control", 'POST', $data);
    }
    
    public function transferCall($callId, $destination) {
        return $this->makeRequest("/calls/" . urlencode($callId) . "/transfer", 'POST', [
            'destination' => $destination
        ]);
    }
    
    /**
     * FreeSWITCH Integration API calls
     */
    public function getFreeSwitchStatus() {
        return $this->makeRequest('/freeswitch/status');
    }
    
    public function getFreeSwitchStats() {
        return $this->makeRequest('/freeswitch/stats');
    }
    
    /**
     * Device Control API calls (aeimsLib integration)
     */
    public function getConnectedDevices() {
        return $this->makeRequest('/devices/connected');
    }
    
    public function sendDeviceCommand($deviceId, $command) {
        return $this->makeRequest("/devices/" . urlencode($deviceId) . "/command", 'POST', [
            'command' => $command
        ]);
    }
    
    public function getDeviceStatus($deviceId) {
        return $this->makeRequest("/devices/" . urlencode($deviceId) . "/status");
    }
    
    /**
     * Monitoring API calls
     */
    public function getSystemHealth() {
        return $this->makeRequest('/monitoring/health');
    }
    
    public function getMetrics($metric = null) {
        $endpoint = '/monitoring/metrics';
        if ($metric) {
            $endpoint .= '?metric=' . urlencode($metric);
        }
        return $this->makeRequest($endpoint);
    }
    
    public function getAlerts() {
        return $this->makeRequest('/monitoring/alerts');
    }
    
    /**
     * User Management API calls
     */
    public function getOperators() {
        return $this->makeRequest('/operators');
    }
    
    public function getOperator($operatorId) {
        return $this->makeRequest("/operators/" . urlencode($operatorId));
    }
    
    public function getCustomers() {
        return $this->makeRequest('/customers');
    }
    
    /**
     * Billing & Revenue API calls
     */
    public function getRevenue($timeframe = '24h') {
        return $this->makeRequest("/billing/revenue?timeframe=" . urlencode($timeframe));
    }
    
    public function getTransactions($limit = 100) {
        return $this->makeRequest("/billing/transactions?limit=" . intval($limit));
    }
    
    /**
     * Configuration API calls
     */
    public function getConfig($section = null) {
        $endpoint = '/config';
        if ($section) {
            $endpoint .= '/' . urlencode($section);
        }
        return $this->makeRequest($endpoint);
    }
    
    public function updateConfig($section, $config) {
        return $this->makeRequest("/config/" . urlencode($section), 'PUT', $config);
    }
    
    /**
     * Check if API is available
     */
    public function isAvailable() {
        $health = $this->health();
        return !isset($health['error']);
    }
    
    /**
     * Get comprehensive system information
     */
    public function getSystemInfo() {
        return [
            'api_available' => $this->isAvailable(),
            'base_url' => $this->baseUrl,
            'has_api_key' => !empty($this->apiKey),
            'health' => $this->health(),
            'system_stats' => $this->getSystemStats(),
            'freeswitch_status' => $this->getFreeSwitchStatus()
        ];
    }
}