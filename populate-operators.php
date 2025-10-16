#!/usr/bin/env php
<?php
/**
 * Populate Operators into PostgreSQL Database
 * Run this via: https://aeims.app/populate-operators.php?key=populate2025
 */

if (php_sapi_name() !== 'cli') {
    // Web access - require key
    if (!isset($_GET['key']) || $_GET['key'] !== 'populate2025') {
        http_response_code(401);
        die('Unauthorized');
    }
}

require_once __DIR__ . '/includes/DatabaseManager.php';

echo "=== AEIMS Operator Population Script ===\n\n";

$db = DatabaseManager::getInstance();

if (!$db->isEnabled()) {
    die("❌ Database not enabled. Set USE_DATABASE=true\n");
}

if (!$db->isAvailable()) {
    die("❌ Database not available. Check connection settings.\n");
}

echo "✅ Connected to database\n\n";

// Check if password_hash column exists, add if missing
try {
    $columnExists = $db->fetchOne("
        SELECT EXISTS (
            SELECT FROM information_schema.columns
            WHERE table_name = 'operators'
            AND column_name = 'password_hash'
        )
    ");

    if (!$columnExists['exists']) {
        $db->execute("ALTER TABLE operators ADD COLUMN password_hash VARCHAR(255)");
        echo "✅ Added password_hash column\n";
    }

    // Check if phone column exists, add if missing
    $phoneExists = $db->fetchOne("
        SELECT EXISTS (
            SELECT FROM information_schema.columns
            WHERE table_name = 'operators'
            AND column_name = 'phone'
        )
    ");

    if (!$phoneExists['exists']) {
        $db->execute("ALTER TABLE operators ADD COLUMN phone VARCHAR(50)");
        echo "✅ Added phone column\n";
    }

    echo "✅ Operators table ready\n\n";
} catch (Exception $e) {
    die("❌ Error updating table: " . $e->getMessage() . "\n");
}

// Demo operators
$operators = [
    [
        'username' => 'sarah@example.com',
        'email' => 'sarah@example.com',
        'password_hash' => password_hash('demo123', PASSWORD_DEFAULT),
        'display_name' => 'Sarah Johnson',
        'phone' => '+1-555-0101',
        'status' => 'active',
        'is_active' => true,
        'is_verified' => true,
    ],
    [
        'username' => 'jessica@example.com',
        'email' => 'jessica@example.com',
        'password_hash' => password_hash('demo456', PASSWORD_DEFAULT),
        'display_name' => 'Jessica Williams',
        'phone' => '+1-555-0102',
        'status' => 'active',
        'is_active' => true,
        'is_verified' => true,
    ],
    [
        'username' => 'amanda@example.com',
        'email' => 'amanda@example.com',
        'password_hash' => password_hash('demo789', PASSWORD_DEFAULT),
        'display_name' => 'Amanda Rodriguez',
        'phone' => '+1-555-0103',
        'status' => 'active',
        'is_active' => true,
        'is_verified' => true,
    ],
];

$sql = "
    INSERT INTO operators (
        username, email, password_hash, display_name, phone,
        status, is_active, is_verified, created_at
    ) VALUES (
        :username, :email, :password_hash, :display_name, :phone,
        :status, :is_active, :is_verified, CURRENT_TIMESTAMP
    )
    ON CONFLICT (username) DO UPDATE SET
        password_hash = EXCLUDED.password_hash,
        display_name = EXCLUDED.display_name,
        phone = EXCLUDED.phone,
        status = EXCLUDED.status,
        is_active = EXCLUDED.is_active,
        is_verified = EXCLUDED.is_verified,
        updated_at = CURRENT_TIMESTAMP
";

$added = 0;
$updated = 0;

foreach ($operators as $operator) {
    try {
        // Check if exists
        $existing = $db->fetchOne(
            "SELECT id FROM operators WHERE username = :username",
            ['username' => $operator['username']]
        );

        $db->execute($sql, $operator);

        if ($existing) {
            $updated++;
            echo "✅ Updated: {$operator['display_name']} ({$operator['email']})\n";
        } else {
            $added++;
            echo "✅ Added: {$operator['display_name']} ({$operator['email']})\n";
        }
    } catch (Exception $e) {
        echo "❌ Error for {$operator['email']}: " . $e->getMessage() . "\n";
    }
}

echo "\n=== Summary ===\n";
echo "Added: $added\n";
echo "Updated: $updated\n";
echo "\n✅ Operator population complete!\n";
echo "\nTest login at: https://aeims.app/agents/login.php\n";
echo "Credentials: sarah@example.com / demo123\n";
