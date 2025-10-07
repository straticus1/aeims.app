<?php
/**
 * AEIMS Unified Database Configuration
 * PostgreSQL connection with fallback environment variables
 */

$db_config = [
    'host' => getenv('DATABASE_HOST') ?: getenv('DB_HOST') ?: getenv('POSTGRES_HOST') ?: 'aeims-postgres',
    'port' => getenv('DATABASE_PORT') ?: getenv('DB_PORT') ?: getenv('POSTGRES_PORT') ?: '5432',
    'dbname' => getenv('DATABASE_NAME') ?: getenv('DB_NAME') ?: getenv('POSTGRES_DB') ?: 'aeims_core',
    'username' => getenv('DATABASE_USER') ?: getenv('DB_USER') ?: getenv('POSTGRES_USER') ?: 'aeims_user',
    'password' => getenv('DATABASE_PASS') ?: getenv('DB_PASS') ?: getenv('POSTGRES_PASSWORD') ?: 'secure_password_123'
];

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
        throw new PDOException("Unable to connect to database: " . $e->getMessage());
    }
}
?>