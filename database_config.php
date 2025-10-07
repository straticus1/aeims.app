<?php
/**
 * AEIMS Database Configuration
 * PostgreSQL connection for integration with AEIMS Core
 */

// Database configuration
$db_config = [
    'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
    'port' => $_ENV['DB_PORT'] ?? '5432',
    'dbname' => $_ENV['DB_NAME'] ?? 'aeims_core',
    'username' => $_ENV['DB_USER'] ?? 'aeims_user',
    'password' => $_ENV['DB_PASS'] ?? 'secure_password_123'
];

/**
 * Get database connection
 * @return PDO
 * @throws PDOException
 */
function getDbConnection() {
    global $db_config;

    $dsn = "pgsql:host={$db_config['host']};port={$db_config['port']};dbname={$db_config['dbname']}";

    try {
        $pdo = new PDO($dsn, $db_config['username'], $db_config['password']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw new PDOException("Unable to connect to database");
    }
}

/**
 * Test database connection
 * @return bool
 */
function testDbConnection() {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->query("SELECT 1");
        return $stmt !== false;
    } catch (Exception $e) {
        error_log("Database connection test failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Initialize database schema if needed
 */
function initializeDatabase() {
    try {
        $pdo = getDbConnection();

        // Check if our tables exist
        $stmt = $pdo->prepare("
            SELECT EXISTS (
                SELECT FROM information_schema.tables
                WHERE table_schema = 'public'
                AND table_name = 'aeims_app_users'
            )
        ");
        $stmt->execute();
        $table_exists = $stmt->fetchColumn();

        if (!$table_exists) {
            echo "Initializing database schema...\n";
            $schema = file_get_contents(__DIR__ . '/database/schema_postgres.sql');
            $pdo->exec($schema);
            echo "Database schema initialized successfully.\n";
        }

        return true;
    } catch (Exception $e) {
        error_log("Database initialization failed: " . $e->getMessage());
        return false;
    }
}
?>