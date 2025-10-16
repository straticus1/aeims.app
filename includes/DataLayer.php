<?php
/**
 * AEIMS DataLayer - Universal Data Access Layer
 *
 * PHASE 2: Application Migration to PostgreSQL
 *
 * Purpose:
 * - Single interface for both JSON and PostgreSQL
 * - Automatic fallback if DB unavailable
 * - Dual-write support for migration safety
 * - Zero behavior changes during migration
 *
 * Usage:
 *   $data = new DataLayer();
 *   $customer = $data->getCustomer($username);  // Automatically uses DB or JSON
 */

require_once __DIR__ . '/DatabaseManager.php';
require_once __DIR__ . '/SecurityManager.php';

class DataLayer {
    private $db;
    private $security;
    private $useDatabase;
    private $dualWrite;

    // File paths
    private $customersFile = __DIR__ . '/../data/customers.json';
    private $operatorsFile = __DIR__ . '/../data/operators.json';
    private $messagesFile = __DIR__ . '/../data/messages.json';
    private $conversationsFile = __DIR__ . '/../data/conversations.json';
    private $contentItemsFile = __DIR__ . '/../data/content_items.json';
    private $favoritesFile = __DIR__ . '/../data/favorites.json';
    private $sitesFile = __DIR__ . '/../data/sites.json';
    private $operatorRequestsFile = __DIR__ . '/../data/operator_requests.json';
    private $chatRoomsFile = __DIR__ . '/../data/chat_rooms.json';

    public function __construct() {
        $this->security = SecurityManager::getInstance();
        $this->db = DatabaseManager::getInstance();

        // Check if database is enabled and available
        $this->useDatabase = $this->db->isEnabled() && $this->db->isAvailable();

        // Dual-write mode: write to both JSON and DB during migration
        $this->dualWrite = (getenv('DUAL_WRITE') === 'true');

        error_log("DataLayer initialized: useDatabase=" . ($this->useDatabase ? 'true' : 'false') .
                  ", dualWrite=" . ($this->dualWrite ? 'true' : 'false'));
    }

    // =========================================================================
    // CUSTOMER OPERATIONS
    // =========================================================================

    /**
     * Get customer by username
     */
    public function getCustomer($username) {
        // Try PostgreSQL first if available
        if ($this->useDatabase) {
            try {
                $result = $this->db->fetchOne(
                    "SELECT * FROM customers WHERE username = :username OR email = :username",
                    ['username' => $username]
                );
                if ($result) {
                    return $this->formatCustomerFromDB($result);
                }
            } catch (Exception $e) {
                error_log("DataLayer: DB query failed, falling back to JSON: " . $e->getMessage());
            }
        }

        // Fallback to JSON
        return $this->getCustomerFromJSON($username);
    }

    /**
     * Format customer data from database to match JSON structure
     */
    private function formatCustomerFromDB($row) {
        return [
            'customer_id' => $row['customer_id'],
            'id' => $row['customer_id'],
            'username' => $row['username'],
            'email' => $row['email'],
            'password_hash' => $row['password_hash'],
            'active' => $row['active'] ?? true,
            'verified' => $row['verified'] ?? false,
            'suspended' => $row['suspended'] ?? false,
            'registration_ip' => $row['registration_ip'] ?? null,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
            'profile' => [
                'display_name' => $row['display_name'] ?? $row['username'],
                'bio' => $row['bio'] ?? '',
                'avatar_url' => $row['avatar_url'] ?? '',
                'age_verified' => $row['age_verified'] ?? false,
                'preferences' => json_decode($row['preferences'] ?? '{}', true)
            ],
            'billing' => [
                'credits' => floatval($row['credits'] ?? 0),
                'total_spent' => floatval($row['total_spent'] ?? 0),
                'payment_methods' => []
            ],
            'stats' => [
                'total_sessions' => 0,
                'total_messages' => 0,
                'total_calls' => 0,
                'last_activity' => $row['last_login_at'] ?? $row['updated_at']
            ],
            'metadata' => json_decode($row['metadata'] ?? '{}', true)
        ];
    }

    /**
     * Save customer (dual-write if enabled)
     */
    public function saveCustomer($data) {
        $jsonSuccess = false;
        $dbSuccess = false;

        // Always write to JSON (primary during migration)
        $jsonSuccess = $this->saveCustomerToJSON($data);

        // Write to DB if enabled
        if ($this->useDatabase && $this->dualWrite) {
            try {
                $dbSuccess = $this->saveCustomerToDB($data);
            } catch (Exception $e) {
                error_log("DataLayer: DB save failed (continuing with JSON): " . $e->getMessage());
            }
        }

        return $jsonSuccess;  // JSON is source of truth during migration
    }

    /**
     * Search customers
     */
    public function searchCustomers($filters = []) {
        if ($this->useDatabase) {
            try {
                return $this->searchCustomersInDB($filters);
            } catch (Exception $e) {
                error_log("DataLayer: DB search failed, falling back to JSON: " . $e->getMessage());
            }
        }

        return $this->searchCustomersInJSON($filters);
    }

    // =========================================================================
    // OPERATOR OPERATIONS
    // =========================================================================

