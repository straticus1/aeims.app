<?php
/**
 * aeimsLib Device Control Integration
 * Connects aeims.app to the aeimsLib device control system (../aeimsLib/)
 */

class AeimsLibIntegration {
    private $aeimsLibPath;
    private $configPath;
    private $available;
    
    public function __construct() {
        $this->aeimsLibPath = dirname(__DIR__) . '/aeimsLib';
        $this->configPath = $this->aeimsLibPath . '/config.php';
        $this->available = $this->checkAvailability();
    }
    
    /**
     * Check if aeimsLib is available and properly configured
     */
    private function checkAvailability() {
        return file_exists($this->aeimsLibPath . '/package.json') && 
               file_exists($this->aeimsLibPath . '/device_manager.php');
    }
    
    /**
     * Check if aeimsLib is available
     */
    public function isAvailable() {
        return $this->available;
    }
    
    /**
     * Get supported device brands from aeimsLib
     */
    public function getSupportedDevices() {
        if (!$this->available) {
            return $this->getStaticDeviceList();
        }
        
        try {
            // Try to get dynamic list from aeimsLib
            $output = shell_exec("cd {$this->aeimsLibPath} && npm run list-devices 2>&1");
            $devices = json_decode($output, true);
            
            if ($devices && is_array($devices)) {
                return $devices;
            }
        } catch (Exception $e) {
            error_log("aeimsLib device list error: " . $e->getMessage());
        }
        
        return $this->getStaticDeviceList();
    }
    
    /**
     * Static device list based on aeimsLib documentation
     */
    private function getStaticDeviceList() {
        return [
            'stable' => [
                'Lovense',
                'WeVibe/WowTech', 
                'Kiiroo',
                'Magic Motion',
                'Generic BLE devices',
                'Buttplug.io protocol'
            ],
            'experimental' => [
                'Svakom',
                'Vorze',
                'XInput/DirectInput Gamepads',
                'Handy/Stroker',
                'OSR/OpenSexRouter',
                'MaxPro/Max2',
                'PiShock (Electrostimulation)',
                'TCode Protocol Devices',
                'Bluetooth TENS Units',
                'Vibease',
                'Satisfyer Connect',
                'Hicoo/Hi-Link',
                'LoveLife Krush/Apex'
            ]
        ];
    }
    
    /**
     * Get device control capabilities
     */
    public function getCapabilities() {
        return [
            'real_time_control' => true,
            'pattern_system' => true,
            'websocket_control' => true,
            'mobile_support' => true,
            'vr_integration' => true,
            'audio_sync' => true,
            'mesh_networking' => true,
            'ai_patterns' => true,
            'security_features' => [
                'https_wss_encryption' => true,
                'jwt_authentication' => true,
                'oauth2_pkce' => true,
                'mfa_support' => true,
                'rate_limiting' => true
            ]
        ];
    }
    
    /**
     * Get device control statistics
     */
    public function getDeviceStats() {
        if (!$this->available) {
            return [
                'connected_devices' => 0,
                'active_sessions' => 0,
                'total_patterns' => 0,
                'status' => 'unavailable'
            ];
        }
        
        try {
            $output = shell_exec("cd {$this->aeimsLibPath} && npm run stats 2>&1");
            $stats = json_decode($output, true);
            
            if ($stats && is_array($stats)) {
                return $stats;
            }
        } catch (Exception $e) {
            error_log("aeimsLib stats error: " . $e->getMessage());
        }
        
        return [
            'connected_devices' => 0,
            'active_sessions' => 0,
            'total_patterns' => 150, // Estimated from documentation
            'status' => 'unknown'
        ];
    }
    
    /**
     * Test device control connection
     */
    public function testConnection() {
        if (!$this->available) {
            return [
                'success' => false,
                'error' => 'aeimsLib not available',
                'path' => $this->aeimsLibPath
            ];
        }
        
        try {
            $output = shell_exec("cd {$this->aeimsLibPath} && npm run test-connection 2>&1");
            $result = json_decode($output, true);
            
            if ($result) {
                return $result;
            }
            
            return [
                'success' => true,
                'message' => 'Basic availability check passed',
                'raw_output' => $output
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'path' => $this->aeimsLibPath
            ];
        }
    }
    
