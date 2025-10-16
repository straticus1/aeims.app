<?php
/**
 * Telephony Migrations Runner
 * Run migrations 004 and 005 for telephony integration
 *
 * Access: https://aeims.app/run-telephony-migrations.php?key=TELEPHONY_MIGRATE_2025
 */

// Security check
$migrationKey = $_GET['key'] ?? '';
if ($migrationKey !== 'TELEPHONY_MIGRATE_2025') {
    http_response_code(403);
    die('Unauthorized');
}

require_once __DIR__ . '/includes/DatabaseManager.php';

$db = DatabaseManager::getInstance();

echo "<h1>Telephony Integration Migrations</h1>\n";
echo "<pre>\n";

// Migration 004: Core Telephony Tables
echo "=== Running Migration 004: Create Telephony Tables ===\n\n";

$migration004 = file_get_contents(__DIR__ . '/database/migrations/004-create-telephony-tables.sql');

try {
    $db->execute($migration004);
    echo "✅ Migration 004 completed successfully\n\n";
} catch (Exception $e) {
    echo "❌ Migration 004 failed: " . $e->getMessage() . "\n\n";
    echo "Continuing to migration 005...\n\n";
}

// Migration 005: Free Minutes Support
echo "=== Running Migration 005: Add Free Minutes Support ===\n\n";

$migration005 = file_get_contents(__DIR__ . '/database/migrations/005-add-free-minutes-support.sql');

try {
    $db->execute($migration005);
    echo "✅ Migration 005 completed successfully\n\n";
} catch (Exception $e) {
    echo "❌ Migration 005 failed: " . $e->getMessage() . "\n\n";
}

// Verify tables were created
echo "=== Verifying Tables ===\n\n";

$tables = [
    'calls',
    'transactions',
    'messages',
    'customer_free_minutes',
    'operator_customer_interactions'
];

foreach ($tables as $table) {
    $exists = $db->fetchOne("
        SELECT EXISTS (
            SELECT FROM information_schema.tables
            WHERE table_name = :table
        ) as exists
    ", ['table' => $table]);

    if ($exists['exists']) {
        echo "✅ Table '$table' exists\n";
    } else {
        echo "❌ Table '$table' missing\n";
    }
}

echo "\n=== Migration Complete ===\n";
echo "All telephony tables and functions have been created.\n";
echo "You can now test call functionality.\n";
echo "</pre>\n";