    /**
     * Get operator by username or email
     */
    public function getOperator($username) {
        if ($this->useDatabase) {
            try {
                $result = $this->db->fetchOne(
                    "SELECT * FROM operators WHERE username = :username OR email = :username",
                    ['username' => $username]
                );
                if ($result) {
                    return $this->formatOperatorFromDB($result);
                }
            } catch (Exception $e) {
                error_log("DataLayer: DB query failed, falling back to JSON: " . $e->getMessage());
            }
        }

        return $this->getOperatorFromJSON($username);
    }

    /**
     * Get operator by ID
     */
    public function getOperatorById($operatorId) {
        if ($this->useDatabase) {
            try {
                $result = $this->db->fetchOne(
                    "SELECT * FROM operators WHERE id = :id",
                    ['id' => $operatorId]
                );
                if ($result) {
                    return $this->formatOperatorFromDB($result);
                }
            } catch (Exception $e) {
                error_log("DataLayer: DB query failed for getOperatorById: " . $e->getMessage());
            }
        }

        // Fallback to JSON
        return $this->getOperatorByIdFromJSON($operatorId);
    }

    /**
     * Format operator data from database to match JSON structure
     */
    private function formatOperatorFromDB($row) {
        return [
            'id' => $row['id'],
            'operator_id' => $row['id'], // For backward compatibility
            'username' => $row['username'],
            'email' => $row['email'],
            'password_hash' => $row['password_hash'],
            'name' => $row['display_name'] ?? $row['username'],
            'display_name' => $row['display_name'] ?? $row['username'],
            'active' => $row['is_active'] ?? true,
            'verified' => $row['is_verified'] ?? false,
            'online' => $row['online'] ?? false,
            'available' => $row['available'] ?? false,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
            'profile' => [
                'display_name' => $row['display_name'] ?? $row['username'],
                'bio' => $row['bio'] ?? '',
                'age' => $row['age'] ?? null,
                'location' => $row['location'] ?? null,
                'avatar_url' => $row['avatar_url'] ?? '',
                'languages' => json_decode($row['languages'] ?? '[]', true),
                'specialties' => json_decode($row['specialties'] ?? '[]', true),
                'gallery_images' => json_decode($row['gallery_images'] ?? '[]', true),
                'category' => $row['category'] ?? 'standard',
                'available' => $row['available'] ?? false,
                'status_message' => $row['status_message'] ?? null,
                'display_names' => [],  // Legacy support
                'bios' => []  // Legacy support
            ],
            'earnings' => [
                'lifetime_total' => floatval($row['total_earned'] ?? 0),
                'pending' => floatval($row['pending_payout'] ?? 0),
                'available' => floatval($row['total_earned'] ?? 0) - floatval($row['pending_payout'] ?? 0)
            ],
            'settings' => [
                'commission_rate' => floatval($row['commission_rate'] ?? 0.60),
                'services' => []  // Will be populated from site-specific settings
            ],
            'stats' => [
                'total_calls' => 0,
                'total_messages' => 0,
                'today' => [
                    'rating' => 0,
                    'calls' => 0
                ]
            ],
            'domains' => json_decode($row['domains'] ?? '{}', true),
            'services' => json_decode($row['services'] ?? '{}', true),
            'metadata' => json_decode($row['metadata'] ?? '{}', true)
        ];
    }

    /**
     * Save operator (dual-write if enabled)
     */
    public function saveOperator($data) {
        $jsonSuccess = $this->saveOperatorToJSON($data);

        if ($this->useDatabase && $this->dualWrite) {
            try {
                $this->saveOperatorToDB($data);
            } catch (Exception $e) {
                error_log("DataLayer: DB save failed: " . $e->getMessage());
            }
        }

        return $jsonSuccess;
    }

    /**
     * Search operators (with filters)
     */
    public function searchOperators($siteId, $filters = []) {
        if ($this->useDatabase) {
            try {
                return $this->searchOperatorsInDB($siteId, $filters);
            } catch (Exception $e) {
                error_log("DataLayer: DB search failed, falling back to JSON: " . $e->getMessage());
            }
        }

        return $this->searchOperatorsInJSON($siteId, $filters);
    }

    // =========================================================================
    // MESSAGE OPERATIONS
    // =========================================================================

    /**
     * Get messages for a conversation
     */
    public function getMessages($conversationId, $limit = 50) {
        if ($this->useDatabase) {
            try {
                return $this->getMessagesFromDB($conversationId, $limit);
            } catch (Exception $e) {
                error_log("DataLayer: DB query failed, falling back to JSON: " . $e->getMessage());
            }
        }

        return $this->getMessagesFromJSON($conversationId, $limit);
    }

    /**
     * Save message (dual-write if enabled)
     */
    public function saveMessage($data) {
        $jsonSuccess = $this->saveMessageToJSON($data);

        if ($this->useDatabase && $this->dualWrite) {
            try {
                $this->saveMessageToDB($data);
            } catch (Exception $e) {
                error_log("DataLayer: DB save failed: " . $e->getMessage());
            }
        }

        return $jsonSuccess;
    }

    // =========================================================================
    // SITE OPERATIONS
    // =========================================================================

