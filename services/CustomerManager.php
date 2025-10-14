<?php

namespace AEIMS\Services;

use Exception;

/**
 * Customer Management Service
 * Handles customer accounts, authentication, and activity for multi-site platform
 * UPDATED: Now uses DataLayer for PostgreSQL/JSON abstraction
 */
class CustomerManager
{
    private $dataLayer;
    private string $activityFile;

    public function __construct()
    {
        // Use DataLayer for customer data access
        require_once __DIR__ . '/../includes/DataLayer.php';
        $this->dataLayer = getDataLayer();
        $this->activityFile = __DIR__ . '/../data/customer_activity.json';
    }

    private function getAllCustomersInternal(): array
    {
        // Load all customers from DataLayer
        $dataFile = __DIR__ . '/../data/customers.json';
        if (!file_exists($dataFile)) {
            return [];
        }
        $data = json_decode(file_get_contents($dataFile), true);
        return $data['customers'] ?? [];
    }

    public function createCustomer(array $customerData): array
    {
        $customerId = 'cust_' . uniqid();
        $username = $customerData['username'];
        $email = $customerData['email'];

        // Check if username or email already exists using DataLayer
        $existingByUsername = $this->dataLayer->getCustomer($username);
        if ($existingByUsername) {
            throw new Exception('Username already exists');
        }

        $existingByEmail = $this->dataLayer->getCustomer($email);
        if ($existingByEmail) {
            throw new Exception('Email already registered');
        }

        $customer = [
            'customer_id' => $customerId,
            'id' => $customerId,  // Add 'id' for compatibility
            'username' => $username,
            'email' => $email,
            'password_hash' => password_hash($customerData['password'], PASSWORD_DEFAULT),
            'site_domain' => $customerData['site_domain'],
            'sites' => [$customerData['site_domain']],  // Add sites array
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

        // Save using DataLayer
        $this->dataLayer->saveCustomer($customer);

        // Remove password hash from returned data
        unset($customer['password_hash']);
        return $customer;
    }

    public function authenticate(string $username, string $password, string $siteDomain): ?array
    {
        // Use DataLayer to get customer
        $customer = $this->dataLayer->getCustomer($username);

        if ($customer && $customer['active'] && password_verify($password, $customer['password_hash'])) {
            // Update last activity using DataLayer
            $customer['stats']['last_activity'] = date('Y-m-d H:i:s');
            $customer['stats']['total_sessions'] = ($customer['stats']['total_sessions'] ?? 0) + 1;
            $this->dataLayer->saveCustomer($customer);

            // Remove password hash from returned data
            unset($customer['password_hash']);
            return $customer;
        }

        return null;
    }

    public function getCustomer(string $customerId): ?array
    {
        // Use DataLayer to get customer by ID
        $customers = $this->getAllCustomersInternal();
        $customer = $customers[$customerId] ?? null;

        if ($customer) {
            unset($customer['password_hash']);
            return $customer;
        }
        return null;
    }

    public function updateCustomer(string $customerId, array $updates): array
    {
        $customer = $this->getCustomer($customerId);
        if (!$customer) {
            throw new Exception('Customer not found');
        }

        // Re-add password_hash for saving
        $customers = $this->getAllCustomersInternal();
        $fullCustomer = $customers[$customerId];

        $allowedFields = ['username', 'email', 'active', 'verified', 'profile', 'billing'];

        foreach ($updates as $key => $value) {
            if (in_array($key, $allowedFields)) {
                if ($key === 'profile' || $key === 'billing') {
                    // Merge nested objects
                    $fullCustomer[$key] = array_merge($fullCustomer[$key] ?? [], $value);
                } else {
                    $fullCustomer[$key] = $value;
                }
            }
        }

        $fullCustomer['updated_at'] = date('Y-m-d H:i:s');
        $this->dataLayer->saveCustomer($fullCustomer);

        return $this->getCustomer($customerId);
    }

    public function addCredits(string $customerId, float $amount, string $reason = ''): bool
    {
        $customers = $this->getAllCustomersInternal();
        if (!isset($customers[$customerId])) {
            throw new Exception('Customer not found');
        }

        $customer = $customers[$customerId];
        $customer['billing']['credits'] = ($customer['billing']['credits'] ?? 0) + $amount;
        $this->dataLayer->saveCustomer($customer);

        $this->logActivity($customerId, 'credits_added', [
            'amount' => $amount,
            'reason' => $reason,
            'new_balance' => $customer['billing']['credits']
        ]);

        return true;
    }

    public function deductCredits(string $customerId, float $amount, string $reason = ''): bool
    {
        $customers = $this->getAllCustomersInternal();
        if (!isset($customers[$customerId])) {
            throw new Exception('Customer not found');
        }

        $customer = $customers[$customerId];
        $credits = $customer['billing']['credits'] ?? 0;

        if ($credits < $amount) {
            throw new Exception('Insufficient credits');
        }

        $customer['billing']['credits'] = $credits - $amount;
        $customer['billing']['total_spent'] = ($customer['billing']['total_spent'] ?? 0) + $amount;
        $this->dataLayer->saveCustomer($customer);

        $this->logActivity($customerId, 'credits_deducted', [
            'amount' => $amount,
            'reason' => $reason,
            'new_balance' => $customer['billing']['credits']
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