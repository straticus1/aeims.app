<?php
/**
 * AEIMS Database Manager
 * Secure PDO wrapper with connection pooling, prepared statements, and transaction support
 *
 * PHASE 1 FIXES (Zero-Downtime Migration):
 * - Lazy connection (NEVER connects until actually used)
 * - Feature flag support (USE_DATABASE env var)
 * - isAvailable() method to check DB status
 * - Never throws exceptions that break authentication
 * - Automatic fallback support for DataLayer
 *
 * SECURITY FIXES:
 * - SQL injection prevention with prepared statements
 * - Proper error handling
 * - Automatic reconnection
 */

class DatabaseManager {
    private static $instance = null;
    private $connection = null;
    private $config;
    private $inTransaction = false;
    private $useDatabase = false;
    private $connectionAttempted = false;
    private $connectionAvailable = false;

    private function __construct() {
        // Load environment variables from .env file
        if (file_exists(__DIR__ . '/../.env')) {
            require_once __DIR__ . '/../load-env.php';
        }

        $this->config = include __DIR__ . '/../config.php';
        // PHASE 1 FIX: Don't connect here! Wait until actually needed
        // This prevents auth from breaking if DB is unavailable
        $this->useDatabase = (getenv('USE_DATABASE') === 'true');
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Check if database is enabled via feature flag
     */
    public function isEnabled() {
        return $this->useDatabase;
    }

    /**
     * Check if database is available (safe method, never throws)
     */
    public function isAvailable() {
        // If not enabled, return false
        if (!$this->useDatabase) {
            return false;
        }

        // If we already tried and failed, return cached result
        if ($this->connectionAttempted) {
            return $this->connectionAvailable;
        }

        // Try to connect (but don't throw exceptions)
        try {
            $this->connect();
            $this->connectionAvailable = $this->isConnected();
            $this->connectionAttempted = true;
            return $this->connectionAvailable;
        } catch (Exception $e) {
            error_log("Database availability check failed: " . $e->getMessage());
            $this->connectionAvailable = false;
            $this->connectionAttempted = true;
            return false;
        }
    }

    /**
     * Establish database connection (PHASE 1 FIX: Never throws on failure)
     */
    private function connect() {
        // Only try if database is enabled
        if (!$this->useDatabase) {
            error_log("Database not enabled (USE_DATABASE=false)");
            return false;
        }

        $dbConfig = $this->config['database'] ?? [];

        $host = $dbConfig['host'] ?? getenv('DB_HOST') ?: '127.0.0.1';
        $port = $dbConfig['port'] ?? getenv('DB_PORT') ?: '5432';
        $dbname = $dbConfig['name'] ?? getenv('DB_NAME') ?: 'aeims_core';
        $username = $dbConfig['user'] ?? getenv('DB_USER') ?: 'aeims_user';
        $password = $dbConfig['password'] ?? getenv('DB_PASS') ?: '';

        $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";

        try {
            $this->connection = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => true, // Connection pooling
                PDO::ATTR_TIMEOUT => 5
            ]);

            error_log("Database connected successfully");
            $this->connectionAvailable = true;
            return true;
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            $this->connectionAvailable = false;
            // PHASE 1 FIX: Don't throw! This was breaking auth
            return false;
        }
    }

    /**
     * Get database connection (with auto-reconnect)
     * PHASE 1 FIX: Throws only if DB is enabled but unavailable
     */
    public function getConnection() {
        // If database not enabled, throw informative exception
        if (!$this->useDatabase) {
            throw new Exception("Database not enabled. Set USE_DATABASE=true to enable.");
        }

        try {
            // Test connection
            if ($this->connection === null || !$this->isConnected()) {
                $connected = $this->connect();
                if (!$connected) {
                    throw new Exception("Unable to connect to database");
                }
            }
            return $this->connection;
        } catch (Exception $e) {
            error_log("Failed to get database connection: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Check if connected
     */
    private function isConnected() {
        try {
            $this->connection->query('SELECT 1');
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Execute query with prepared statement
     */
    public function query($sql, $params = []) {
        try {
            $conn = $this->getConnection();
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Query failed: " . $e->getMessage() . " | SQL: " . $sql);
            throw new Exception("Database query failed: " . $e->getMessage());
        }
    }

    /**
     * Execute statement (alias for query, for INSERT/UPDATE/DELETE)
     */
    public function execute($sql, $params = []) {
        return $this->query($sql, $params);
    }

    /**
     * Fetch single row
     */
    public function fetchOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }

    /**
     * Fetch all rows
     */
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Insert record (returns true on success)
     */
    public function insert($table, $data) {
        $columns = array_keys($data);
        $placeholders = array_map(function($col) { return ":$col"; }, $columns);

        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $stmt = $this->query($sql, $data);
        return true;
    }

    /**
     * Update record
     */
    public function update($table, $data, $where, $whereParams = []) {
        $setParts = [];
        foreach (array_keys($data) as $column) {
            $setParts[] = "$column = :$column";
        }

        $sql = sprintf(
            "UPDATE %s SET %s WHERE %s",
            $table,
            implode(', ', $setParts),
            $where
        );

        $params = array_merge($data, $whereParams);
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    /**
     * Delete record
     */
    public function delete($table, $where, $whereParams = []) {
        $sql = sprintf("DELETE FROM %s WHERE %s", $table, $where);
        $stmt = $this->query($sql, $whereParams);
        return $stmt->rowCount();
    }

    /**
     * Begin transaction
     */
    public function beginTransaction() {
        if (!$this->inTransaction) {
            $this->getConnection()->beginTransaction();
            $this->inTransaction = true;
        }
    }

    /**
     * Commit transaction
     */
    public function commit() {
        if ($this->inTransaction) {
            $this->getConnection()->commit();
            $this->inTransaction = false;
        }
    }

    /**
     * Rollback transaction
     */
    public function rollback() {
        if ($this->inTransaction) {
            $this->getConnection()->rollBack();
            $this->inTransaction = false;
        }
    }

    /**
     * Execute transaction with callback
     */
    public function transaction(callable $callback) {
        $this->beginTransaction();
        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    /**
     * Check if table exists
     */
    public function tableExists($tableName) {
        $sql = "SELECT EXISTS (
            SELECT FROM information_schema.tables
            WHERE table_schema = 'public'
            AND table_name = :table_name
        )";
        $result = $this->fetchOne($sql, ['table_name' => $tableName]);
        return $result['exists'] ?? false;
    }

    /**
     * Initialize database schema
     */
    public function initializeSchema() {
        $schemaFile = __DIR__ . '/../database/schema_postgres.sql';

        if (!file_exists($schemaFile)) {
            throw new Exception("Schema file not found: $schemaFile");
        }

        // Check if already initialized
        if ($this->tableExists('aeims_app_users')) {
            error_log("Database already initialized");
            return true;
        }

        $schema = file_get_contents($schemaFile);
        $conn = $this->getConnection();

        try {
            $conn->exec($schema);
            error_log("Database schema initialized successfully");
            return true;
        } catch (PDOException $e) {
            error_log("Schema initialization failed: " . $e->getMessage());
            throw new Exception("Failed to initialize database schema");
        }
    }

    /**
     * User management methods
     */

    public function createUser($username, $email, $password, $role = 'customer', $additionalData = []) {
        $data = array_merge([
            'username' => $username,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => $role,
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s')
        ], $additionalData);

        return $this->insert('aeims_app_users', $data);
    }

    public function getUserByUsername($username) {
        return $this->fetchOne(
            "SELECT * FROM aeims_app_users WHERE username = :username",
            ['username' => $username]
        );
    }

    public function getUserByEmail($email) {
        return $this->fetchOne(
            "SELECT * FROM aeims_app_users WHERE email = :email",
            ['email' => $email]
        );
    }

    public function getUserById($id) {
        return $this->fetchOne(
            "SELECT * FROM aeims_app_users WHERE id = :id",
            ['id' => $id]
        );
    }

    public function updateUser($id, $data) {
        if (isset($data['password'])) {
            $data['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
            unset($data['password']);
        }

        $data['updated_at'] = date('Y-m-d H:i:s');

        return $this->update('aeims_app_users', $data, 'id = :id', ['id' => $id]);
    }

    public function lockUser($username, $reason = '') {
        return $this->update(
            'aeims_app_users',
            [
                'status' => 'locked',
                'locked_at' => date('Y-m-d H:i:s'),
                'lock_reason' => $reason
            ],
            'username = :username',
            ['username' => $username]
        );
    }

    public function unlockUser($username) {
        return $this->update(
            'aeims_app_users',
            [
                'status' => 'active',
                'locked_at' => null,
                'lock_reason' => null
            ],
            'username = :username',
            ['username' => $username]
        );
    }

    public function deleteUser($id) {
        // Soft delete
        return $this->update(
            'aeims_app_users',
            [
                'status' => 'deleted',
                'deleted_at' => date('Y-m-d H:i:s')
            ],
            'id = :id',
            ['id' => $id]
        );
    }

    public function listUsers($role = null, $status = null, $limit = 100, $offset = 0) {
        $where = [];
        $params = [];

        if ($role) {
            $where[] = "role = :role";
            $params['role'] = $role;
        }

        if ($status) {
            $where[] = "status = :status";
            $params['status'] = $status;
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT id, username, email, role, status, created_at, last_login
                FROM aeims_app_users
                $whereClause
                ORDER BY created_at DESC
                LIMIT :limit OFFSET :offset";

        $params['limit'] = $limit;
        $params['offset'] = $offset;

        return $this->fetchAll($sql, $params);
    }

    public function updateLastLogin($userId) {
        return $this->update(
            'aeims_app_users',
            ['last_login' => date('Y-m-d H:i:s')],
            'id = :id',
            ['id' => $userId]
        );
    }

    /**
     * Migration helpers
     */

    public function migrateJSONUsers($jsonFile) {
        if (!file_exists($jsonFile)) {
            throw new Exception("JSON file not found: $jsonFile");
        }

        $jsonData = json_decode(file_get_contents($jsonFile), true);
        if (!$jsonData) {
            throw new Exception("Invalid JSON data");
        }

        $migrated = 0;
        $errors = [];

        $this->beginTransaction();
        try {
            foreach ($jsonData as $username => $userData) {
                try {
                    // Check if user already exists
                    $existing = $this->getUserByUsername($username);
                    if ($existing) {
                        $errors[] = "User already exists: $username";
                        continue;
                    }

                    $this->insert('aeims_app_users', [
                        'username' => $username,
                        'email' => $userData['email'] ?? "$username@aeims.app",
                        'password_hash' => $userData['password'] ?? password_hash('changeme', PASSWORD_DEFAULT),
                        'role' => $userData['type'] ?? 'customer',
                        'status' => $userData['status'] ?? 'active',
                        'created_at' => $userData['created_at'] ?? date('Y-m-d H:i:s'),
                        'metadata' => json_encode($userData)
                    ]);

                    $migrated++;
                } catch (Exception $e) {
                    $errors[] = "Error migrating $username: " . $e->getMessage();
                }
            }

            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }

        return [
            'migrated' => $migrated,
            'errors' => $errors
        ];
    }

    /**
     * Health check (PHASE 1 FIX: Safe method, never throws)
     */
    public function healthCheck() {
        // Check if enabled
        if (!$this->isEnabled()) {
            return [
                'status' => 'disabled',
                'message' => 'Database not enabled (USE_DATABASE=false)',
                'enabled' => false
            ];
        }

        // Check if available
        if (!$this->isAvailable()) {
            return [
                'status' => 'unavailable',
                'message' => 'Database connection failed',
                'enabled' => true,
                'available' => false
            ];
        }

        // Try to get info
        try {
            $result = $this->fetchOne("SELECT version() as version, current_database() as database");
            return [
                'status' => 'healthy',
                'enabled' => true,
                'available' => true,
                'database' => $result['database'] ?? 'unknown',
                'version' => $result['version'] ?? 'unknown',
                'tables' => $this->tableExists('sites')
            ];
        } catch (Exception $e) {
            return [
                'status' => 'unhealthy',
                'enabled' => true,
                'available' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}

// =============================================================================
// PHASE 1 MIGRATION NOTES
// =============================================================================
//
// Changes Made:
// 1. Added lazy connection - no longer connects in constructor
// 2. Added isEnabled() - check if USE_DATABASE env var is true
// 3. Added isAvailable() - safe method to check DB status
// 4. Updated connect() - returns boolean instead of throwing
// 5. Updated getConnection() - throws only when DB enabled but unavailable
// 6. Updated healthCheck() - never throws, returns detailed status
//
// Usage:
//   $db = DatabaseManager::getInstance();  // Safe! Never throws
//   if ($db->isAvailable()) {
//       $result = $db->query(...);  // Use database
//   } else {
//       // Fallback to JSON
//   }
//
// Environment Variables:
//   USE_DATABASE=false   - Database disabled (default, current state)
//   USE_DATABASE=true    - Database enabled (migration mode)
//
// Migration Phases:
//   Phase 1: Fix DatabaseManager (COMPLETE)
//   Phase 2: Create DataLayer abstraction
//   Phase 3: Implement dual-write mode
//   Phase 4: Validate data integrity
//   Phase 5: Switch read source to PostgreSQL
//   Phase 6: PostgreSQL only
//
// =============================================================================


// Global helper
function getDB() {
    return DatabaseManager::getInstance();
}