    /**
     * Get site by domain
     */
    public function getSite($domain) {
        if ($this->useDatabase) {
            try {
                $result = $this->db->fetchOne(
                    "SELECT * FROM sites WHERE domain = :domain",
                    ['domain' => $domain]
                );
                if ($result) {
                    return $this->formatSiteFromDB($result);
                }
            } catch (Exception $e) {
                error_log("DataLayer: DB query failed, falling back to JSON: " . $e->getMessage());
            }
        }

        return $this->getSiteFromJSON($domain);
    }

    /**
     * List all sites
     */
    public function getAllSites() {
        if ($this->useDatabase) {
            try {
                return $this->getAllSitesFromDB();
            } catch (Exception $e) {
                error_log("DataLayer: DB query failed, falling back to JSON: " . $e->getMessage());
            }
        }

        return $this->getAllSitesFromJSON();
    }

    // =========================================================================
    // JSON OPERATIONS - CUSTOMERS
    // =========================================================================

    private function getCustomerFromJSON($username) {
        if (!file_exists($this->customersFile)) {
            return null;
        }

        $data = $this->security->safeJSONRead($this->customersFile);
        $customers = $data['customers'] ?? [];

        foreach ($customers as $customerId => $customer) {
            if ($customer['username'] === $username) {
                $customer['customer_id'] = $customerId;
                return $customer;
            }
        }

        return null;
    }

    private function saveCustomerToJSON($data) {
        if (!file_exists($this->customersFile)) {
            $fileData = ['customers' => []];
        } else {
            $fileData = $this->security->safeJSONRead($this->customersFile);
        }

        $customers = $fileData['customers'] ?? [];

        // Generate ID if new customer
        if (!isset($data['customer_id'])) {
            $data['customer_id'] = 'cust_' . uniqid();
        }

        $customers[$data['customer_id']] = $data;
        $fileData['customers'] = $customers;

        return $this->security->safeJSONWrite($this->customersFile, $fileData);
    }

    private function searchCustomersInJSON($filters) {
        if (!file_exists($this->customersFile)) {
            return [];
        }

        $data = $this->security->safeJSONRead($this->customersFile);
        $customers = $data['customers'] ?? [];

        // Apply filters
        $results = [];
        foreach ($customers as $customerId => $customer) {
            $match = true;

            if (isset($filters['site_id'])) {
                if (!in_array($filters['site_id'], $customer['sites'] ?? [])) {
                    $match = false;
                }
            }

            if (isset($filters['active'])) {
                if ($customer['active'] !== $filters['active']) {
                    $match = false;
                }
            }

            if ($match) {
                $customer['customer_id'] = $customerId;
                $results[] = $customer;
            }
        }

        return $results;
    }

    // =========================================================================
    // JSON OPERATIONS - OPERATORS
    // =========================================================================

    private function getOperatorFromJSON($username) {
        if (!file_exists($this->operatorsFile)) {
            return null;
        }

        $data = $this->security->safeJSONRead($this->operatorsFile);
        $operators = $data['operators'] ?? [];

        foreach ($operators as $operatorId => $operator) {
            if ($operator['username'] === $username) {
                $operator['operator_id'] = $operatorId;
                return $operator;
            }
        }

        return null;
    }

    private function getOperatorByIdFromJSON($operatorId) {
        if (!file_exists($this->operatorsFile)) {
            return null;
        }

        $data = $this->security->safeJSONRead($this->operatorsFile);
        $operators = $data['operators'] ?? [];

        if (isset($operators[$operatorId])) {
            $operator = $operators[$operatorId];
            $operator['operator_id'] = $operatorId;
            $operator['id'] = $operatorId;
            return $operator;
        }

        return null;
    }

    private function saveOperatorToJSON($data) {
        if (!file_exists($this->operatorsFile)) {
            $fileData = ['operators' => []];
        } else {
            $fileData = $this->security->safeJSONRead($this->operatorsFile);
        }

        $operators = $fileData['operators'] ?? [];

        if (!isset($data['operator_id'])) {
            $data['operator_id'] = 'op_' . uniqid();
        }

        $operators[$data['operator_id']] = $data;
        $fileData['operators'] = $operators;

        return $this->security->safeJSONWrite($this->operatorsFile, $fileData);
    }

    private function searchOperatorsInJSON($siteId, $filters) {
        if (!file_exists($this->operatorsFile)) {
            return [];
        }

        $data = $this->security->safeJSONRead($this->operatorsFile);
        $operators = $data['operators'] ?? [];

        $results = [];
        foreach ($operators as $operatorId => $operator) {
            $match = true;

            // Filter by site
            if ($siteId && !in_array($siteId, $operator['sites'] ?? [])) {
                $match = false;
            }

            // Filter by active
            if (isset($filters['active']) && $operator['active'] !== $filters['active']) {
                $match = false;
            }

            // Filter by online
            if (isset($filters['online']) && $operator['online'] !== $filters['online']) {
                $match = false;
            }

            // Filter by category
            if (isset($filters['category']) && $operator['category'] !== $filters['category']) {
                $match = false;
            }

            if ($match) {
                $operator['operator_id'] = $operatorId;
                $results[] = $operator;
            }
        }

        return $results;
    }

    // =========================================================================
    // JSON OPERATIONS - MESSAGES
    // =========================================================================

