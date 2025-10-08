<?php

namespace AEIMS\Services;

use Exception;

/**
 * Customer Management Service
 * Handles customer accounts, authentication, and activity for multi-site platform
 */
class CustomerManager
{
    private array $customers = [];
    private string $dataFile;
    private string $activityFile;

    public function __construct()
    {
        $this->dataFile = __DIR__ . '/../data/customers.json';
        $this->activityFile = __DIR__ . '/../data/customer_activity.json';
        $this->loadData();
    }

    private function loadData(): void
    {
        if (file_exists($this->dataFile)) {
            $data = json_decode(file_get_contents($this->dataFile), true);
            $this->customers = $data['customers'] ?? [];
        }
    }

    private function saveData(): void
    {
        $dataDir = dirname($this->dataFile);
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }

        $data = [
            'customers' => $this->customers,
            'last_updated' => date('Y-m-d H:i:s')
        ];

        file_put_contents($this->dataFile, json_encode($data, JSON_PRETTY_PRINT));
    }

    public function createCustomer(array $customerData): array
    {
        $customerId = 'cust_' . uniqid();
        $username = $customerData['username'];
        $email = $customerData['email'];

        // Check if username or email already exists
        foreach ($this->customers as $customer) {
            if ($customer['username'] === $username) {
                throw new Exception('Username already exists');
            }
            if ($customer['email'] === $email) {
                throw new Exception('Email already registered');
            }
        }

        $customer = [
            'customer_id' => $customerId,
            'username' => $username,
            'email' => $email,
            'password_hash' => password_hash($customerData['password'], PASSWORD_DEFAULT),
            'site_domain' => $customerData['site_domain'],
            'registration_ip' => $customerData['registration_ip'] ?? 'unknown',
            'active' => true,
            'verified' => false,
            'created_at' => date('Y-m-d H:i:s'),
            'profile' => [
                'display_name' => $username,
                'bio' => '',
                'preferences' => [],
                'avatar_url' => '',
                'age_verified' => false
            ],
            'billing' => [
                'credits' => 10.00, // Welcome bonus
                'total_spent' => 0.00,
                'payment_methods' => []
            ],
            'stats' => [
                'total_sessions' => 0,
                'total_messages' => 0,
                'total_calls' => 0,
                'last_activity' => date('Y-m-d H:i:s')
            ]
        ];

        $this->customers[$customerId] = $customer;
        $this->saveData();

        // Remove password hash from returned data
        unset($customer['password_hash']);
        return $customer;
    }

    public function authenticate(string $username, string $password, string $siteDomain): ?array
    {
        foreach ($this->customers as $customer) {
            if (($customer['username'] === $username || $customer['email'] === $username)
                && $customer['active']) {

                if (password_verify($password, $customer['password_hash'])) {
                    // Update last activity
                    $this->customers[$customer['customer_id']]['stats']['last_activity'] = date('Y-m-d H:i:s');
                    $this->customers[$customer['customer_id']]['stats']['total_sessions']++;
                    $this->saveData();

                    // Remove password hash from returned data
                    $customerData = $customer;
                    unset($customerData['password_hash']);
                    return $customerData;
                }
            }
        }

        return null;
    }

    public function getCustomer(string $customerId): ?array
    {
        if (isset($this->customers[$customerId])) {
            $customer = $this->customers[$customerId];
            unset($customer['password_hash']);
            return $customer;
        }
        return null;
    }

    public function updateCustomer(string $customerId, array $updates): array
    {
        if (!isset($this->customers[$customerId])) {
            throw new Exception('Customer not found');
        }

        $allowedFields = ['username', 'email', 'active', 'verified', 'profile', 'billing'];

        foreach ($updates as $key => $value) {
            if (in_array($key, $allowedFields)) {
                if ($key === 'profile' || $key === 'billing') {
                    // Merge nested objects
                    $this->customers[$customerId][$key] = array_merge(
                        $this->customers[$customerId][$key] ?? [],
                        $value
                    );
                } else {
                    $this->customers[$customerId][$key] = $value;
                }
            }
        }

        $this->customers[$customerId]['updated_at'] = date('Y-m-d H:i:s');
        $this->saveData();

        return $this->getCustomer($customerId);
    }

    public function addCredits(string $customerId, float $amount, string $reason = ''): bool
    {
        if (!isset($this->customers[$customerId])) {
            throw new Exception('Customer not found');
        }

        $this->customers[$customerId]['billing']['credits'] += $amount;
        $this->saveData();

        $this->logActivity($customerId, 'credits_added', [
            'amount' => $amount,
            'reason' => $reason,
            'new_balance' => $this->customers[$customerId]['billing']['credits']
        ]);

        return true;
    }

    public function deductCredits(string $customerId, float $amount, string $reason = ''): bool
    {
        if (!isset($this->customers[$customerId])) {
            throw new Exception('Customer not found');
        }

        if ($this->customers[$customerId]['billing']['credits'] < $amount) {
            throw new Exception('Insufficient credits');
        }

        $this->customers[$customerId]['billing']['credits'] -= $amount;
        $this->customers[$customerId]['billing']['total_spent'] += $amount;
        $this->saveData();

        $this->logActivity($customerId, 'credits_deducted', [
            'amount' => $amount,
            'reason' => $reason,
            'new_balance' => $this->customers[$customerId]['billing']['credits']
        ]);

        return true;
    }

    public function getCustomerCredits(string $customerId): float
    {
        if (!isset($this->customers[$customerId])) {
            throw new Exception('Customer not found');
        }

        return $this->customers[$customerId]['billing']['credits'];
    }

    public function logActivity(string $customerId, string $action, array $data = []): void
    {
        $activity = [
            'customer_id' => $customerId,
            'action' => $action,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ];

        $activities = [];
        if (file_exists($this->activityFile)) {
            $existingData = json_decode(file_get_contents($this->activityFile), true);
            $activities = $existingData['activities'] ?? [];
        }

        $activities[] = $activity;

        // Keep only last 1000 activities to prevent file from growing too large
        if (count($activities) > 1000) {
            $activities = array_slice($activities, -1000);
        }

        $dataDir = dirname($this->activityFile);
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }

        $data = [
            'activities' => $activities,
            'last_updated' => date('Y-m-d H:i:s')
        ];

        file_put_contents($this->activityFile, json_encode($data, JSON_PRETTY_PRINT));
    }

    public function getCustomerActivity(string $customerId, int $limit = 50): array
    {
        if (!file_exists($this->activityFile)) {
            return [];
        }

        $data = json_decode(file_get_contents($this->activityFile), true);
        $activities = $data['activities'] ?? [];

        $customerActivities = array_filter($activities, function($activity) use ($customerId) {
            return $activity['customer_id'] === $customerId;
        });

        return array_slice(array_reverse($customerActivities), 0, $limit);
    }

    public function getAllCustomers(): array
    {
        $customers = [];
        foreach ($this->customers as $customer) {
            $customerData = $customer;
            unset($customerData['password_hash']);
            $customers[] = $customerData;
        }
        return $customers;
    }

    public function getCustomersBySite(string $siteDomain): array
    {
        $customers = [];
        foreach ($this->customers as $customer) {
            if ($customer['site_domain'] === $siteDomain) {
                $customerData = $customer;
                unset($customerData['password_hash']);
                $customers[] = $customerData;
            }
        }
        return $customers;
    }

    public function getCustomerById(string $customerId): ?array
    {
        if (!isset($this->customers[$customerId])) {
            return null;
        }

        $customer = $this->customers[$customerId];
        unset($customer['password_hash']);
        return $customer;
    }

    public function getCustomerStats(string $customerId): array
    {
        if (!isset($this->customers[$customerId])) {
            throw new Exception('Customer not found');
        }

        return $this->customers[$customerId]['stats'];
    }

    public function updateCustomerStats(string $customerId, array $stats): void
    {
        if (!isset($this->customers[$customerId])) {
            throw new Exception('Customer not found');
        }

        foreach ($stats as $key => $value) {
            if (isset($this->customers[$customerId]['stats'][$key])) {
                $this->customers[$customerId]['stats'][$key] = $value;
            }
        }

        $this->customers[$customerId]['stats']['last_activity'] = date('Y-m-d H:i:s');
        $this->saveData();
    }

    public function deleteCustomer(string $customerId): bool
    {
        if (!isset($this->customers[$customerId])) {
            throw new Exception('Customer not found');
        }

        // Archive instead of delete
        $this->customers[$customerId]['active'] = false;
        $this->customers[$customerId]['deleted_at'] = date('Y-m-d H:i:s');
        $this->saveData();

        $this->logActivity($customerId, 'account_deleted', [
            'reason' => 'user_request'
        ]);

        return true;
    }
}