#!/usr/bin/env php
<?php
/**
 * Populate Admin Account into PostgreSQL Database
 * Run this via: https://aeims.app/populate-admin.php?key=populate2025
 */

if (php_sapi_name() !== 'cli') {
    // Web access - require key
    if (!isset($_GET['key']) || $_GET['key'] !== 'populate2025') {
        http_response_code(401);
        die('Unauthorized');
    }
}

require_once __DIR__ . '/includes/DatabaseManager.php';

echo "=== AEIMS Admin Account Population Script ===\n\n";

$db = DatabaseManager::getInstance();

if (!$db->isEnabled()) {
    die("❌ Database not enabled. Set USE_DATABASE=true\n");
}

if (!$db->isAvailable()) {
    die("❌ Database not available. Check connection settings.\n");
}

echo "✅ Connected to database\n\n";

// Create admins table if it doesn't exist
try {
    $db->execute("
        CREATE TABLE IF NOT EXISTS admins (
            admin_id VARCHAR(50) PRIMARY KEY,
            username VARCHAR(255) UNIQUE NOT NULL,
            email VARCHAR(255) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            name VARCHAR(255),
            role VARCHAR(50) DEFAULT 'admin',
            permissions TEXT[],
            active BOOLEAN DEFAULT true,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "✅ Admins table ready\n\n";
} catch (Exception $e) {
    die("❌ Error creating table: " . $e->getMessage() . "\n");
}

// Insert admin account
$admin = [
    'admin_id' => 'admin_001',
    'username' => 'admin',
    'email' => 'admin@aeims.app',
    'password_hash' => password_hash('admin123', PASSWORD_DEFAULT),
    'name' => 'System Administrator',
    'role' => 'admin',
    'permissions' => '{all}',
    'active' => true,
];

$sql = "
    INSERT INTO admins (
        admin_id, username, email, password_hash, name, role, permissions, active, created_at
    ) VALUES (
        :admin_id, :username, :email, :password_hash, :name, :role, :permissions::text[], :active, CURRENT_TIMESTAMP
    )
    ON CONFLICT (username) DO UPDATE SET
        password_hash = EXCLUDED.password_hash,
        name = EXCLUDED.name,
        role = EXCLUDED.role,
        permissions = EXCLUDED.permissions,
        active = EXCLUDED.active,
        updated_at = CURRENT_TIMESTAMP
";

try {
    // Check if exists
    $existing = $db->fetchOne(
        "SELECT admin_id FROM admins WHERE username = :username",
        ['username' => $admin['username']]
    );

    $db->execute($sql, $admin);

    if ($existing) {
        echo "✅ Updated: {$admin['name']} ({$admin['username']})\n";
    } else {
        echo "✅ Added: {$admin['name']} ({$admin['username']})\n";
    }
} catch (Exception $e) {
    die("❌ Error: " . $e->getMessage() . "\n");
}

echo "\n✅ Admin account population complete!\n";
echo "\nTest login at: https://aeims.app/login.php\n";
echo "Credentials: admin / admin123\n";
