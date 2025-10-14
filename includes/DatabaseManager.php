<?php
/**
 * AEIMS Database Manager
 * Secure PDO wrapper with connection pooling, prepared statements, and transaction support
 *
 * FIXES:
 * - All database connectivity issues
 * - SQL injection prevention with prepared statements
 * - Proper error handling
 * - Automatic reconnection
 */

class DatabaseManager {
    private static $instance = null;
    private $connection = null;
    private $config;
    private $inTransaction = false;

    private function __construct() {
        $this->config = include __DIR__ . '/../config.php';
        $this->connect();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Establish database connection
     */
    private function connect() {
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
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Unable to connect to database");
        }
    }

    /**
     * Get database connection (with auto-reconnect)
     */
    public function getConnection() {
        try {
            // Test connection
            if ($this->connection === null || !$this->isConnected()) {
                $this->connect();
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
            throw new Exception("Database query failed");
        }
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
     * Insert record and return ID
     */
    public function insert($table, $data) {
        $columns = array_keys($data);
        $placeholders = array_map(function($col) { return ":$col"; }, $columns);

        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s) RETURNING id",
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $stmt = $this->query($sql, $data);
        $result = $stmt->fetch();
        return $result['id'] ?? null;
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
     * Health check
     */
    public function healthCheck() {
        try {
            $result = $this->fetchOne("SELECT version() as version, current_database() as database");
            return [
                'status' => 'healthy',
                'database' => $result['database'] ?? 'unknown',
                'version' => $result['version'] ?? 'unknown',
                'tables' => $this->tableExists('aeims_app_users')
            ];
        } catch (Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage()
            ];
        }
    }
}

// Global helper
function getDB() {
    return DatabaseManager::getInstance();
}