    private function getMessagesFromJSON($conversationId, $limit) {
        if (!file_exists($this->messagesFile)) {
            return [];
        }

        $data = $this->security->safeJSONRead($this->messagesFile);
        $allMessages = $data['messages'] ?? [];

        $messages = [];
        foreach ($allMessages as $messageId => $message) {
            if ($message['conversation_id'] === $conversationId) {
                $message['message_id'] = $messageId;
                $messages[] = $message;
            }
        }

        // Sort by sent_at
        usort($messages, function($a, $b) {
            return strtotime($b['sent_at'] ?? 'now') - strtotime($a['sent_at'] ?? 'now');
        });

        return array_slice($messages, 0, $limit);
    }

    private function saveMessageToJSON($data) {
        if (!file_exists($this->messagesFile)) {
            $fileData = ['messages' => []];
        } else {
            $fileData = $this->security->safeJSONRead($this->messagesFile);
        }

        $messages = $fileData['messages'] ?? [];

        if (!isset($data['message_id'])) {
            $data['message_id'] = 'msg_' . uniqid();
        }

        $messages[$data['message_id']] = $data;
        $fileData['messages'] = $messages;

        return $this->security->safeJSONWrite($this->messagesFile, $fileData);
    }

    // =========================================================================
    // JSON OPERATIONS - SITES
    // =========================================================================

    private function getSiteFromJSON($domain) {
        if (!file_exists($this->sitesFile)) {
            return null;
        }

        $data = $this->security->safeJSONRead($this->sitesFile);
        $sites = $data['sites'] ?? [];

        foreach ($sites as $siteId => $site) {
            if ($site['domain'] === $domain) {
                $site['site_id'] = $siteId;
                return $site;
            }
        }

        return null;
    }

    private function getAllSitesFromJSON() {
        if (!file_exists($this->sitesFile)) {
            return [];
        }

        $data = $this->security->safeJSONRead($this->sitesFile);
        $sites = $data['sites'] ?? [];

        $results = [];
        foreach ($sites as $siteId => $site) {
            $site['site_id'] = $siteId;
            $results[] = $site;
        }

        return $results;
    }

    // =========================================================================
    // DATABASE OPERATIONS - CUSTOMERS
    // =========================================================================

    private function saveCustomerToDB($data) {
        // Check if exists
        $existing = $this->db->fetchOne(
            "SELECT customer_id FROM customers WHERE username = :username",
            ['username' => $data['username']]
        );

        if ($existing) {
            // Update
            return $this->db->update('customers', [
                'email' => $data['email'] ?? '',
                'password_hash' => $data['password_hash'] ?? '',
                'display_name' => $data['display_name'] ?? $data['username'],
                'active' => $data['active'] ?? true,
                'verified' => $data['verified'] ?? false,
                'credits' => $data['credits'] ?? 0,
                'metadata' => json_encode($data)
            ], 'customer_id = :id', ['id' => $existing['customer_id']]);
        } else {
            // Insert
            return $this->db->query(
                "INSERT INTO customers (username, email, password_hash, display_name, active, verified, credits, metadata)
                 VALUES (:username, :email, :password_hash, :display_name, :active, :verified, :credits, :metadata::jsonb)",
                [
                    'username' => $data['username'],
                    'email' => $data['email'] ?? '',
                    'password_hash' => $data['password_hash'] ?? '',
                    'display_name' => $data['display_name'] ?? $data['username'],
                    'active' => $data['active'] ?? true,
                    'verified' => $data['verified'] ?? false,
                    'credits' => $data['credits'] ?? 0,
                    'metadata' => json_encode($data)
                ]
            );
        }
    }


