<?php
/**
 * Emergency Operator Population Script
 * Access: https://aeims.app/x-populate-ops.php
 */

// Security: require key parameter
if (!isset($_GET['k']) || $_GET['k'] !== 'pop2025') {
    http_response_code(401);
    die('Unauthorized');
}

header('Content-Type: text/plain; charset=utf-8');

echo "=== Emergency Operator Population ===\n\n";

require_once __DIR__ . '/includes/DatabaseManager.php';

$db = DatabaseManager::getInstance();

if (!$db->isEnabled() || !$db->isAvailable()) {
    die("❌ Database not available\n");
}

echo "✅ Connected to database\n\n";

// Check existing operators
try {
    $existing = $db->fetchAll("SELECT email, username FROM operators WHERE email LIKE '%example.com'");
    echo "Existing test operators: " . count($existing) . "\n";
    foreach ($existing as $op) {
        echo "  - {$op['email']}\n";
    }
    echo "\n";
} catch (Exception $e) {
    echo "Error checking existing: " . $e->getMessage() . "\n\n";
}

// Ensure columns exist
echo "Adding columns if missing...\n";
try {
    $db->execute("ALTER TABLE operators ADD COLUMN IF NOT EXISTS password_hash VARCHAR(255)");
    $db->execute("ALTER TABLE operators ADD COLUMN IF NOT EXISTS phone VARCHAR(50)");
    echo "✅ Columns ready\n\n";
} catch (Exception $e) {
    echo "Column error (may be OK): " . $e->getMessage() . "\n\n";
}

// Insert/update operators
$operators = [
    ['sarah@example.com', 'Sarah Johnson', '$2y$12$uP769e4CWOCmgm7iXSsqoeH0Vqoi5lSmsPizDmSTmxOoyyTNuykMm', '+1-555-0101'],
    ['jessica@example.com', 'Jessica Williams', '$2y$12$Au9qvnvZrHNoa6IDYSFRlOr54j3WI9hZ264Hh9u3W5kWqZWUSGzgW', '+1-555-0102'],
    ['amanda@example.com', 'Amanda Rodriguez', '$2y$12$PAW1bUSBAJzneXOqUYtdYelR/w.6CKPVK9ScuyrfvBR3dr3A43xMa', '+1-555-0103'],
];

echo "Inserting/updating operators...\n";
foreach ($operators as list($email, $name, $hash, $phone)) {
    try {
        $db->execute("
            INSERT INTO operators (username, email, display_name, password_hash, phone, is_active, is_verified, created_at)
            VALUES (:username, :email, :name, :hash, :phone, true, true, CURRENT_TIMESTAMP)
            ON CONFLICT (email) DO UPDATE SET
                password_hash = EXCLUDED.password_hash,
                display_name = EXCLUDED.display_name,
                phone = EXCLUDED.phone,
                username = EXCLUDED.username,
                is_active = true,
                is_verified = true,
                updated_at = CURRENT_TIMESTAMP
        ", [
            'username' => $email,
            'email' => $email,
            'name' => $name,
            'hash' => $hash,
            'phone' => $phone,
        ]);
        echo "  ✅ $name ($email)\n";
    } catch (Exception $e) {
        echo "  ❌ $name: " . $e->getMessage() . "\n";
    }
}

echo "\n=== Verification ===\n";
try {
    $operators = $db->fetchAll("SELECT email, username, display_name, is_active, LENGTH(password_hash) as hash_len FROM operators WHERE email LIKE '%example.com' ORDER BY email");
    foreach ($operators as $op) {
        $status = $op['is_active'] ? '✅' : '❌';
        echo "$status {$op['email']} | {$op['display_name']} | hash_len: {$op['hash_len']}\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n✅ DONE!\n";
echo "Test login: https://aeims.app/agents/login.php\n";
echo "  sarah@example.com / demo123\n";
