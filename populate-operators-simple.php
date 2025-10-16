#!/usr/bin/env php
<?php
/**
 * Simple Operator Population (Direct SQL)
 */

if (php_sapi_name() !== 'cli') {
    if (!isset($_GET['key']) || $_GET['key'] !== 'populate2025') {
        http_response_code(401);
        die('Unauthorized');
    }
}

require_once __DIR__ . '/includes/DatabaseManager.php';

echo "=== Simple Operator Population ===\n\n";

$db = DatabaseManager::getInstance();

if (!$db->isEnabled() || !$db->isAvailable()) {
    die("❌ Database not available\n");
}

echo "✅ Connected\n\n";

// Ensure password_hash column exists
try {
    $db->execute("ALTER TABLE operators ADD COLUMN IF NOT EXISTS password_hash VARCHAR(255)");
    $db->execute("ALTER TABLE operators ADD COLUMN IF NOT EXISTS phone VARCHAR(50)");
    echo "✅ Columns ready\n\n";
} catch (Exception $e) {
    // Columns might already exist, that's fine
}

// Direct insert with ON CONFLICT
$operators = [
    ['sarah@example.com', 'Sarah Johnson', password_hash('demo123', PASSWORD_DEFAULT), '+1-555-0101'],
    ['jessica@example.com', 'Jessica Williams', password_hash('demo456', PASSWORD_DEFAULT), '+1-555-0102'],
    ['amanda@example.com', 'Amanda Rodriguez', password_hash('demo789', PASSWORD_DEFAULT), '+1-555-0103'],
];

foreach ($operators as list($email, $name, $hash, $phone)) {
    try {
        $db->execute("
            INSERT INTO operators (username, email, display_name, password_hash, phone, status, is_active, is_verified, created_at)
            VALUES (:username, :email, :name, :hash, :phone, 'active', true, true, CURRENT_TIMESTAMP)
            ON CONFLICT (email) DO UPDATE SET
                password_hash = EXCLUDED.password_hash,
                display_name = EXCLUDED.display_name,
                phone = EXCLUDED.phone,
                updated_at = CURRENT_TIMESTAMP
        ", [
            'username' => $email,
            'email' => $email,
            'name' => $name,
            'hash' => $hash,
            'phone' => $phone,
        ]);
        echo "✅ {$name}\n";
    } catch (Exception $e) {
        echo "❌ {$name}: " . $e->getMessage() . "\n";
    }
}

echo "\n✅ Done!\n";
echo "Test: https://aeims.app/agents/login.php\n";
echo "Login: sarah@example.com / demo123\n";