    private function searchCustomersInDB($filters) {
        $where = [];
        $params = [];

        if (isset($filters['site_id'])) {
            $where[] = "EXISTS (SELECT 1 FROM customer_sites WHERE customer_id = customers.customer_id AND site_id = :site_id)";
            $params['site_id'] = $filters['site_id'];
        }

        if (isset($filters['active'])) {
            $where[] = "active = :active";
            $params['active'] = $filters['active'];
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $sql = "SELECT * FROM customers $whereClause ORDER BY created_at DESC LIMIT 100";

        $results = $this->db->fetchAll($sql, $params);

        return array_map([$this, 'formatCustomerFromDB'], $results);
    }

    // =========================================================================
    // DATABASE OPERATIONS - OPERATORS
    // =========================================================================

    private function saveOperatorToDB($data) {
        $existing = $this->db->fetchOne(
            "SELECT id FROM operators WHERE username = :username",
            ['username' => $data['username']]
        );

        if ($existing) {
            return $this->db->update('operators', [
                'email' => $data['email'] ?? '',
                'password_hash' => $data['password_hash'] ?? '',
                'display_name' => $data['display_name'] ?? $data['username'],
                'is_active' => $data['active'] ?? true,
                'is_verified' => $data['verified'] ?? false,
                'online' => $data['online'] ?? false,
                'status' => $data['status'] ?? 'active',
                'metadata' => json_encode($data)
            ], 'id = :id', ['id' => $existing['id']]);
        } else {
            return $this->db->query(
                "INSERT INTO operators (username, email, password_hash, display_name, is_active, is_verified, online, status)
                 VALUES (:username, :email, :password_hash, :display_name, :is_active, :is_verified, :online, :status)",
                [
                    'username' => $data['username'],
                    'email' => $data['email'] ?? '',
                    'password_hash' => $data['password_hash'] ?? '',
                    'display_name' => $data['display_name'] ?? $data['username'],
                    'is_active' => $data['active'] ?? true,
                    'is_verified' => $data['verified'] ?? false,
                    'online' => $data['online'] ?? false,
                    'status' => $data['status'] ?? 'active'
                ]
            );
        }
    }


    private function searchOperatorsInDB($siteId, $filters) {
        $where = [];
        $params = [];

        if ($siteId) {
            $where[] = "EXISTS (SELECT 1 FROM operator_sites WHERE operator_id = operators.id AND site_id = :site_id)";
            $params['site_id'] = $siteId;
        }

        if (isset($filters['active'])) {
            $where[] = "is_active = :active";
            $params['active'] = $filters['active'];
        }

        if (isset($filters['online'])) {
            $where[] = "online = :online";
            $params['online'] = $filters['online'];
        }

        if (isset($filters['category'])) {
            $where[] = "category = :category";
            $params['category'] = $filters['category'];
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $sql = "SELECT * FROM operators $whereClause ORDER BY created_at DESC LIMIT 100";

        $results = $this->db->fetchAll($sql, $params);

        return array_map([$this, 'formatOperatorFromDB'], $results);
    }

    // =========================================================================
    // DATABASE OPERATIONS - MESSAGES
    // =========================================================================

    private function getMessagesFromDB($conversationId, $limit) {
        $sql = "SELECT * FROM messages
                WHERE conversation_id = :conversation_id
                ORDER BY sent_at DESC
                LIMIT :limit";

        return $this->db->fetchAll($sql, [
            'conversation_id' => $conversationId,
            'limit' => $limit
        ]);
    }

    private function saveMessageToDB($data) {
        return $this->db->query(
            "INSERT INTO messages (conversation_id, sender_type, sender_id, content, content_type, sent_at)
             VALUES (:conversation_id, :sender_type, :sender_id, :content, :content_type, :sent_at)",
            [
                'conversation_id' => $data['conversation_id'],
                'sender_type' => $data['sender_type'],
                'sender_id' => $data['sender_id'],
                'content' => $data['content'],
                'content_type' => $data['content_type'] ?? 'text',
                'sent_at' => $data['sent_at'] ?? date('Y-m-d H:i:s')
            ]
        );
    }

    // =========================================================================
    // DATABASE OPERATIONS - SITES
    // =========================================================================

    private function getAllSitesFromDB() {
        $results = $this->db->fetchAll("SELECT * FROM sites ORDER BY name");
        return array_map([$this, 'formatSiteFromDB'], $results);
    }

    private function formatSiteFromDB($row) {
        return [
            'site_id' => $row['site_id'],
            'domain' => $row['domain'],
            'name' => $row['name'],
            'description' => $row['description'],
            'template' => $row['template'],
            'active' => $row['active'],
            'categories' => json_decode($row['categories'] ?? '[]', true),
            'theme' => json_decode($row['theme'] ?? '{}', true),
            'features' => json_decode($row['features'] ?? '{}', true),
            'billing' => json_decode($row['billing_config'] ?? '{}', true),
        ];
    }

    // =========================================================================
    // FAVORITES OPERATIONS
    // =========================================================================

    public function getFavorites($customerId) {
        if ($this->useDatabase) {
            try {
                return $this->getFavoritesFromDB($customerId);
            } catch (Exception $e) {
                error_log("DataLayer: DB query failed, falling back to JSON: " . $e->getMessage());
            }
        }
        return $this->getFavoritesFromJSON($customerId);
    }

    public function addFavorite($customerId, $operatorId) {
        $jsonSuccess = $this->addFavoriteToJSON($customerId, $operatorId);

        if ($this->useDatabase && $this->dualWrite) {
            try {
                $this->addFavoriteToDB($customerId, $operatorId);
            } catch (Exception $e) {
                error_log("DataLayer: DB save failed: " . $e->getMessage());
            }
        }

        return $jsonSuccess;
    }

    public function removeFavorite($customerId, $operatorId) {
        $jsonSuccess = $this->removeFavoriteFromJSON($customerId, $operatorId);

        if ($this->useDatabase && $this->dualWrite) {
            try {
                $this->removeFavoriteFromDB($customerId, $operatorId);
            } catch (Exception $e) {
                error_log("DataLayer: DB delete failed: " . $e->getMessage());
            }
        }

        return $jsonSuccess;
    }

    private function getFavoritesFromJSON($customerId) {
        if (!file_exists($this->favoritesFile)) {
            return [];
        }

        $data = $this->security->safeJSONRead($this->favoritesFile);
        return $data[$customerId] ?? [];
    }

    private function addFavoriteToJSON($customerId, $operatorId) {
        $data = file_exists($this->favoritesFile) ?
                $this->security->safeJSONRead($this->favoritesFile) : [];

        if (!isset($data[$customerId])) {
            $data[$customerId] = [];
        }

        if (!in_array($operatorId, $data[$customerId])) {
            $data[$customerId][] = $operatorId;
        }

        return $this->security->safeJSONWrite($this->favoritesFile, $data);
    }

    private function removeFavoriteFromJSON($customerId, $operatorId) {
        if (!file_exists($this->favoritesFile)) {
            return true;
        }

        $data = $this->security->safeJSONRead($this->favoritesFile);

        if (isset($data[$customerId])) {
            $data[$customerId] = array_filter($data[$customerId], function($id) use ($operatorId) {
                return $id !== $operatorId;
            });
        }

        return $this->security->safeJSONWrite($this->favoritesFile, $data);
    }

    private function getFavoritesFromDB($customerId) {
        $sql = "SELECT operator_id FROM favorites WHERE customer_id = :customer_id";
        $results = $this->db->fetchAll($sql, ['customer_id' => $customerId]);
        return array_column($results, 'operator_id');
    }

    private function addFavoriteToDB($customerId, $operatorId) {
        return $this->db->query(
            "INSERT INTO favorites (customer_id, operator_id) VALUES (:customer_id, :operator_id) ON CONFLICT DO NOTHING",
            ['customer_id' => $customerId, 'operator_id' => $operatorId]
        );
    }

    private function removeFavoriteFromDB($customerId, $operatorId) {
        return $this->db->delete('favorites', 'customer_id = :cid AND operator_id = :oid', [
            'cid' => $customerId,
            'oid' => $operatorId
        ]);
    }

    // =========================================================================
    // CONTENT MARKETPLACE OPERATIONS
    // =========================================================================

    public function getContentItems($operatorId = null) {
        if ($this->useDatabase) {
            try {
                return $this->getContentItemsFromDB($operatorId);
            } catch (Exception $e) {
                error_log("DataLayer: DB query failed, falling back to JSON: " . $e->getMessage());
            }
        }
        return $this->getContentItemsFromJSON($operatorId);
    }

    public function saveContentItem($data) {
        $jsonSuccess = $this->saveContentItemToJSON($data);

        if ($this->useDatabase && $this->dualWrite) {
            try {
                $this->saveContentItemToDB($data);
            } catch (Exception $e) {
                error_log("DataLayer: DB save failed: " . $e->getMessage());
            }
        }

        return $jsonSuccess;
    }

    public function purchaseContent($customerId, $contentId, $amount) {
        $jsonSuccess = $this->purchaseContentInJSON($customerId, $contentId, $amount);

        if ($this->useDatabase && $this->dualWrite) {
            try {
                $this->purchaseContentInDB($customerId, $contentId, $amount);
            } catch (Exception $e) {
                error_log("DataLayer: DB save failed: " . $e->getMessage());
            }
        }

        return $jsonSuccess;
    }

    private function getContentItemsFromJSON($operatorId) {
        if (!file_exists($this->contentItemsFile)) {
            return [];
        }

        $data = $this->security->safeJSONRead($this->contentItemsFile);
        $items = $data['items'] ?? [];

        if ($operatorId) {
            return array_filter($items, function($item) use ($operatorId) {
                return ($item['operator_id'] ?? '') === $operatorId;
            });
        }

        return $items;
    }

    private function saveContentItemToJSON($data) {
        $fileData = file_exists($this->contentItemsFile) ?
                    $this->security->safeJSONRead($this->contentItemsFile) : ['items' => []];

        if (!isset($data['content_id'])) {
            $data['content_id'] = 'content_' . uniqid();
        }

        $fileData['items'][$data['content_id']] = $data;
        return $this->security->safeJSONWrite($this->contentItemsFile, $fileData);
    }

    private function purchaseContentInJSON($customerId, $contentId, $amount) {
        $purchasesFile = __DIR__ . '/../data/content_purchases.json';
        $purchases = file_exists($purchasesFile) ?
                     $this->security->safeJSONRead($purchasesFile) : ['purchases' => []];

        $purchaseId = 'purchase_' . uniqid();
        $purchases['purchases'][$purchaseId] = [
            'purchase_id' => $purchaseId,
            'customer_id' => $customerId,
            'content_id' => $contentId,
            'amount' => $amount,
            'purchased_at' => date('Y-m-d H:i:s')
        ];

        return $this->security->safeJSONWrite($purchasesFile, $purchases);
    }

    private function getContentItemsFromDB($operatorId) {
        $sql = "SELECT * FROM content_items WHERE (:operator_id IS NULL OR operator_id = :operator_id)";
        return $this->db->fetchAll($sql, ['operator_id' => $operatorId]);
    }

    private function saveContentItemToDB($data) {
        return $this->db->query(
            "INSERT INTO content_items (operator_id, title, description, content_type, price, file_url, thumbnail_url)
             VALUES (:operator_id, :title, :description, :content_type, :price, :file_url, :thumbnail_url)
             ON CONFLICT (content_id) DO UPDATE SET title = EXCLUDED.title, price = EXCLUDED.price",
            $data
        );
    }

    private function purchaseContentInDB($customerId, $contentId, $amount) {
        return $this->db->query(
            "INSERT INTO content_purchases (customer_id, content_id, amount) VALUES (:cid, :content_id, :amount)",
            ['cid' => $customerId, 'content_id' => $contentId, 'amount' => $amount]
        );
    }

    // =========================================================================
    // CHAT ROOM OPERATIONS
    // =========================================================================

    public function getChatRooms($siteId = null) {
        if ($this->useDatabase) {
            try {
                return $this->getChatRoomsFromDB($siteId);
            } catch (Exception $e) {
                error_log("DataLayer: DB query failed, falling back to JSON: " . $e->getMessage());
            }
        }
        return $this->getChatRoomsFromJSON($siteId);
    }

    public function saveChatRoom($data) {
        $jsonSuccess = $this->saveChatRoomToJSON($data);

        if ($this->useDatabase && $this->dualWrite) {
            try {
                $this->saveChatRoomToDB($data);
            } catch (Exception $e) {
                error_log("DataLayer: DB save failed: " . $e->getMessage());
            }
        }

        return $jsonSuccess;
    }

    private function getChatRoomsFromJSON($siteId) {
        if (!file_exists($this->chatRoomsFile)) {
            return [];
        }

        $data = $this->security->safeJSONRead($this->chatRoomsFile);
        $rooms = $data['rooms'] ?? [];

        if ($siteId) {
            return array_filter($rooms, function($room) use ($siteId) {
                return ($room['site_id'] ?? '') === $siteId;
            });
        }

        return $rooms;
    }

    private function saveChatRoomToJSON($data) {
        $fileData = file_exists($this->chatRoomsFile) ?
                    $this->security->safeJSONRead($this->chatRoomsFile) : ['rooms' => []];

        if (!isset($data['room_id'])) {
            $data['room_id'] = 'room_' . uniqid();
        }

        $fileData['rooms'][$data['room_id']] = $data;
        return $this->security->safeJSONWrite($this->chatRoomsFile, $fileData);
    }

    private function getChatRoomsFromDB($siteId) {
        $sql = "SELECT * FROM chat_rooms WHERE (:site_id IS NULL OR site_id = :site_id)";
        return $this->db->fetchAll($sql, ['site_id' => $siteId]);
    }

    private function saveChatRoomToDB($data) {
        return $this->db->query(
            "INSERT INTO chat_rooms (site_id, operator_id, name, description, max_participants, active)
             VALUES (:site_id, :operator_id, :name, :description, :max_participants, :active)
             ON CONFLICT (room_id) DO UPDATE SET name = EXCLUDED.name, active = EXCLUDED.active",
            $data
        );
    }

    // =========================================================================
    // NOTIFICATION OPERATIONS
    // =========================================================================

    public function getNotifications($userId, $userType = 'customer') {
        if ($this->useDatabase) {
            try {
                return $this->getNotificationsFromDB($userId, $userType);
            } catch (Exception $e) {
                error_log("DataLayer: DB query failed, falling back to JSON: " . $e->getMessage());
            }
        }
        return $this->getNotificationsFromJSON($userId, $userType);
    }

    public function createNotification($data) {
        $jsonSuccess = $this->createNotificationInJSON($data);

        if ($this->useDatabase && $this->dualWrite) {
            try {
                $this->createNotificationInDB($data);
            } catch (Exception $e) {
                error_log("DataLayer: DB save failed: " . $e->getMessage());
            }
        }

        return $jsonSuccess;
    }

    public function markNotificationRead($notificationId) {
        $jsonSuccess = $this->markNotificationReadInJSON($notificationId);

        if ($this->useDatabase && $this->dualWrite) {
            try {
                $this->markNotificationReadInDB($notificationId);
            } catch (Exception $e) {
                error_log("DataLayer: DB update failed: " . $e->getMessage());
            }
        }

        return $jsonSuccess;
    }

    private function getNotificationsFromJSON($userId, $userType) {
        $notificationsFile = __DIR__ . '/../data/notifications.json';
        if (!file_exists($notificationsFile)) {
            return [];
        }

        $data = $this->security->safeJSONRead($notificationsFile);
        $notifications = $data['notifications'] ?? [];

        return array_filter($notifications, function($notif) use ($userId, $userType) {
            return $notif['recipient_id'] === $userId && $notif['recipient_type'] === $userType;
        });
    }

    private function createNotificationInJSON($data) {
        $notificationsFile = __DIR__ . '/../data/notifications.json';
        $fileData = file_exists($notificationsFile) ?
                    $this->security->safeJSONRead($notificationsFile) : ['notifications' => []];

        if (!isset($data['notification_id'])) {
            $data['notification_id'] = 'notif_' . uniqid();
        }

        $fileData['notifications'][$data['notification_id']] = $data;
        return $this->security->safeJSONWrite($notificationsFile, $fileData);
    }

    private function markNotificationReadInJSON($notificationId) {
        $notificationsFile = __DIR__ . '/../data/notifications.json';
        if (!file_exists($notificationsFile)) {
            return false;
        }

        $data = $this->security->safeJSONRead($notificationsFile);

        if (isset($data['notifications'][$notificationId])) {
            $data['notifications'][$notificationId]['read_at'] = date('Y-m-d H:i:s');
            return $this->security->safeJSONWrite($notificationsFile, $data);
        }

        return false;
    }

    private function getNotificationsFromDB($userId, $userType) {
        $sql = "SELECT * FROM notifications WHERE recipient_id = :user_id AND recipient_type = :user_type ORDER BY created_at DESC";
        return $this->db->fetchAll($sql, ['user_id' => $userId, 'user_type' => $userType]);
    }

    private function createNotificationInDB($data) {
        return $this->db->query(
            "INSERT INTO notifications (recipient_id, recipient_type, message, notification_type, related_id)
             VALUES (:recipient_id, :recipient_type, :message, :notification_type, :related_id)",
            $data
        );
    }

    private function markNotificationReadInDB($notificationId) {
        return $this->db->update('notifications', ['read_at' => date('Y-m-d H:i:s')],
                                'notification_id = :id', ['id' => $notificationId]);
    }

    // =========================================================================
    // TRANSACTION OPERATIONS
    // =========================================================================

    public function getTransactions($userId, $userType = 'customer') {
        if ($this->useDatabase) {
            try {
                return $this->getTransactionsFromDB($userId, $userType);
            } catch (Exception $e) {
                error_log("DataLayer: DB query failed, falling back to JSON: " . $e->getMessage());
            }
        }
        return $this->getTransactionsFromJSON($userId, $userType);
    }

    public function recordTransaction($data) {
        $jsonSuccess = $this->recordTransactionInJSON($data);

        if ($this->useDatabase && $this->dualWrite) {
            try {
                $this->recordTransactionInDB($data);
            } catch (Exception $e) {
                error_log("DataLayer: DB save failed: " . $e->getMessage());
            }
        }

        return $jsonSuccess;
    }

    private function getTransactionsFromJSON($userId, $userType) {
        $transactionsFile = __DIR__ . '/../data/transactions.json';
        if (!file_exists($transactionsFile)) {
            return [];
        }

        $data = $this->security->safeJSONRead($transactionsFile);
        $transactions = $data['transactions'] ?? [];

        $key = $userType === 'customer' ? 'customer_id' : 'operator_id';
        return array_filter($transactions, function($txn) use ($userId, $key) {
            return ($txn[$key] ?? '') === $userId;
        });
    }

    private function recordTransactionInJSON($data) {
        $transactionsFile = __DIR__ . '/../data/transactions.json';
        $fileData = file_exists($transactionsFile) ?
                    $this->security->safeJSONRead($transactionsFile) : ['transactions' => []];

        if (!isset($data['transaction_id'])) {
            $data['transaction_id'] = 'txn_' . uniqid();
        }

        $fileData['transactions'][$data['transaction_id']] = $data;
        return $this->security->safeJSONWrite($transactionsFile, $fileData);
    }

    private function getTransactionsFromDB($userId, $userType) {
        $column = $userType === 'customer' ? 'customer_id' : 'operator_id';
        $sql = "SELECT * FROM transactions WHERE $column = :user_id ORDER BY created_at DESC";
        return $this->db->fetchAll($sql, ['user_id' => $userId]);
    }

    private function recordTransactionInDB($data) {
        return $this->db->query(
            "INSERT INTO transactions (customer_id, operator_id, amount, transaction_type, status, metadata)
             VALUES (:customer_id, :operator_id, :amount, :transaction_type, :status, :metadata::jsonb)",
            $data
        );
    }

    // =========================================================================
    // OPERATOR REQUEST OPERATIONS
    // =========================================================================

    public function getOperatorRequests($customerId) {
        if ($this->useDatabase) {
            try {
                return $this->getOperatorRequestsFromDB($customerId);
            } catch (Exception $e) {
                error_log("DataLayer: DB query failed, falling back to JSON: " . $e->getMessage());
            }
        }
        return $this->getOperatorRequestsFromJSON($customerId);
    }

    public function createOperatorRequest($data) {
        $jsonSuccess = $this->createOperatorRequestInJSON($data);

        if ($this->useDatabase && $this->dualWrite) {
            try {
                $this->createOperatorRequestInDB($data);
            } catch (Exception $e) {
                error_log("DataLayer: DB save failed: " . $e->getMessage());
            }
        }

        return $jsonSuccess;
    }

    private function getOperatorRequestsFromJSON($customerId) {
        if (!file_exists($this->operatorRequestsFile)) {
            return [];
        }

        $data = $this->security->safeJSONRead($this->operatorRequestsFile);
        $requests = $data['requests'] ?? [];

        return array_filter($requests, function($req) use ($customerId) {
            return ($req['customer_id'] ?? '') === $customerId;
        });
    }

    private function createOperatorRequestInJSON($data) {
        $fileData = file_exists($this->operatorRequestsFile) ?
                    $this->security->safeJSONRead($this->operatorRequestsFile) : ['requests' => []];

        if (!isset($data['request_id'])) {
            $data['request_id'] = 'req_' . uniqid();
        }

        $fileData['requests'][$data['request_id']] = $data;
        return $this->security->safeJSONWrite($this->operatorRequestsFile, $fileData);
    }

    private function getOperatorRequestsFromDB($customerId) {
        $sql = "SELECT * FROM operator_requests WHERE customer_id = :customer_id ORDER BY created_at DESC";
        return $this->db->fetchAll($sql, ['customer_id' => $customerId]);
    }

    private function createOperatorRequestInDB($data) {
        return $this->db->query(
            "INSERT INTO operator_requests (customer_id, operator_id, site_id, request_type, status, message)
             VALUES (:customer_id, :operator_id, :site_id, :request_type, :status, :message)",
            $data
        );
    }
}

// Global helper
function getDataLayer() {
    static $instance = null;
    if ($instance === null) {
        $instance = new DataLayer();
    }
    return $instance;
}
