<?php
/**
 * RDS Migration Runner
 * WARNING: Delete this file after migration completes!
 * Access via: https://aeims.app/run-migration.php?key=migrate2025
 */

// Security check
if (!isset($_GET['key']) || $_GET['key'] !== 'migrate2025') {
    http_response_code(403);
    die('Unauthorized');
}

// Prevent timeout
set_time_limit(300); // 5 minutes
ini_set('memory_limit', '512M');

echo "=== AEIMS RDS Migration Runner ===\n\n";
echo "Starting migration at: " . date('Y-m-d H:i:s') . "\n\n";

// Load environment
require_once __DIR__ . '/load-env.php';
require_once __DIR__ . '/includes/DatabaseManager.php';

$db = DatabaseManager::getInstance();

// Check if database is enabled
if (!$db->isEnabled()) {
    die("ERROR: Database is not enabled (USE_DATABASE=false)\n");
}

// Check if database is available
if (!$db->isAvailable()) {
    die("ERROR: Database is not available (cannot connect)\n");
}

echo "✓ Database connection successful\n";
echo "  Host: " . getenv('DB_HOST') . "\n";
echo "  Database: " . getenv('DB_NAME') . "\n\n";

// Check if tables already exist
try {
    $result = $db->fetchOne("SELECT COUNT(*) as count FROM customers");
    $customerCount = $result['count'] ?? 0;

    echo "Current data in database:\n";
    echo "  Customers: $customerCount\n";

    if ($customerCount > 0) {
        echo "\n⚠ Database already has data. Skipping migration.\n";
        echo "If you want to re-run migration, truncate tables first.\n";
        exit(0);
    }
} catch (Exception $e) {
    echo "Note: Tables don't exist yet or error checking: " . $e->getMessage() . "\n";
    echo "Will create schema and migrate data...\n\n";
}

// Run the migration
echo "=== Running Migration Script ===\n\n";

try {
    // Include and run the migration
    ob_start();
    require_once __DIR__ . '/database/migrate-json-to-postgres.php';
    $migrationOutput = ob_get_clean();

    echo $migrationOutput;
    echo "\n\n";

    // Verify migration
    $customerCount = $db->fetchOne("SELECT COUNT(*) as count FROM customers")['count'];
    $operatorCount = $db->fetchOne("SELECT COUNT(*) as count FROM operators")['count'];
    $siteCount = $db->fetchOne("SELECT COUNT(*) as count FROM sites")['count'];

    echo "=== Migration Complete ===\n";
    echo "  Customers: $customerCount\n";
    echo "  Operators: $operatorCount\n";
    echo "  Sites: $siteCount\n\n";

    echo "✓ Migration successful!\n";
    echo "Completed at: " . date('Y-m-d H:i:s') . "\n\n";

    echo "⚠ IMPORTANT: Delete /run-migration.php immediately for security!\n";

} catch (Exception $e) {
    echo "\n✗ Migration FAILED\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    http_response_code(500);
}