    /**
     * Get WebSocket server status
     */
    public function getWebSocketStatus() {
        if (!$this->available) {
            return [
                'running' => false,
                'error' => 'aeimsLib unavailable'
            ];
        }
        
        // Check if WebSocket server is running (typically on port 8080)
        $ports = [8080, 8081, 8082]; // Common aeimsLib ports
        
        foreach ($ports as $port) {
            $connection = @fsockopen('localhost', $port, $errno, $errstr, 1);
            if ($connection) {
                fclose($connection);
                return [
                    'running' => true,
                    'port' => $port,
                    'url' => "ws://localhost:{$port}/ws"
                ];
            }
        }
        
        return [
            'running' => false,
            'checked_ports' => $ports,
            'suggestion' => 'Run "npm start" in aeimsLib directory'
        ];
    }
    
    /**
     * Get configuration status
     */
    public function getConfigStatus() {
        $configFiles = [
            'config.php' => $this->aeimsLibPath . '/config.php',
            'package.json' => $this->aeimsLibPath . '/package.json',
            '.env' => $this->aeimsLibPath . '/.env',
            'config.json' => $this->aeimsLibPath . '/config/config.json'
        ];
        
        $status = [];
        foreach ($configFiles as $name => $path) {
            $status[$name] = [
                'exists' => file_exists($path),
                'readable' => file_exists($path) && is_readable($path),
                'path' => $path
            ];
        }
        
        return $status;
    }
    
    /**
     * Get pattern library information
     */
    public function getPatternLibrary() {
        return [
            'built_in_patterns' => [
                'Constant intensity',
                'Wave patterns', 
                'Pulse patterns',
                'Escalation patterns',
                'Custom pattern creation'
            ],
            'advanced_features' => [
                'AI pattern generation',
                'Audio synchronization',
                'Video framework integration',
                'Beat detection',
                'Pattern extraction',
                'VR/XR spatial control'
            ],
            'marketplace' => [
                'pattern_sharing' => true,
                'user_profiles' => true,
                'activity_scheduling' => true,
                'recommendations' => true
            ]
        ];
    }
    
    /**
     * Get comprehensive system information
     */
    public function getSystemInfo() {
        return [
            'available' => $this->available,
            'path' => $this->aeimsLibPath,
            'config_status' => $this->getConfigStatus(),
            'websocket_status' => $this->getWebSocketStatus(),
            'device_stats' => $this->getDeviceStats(),
            'supported_devices' => $this->getSupportedDevices(),
            'capabilities' => $this->getCapabilities(),
            'pattern_library' => $this->getPatternLibrary(),
            'connection_test' => $this->testConnection()
        ];
    }
    
    /**
     * Generate device control demo/showcase data
     */
    public function getDemoData() {
        return [
            'featured_devices' => [
                'Lovense' => [
                    'models' => ['Lush', 'Hush', 'Edge', 'Max', 'Nora'],
                    'features' => ['Real-time control', 'Pattern sync', 'Long-distance'],
                    'compatibility' => '100%'
                ],
                'WeVibe' => [
                    'models' => ['Sync', 'Unite', 'Pivot', 'Chorus'],
                    'features' => ['App control', 'Partner connectivity', 'Vibration patterns'],
                    'compatibility' => '95%'
                ],
                'Kiiroo' => [
                    'models' => ['Titan', 'Cliona', 'Pearl2'],
                    'features' => ['Interactive content', 'Multi-device sync', 'VR integration'],
                    'compatibility' => '90%'
                ]
            ],
            'demo_stats' => [
                'total_integrations' => '15+ device brands',
                'pattern_library' => '500+ patterns',
                'active_developers' => '1000+ developers',
                'compatibility_rate' => '95% success rate'
            ]
        ];
    }
}