<?php
/**
 * API Key Management System
 * Government ID Verification Services by After Dark Systems
 */

namespace IDVerify;

class APIKeyManager {
    private string $dataFile;
    private array $keys = [];

    public function __construct(string $dataDir = null) {
        $dataDir = $dataDir ?? __DIR__ . '/../data';
        $this->dataFile = $dataDir . '/api_keys.json';
        $this->loadKeys();
    }

    private function loadKeys(): void {
        if (file_exists($this->dataFile)) {
            $data = json_decode(file_get_contents($this->dataFile), true);
            $this->keys = $data['keys'] ?? [];
        }
    }

    private function saveKeys(): void {
        $dir = dirname($this->dataFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $data = [
            'keys' => $this->keys,
            'last_updated' => date('Y-m-d H:i:s')
        ];

        file_put_contents($this->dataFile, json_encode($data, JSON_PRETTY_PRINT));
        chmod($this->dataFile, 0600);
    }

    /**
     * Generate a new API key
     */
    public function generateKey(string $name, array $options = []): array {
        $apiKey = 'ADS_' . bin2hex(random_bytes(32));

        $keyData = [
            'api_key' => $apiKey,
            'name' => $name,
            'created_at' => date('Y-m-d H:i:s'),
            'created_by' => $options['created_by'] ?? 'system',
            'status' => 'active',
            'last_used' => null,
            'usage_count' => 0,
            'rate_limit' => $options['rate_limit'] ?? 1000,
            'rate_period' => $options['rate_period'] ?? 'hour',
            'ip_whitelist' => $options['ip_whitelist'] ?? [],
            'permissions' => $options['permissions'] ?? ['verify', 'retrieve'],
            'metadata' => $options['metadata'] ?? []
        ];

        $this->keys[$apiKey] = $keyData;
        $this->saveKeys();

        return $keyData;
    }

    /**
     * Validate an API key
     */
    public function validateKey(string $apiKey, string $permission = null): array {
        if (!isset($this->keys[$apiKey])) {
            return [
                'valid' => false,
                'error' => 'Invalid API key'
            ];
        }

        $keyData = $this->keys[$apiKey];

        // Check if key is active
        if ($keyData['status'] !== 'active') {
            return [
                'valid' => false,
                'error' => 'API key is ' . $keyData['status']
            ];
        }

        // Check permission
        if ($permission && !in_array($permission, $keyData['permissions'])) {
            return [
                'valid' => false,
                'error' => 'API key does not have permission: ' . $permission
            ];
        }

        // Check IP whitelist
        if (!empty($keyData['ip_whitelist'])) {
            $clientIP = $_SERVER['REMOTE_ADDR'] ?? '';
            if (!in_array($clientIP, $keyData['ip_whitelist'])) {
                return [
                    'valid' => false,
                    'error' => 'IP address not whitelisted'
                ];
            }
        }

        return [
            'valid' => true,
            'key_data' => $keyData
        ];
    }

    /**
     * Record API key usage
     */
    public function recordUsage(string $apiKey): void {
        if (isset($this->keys[$apiKey])) {
            $this->keys[$apiKey]['last_used'] = date('Y-m-d H:i:s');
            $this->keys[$apiKey]['usage_count']++;
            $this->saveKeys();
        }
    }

    /**
     * Revoke an API key
     */
    public function revokeKey(string $apiKey): bool {
        if (isset($this->keys[$apiKey])) {
            $this->keys[$apiKey]['status'] = 'revoked';
            $this->keys[$apiKey]['revoked_at'] = date('Y-m-d H:i:s');
            $this->saveKeys();
            return true;
        }
        return false;
    }

    /**
     * Get all API keys
     */
    public function getAllKeys(): array {
        return array_values($this->keys);
    }

    /**
     * Get API key statistics
     */
    public function getKeyStats(string $apiKey): ?array {
        return $this->keys[$apiKey] ?? null;
    }

    /**
     * Get usage statistics
     */
    public function getUsageStats(): array {
        $stats = [
            'total_keys' => count($this->keys),
            'active_keys' => 0,
            'revoked_keys' => 0,
            'total_usage' => 0
        ];

        foreach ($this->keys as $key) {
            if ($key['status'] === 'active') {
                $stats['active_keys']++;
            } elseif ($key['status'] === 'revoked') {
                $stats['revoked_keys']++;
            }
            $stats['total_usage'] += $key['usage_count'];
        }

        return $stats;
    }
}
