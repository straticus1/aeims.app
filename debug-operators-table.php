#!/usr/bin/env php
<?php
/**
 * Debug: Check operators table structure
 */

if (php_sapi_name() !== 'cli') {
    if (!isset($_GET['key']) || $_GET['key'] !== 'debug2025') {
        http_response_code(401);
        die('Unauthorized');
    }
}

require_once __DIR__ . '/includes/DatabaseManager.php';

echo "=== Operators Table Debug ===\n\n";

$db = DatabaseManager::getInstance();

if (!$db->isEnabled() || !$db->isAvailable()) {
    die("❌ Database not available\n");
}

echo "✅ Connected to database\n\n";

// Check if operators table exists
try {
    $result = $db->fetchOne("
        SELECT EXISTS (
            SELECT FROM information_schema.tables
            WHERE table_schema = 'public'
            AND table_name = 'operators'
        )
    ");

    if ($result['exists']) {
        echo "✅ Operators table EXISTS\n\n";

        // Get table structure
        $columns = $db->fetchAll("
            SELECT column_name, data_type, character_maximum_length
            FROM information_schema.columns
            WHERE table_schema = 'public'
            AND table_name = 'operators'
            ORDER BY ordinal_position
        ");

        echo "Table structure:\n";
        foreach ($columns as $col) {
            $type = $col['data_type'];
            if ($col['character_maximum_length']) {
                $type .= "(" . $col['character_maximum_length'] . ")";
            }
            echo "  - " . $col['column_name'] . ": " . $type . "\n";
        }

        // Count rows
        $count = $db->fetchOne("SELECT COUNT(*) as count FROM operators");
        echo "\nRows in table: " . $count['count'] . "\n";

    } else {
        echo "❌ Operators table does NOT exist\n";
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
