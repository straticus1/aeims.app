<?php
/**
 * Check operators table schema and data
 */

require_once __DIR__ . '/includes/DatabaseManager.php';

$db = DatabaseManager::getInstance();

if (!$db->isEnabled() || !$db->isAvailable()) {
    die("❌ Database not available\n");
}

echo "=== OPERATORS TABLE SCHEMA ===\n\n";

// Get column info
try {
    $columns = $db->fetchAll("
        SELECT column_name, data_type, is_nullable, column_default
        FROM information_schema.columns
        WHERE table_name = 'operators'
        ORDER BY ordinal_position
    ");

    foreach ($columns as $col) {
        echo sprintf("%-20s %-15s %s %s\n",
            $col['column_name'],
            $col['data_type'],
            $col['is_nullable'] === 'YES' ? 'NULL' : 'NOT NULL',
            $col['column_default'] ? "DEFAULT {$col['column_default']}" : ''
        );
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== CURRENT OPERATORS ===\n\n";

try {
    $operators = $db->fetchAll("SELECT id, username, email, display_name, domains, services, metadata FROM operators");

    foreach ($operators as $op) {
        echo "ID: {$op['id']}\n";
        echo "Email: {$op['email']}\n";
        echo "Name: {$op['display_name']}\n";
        echo "Domains: " . ($op['domains'] ?? 'NULL') . "\n";
        echo "Services: " . ($op['services'] ?? 'NULL') . "\n";
        echo "Metadata: " . ($op['metadata'] ?? 'NULL') . "\n";
        echo "---\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
