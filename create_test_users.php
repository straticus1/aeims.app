<?php
/**
 * Test User Creation Script
 * Creates test users for each user type for development testing
 *
 * @author Ryan Coleman <coleman.ryan@gmail.com>
 */

// Simple database setup for testing
try {
    $pdo = new PDO('sqlite:test_users.db');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create tables if they don't exist
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT UNIQUE,
            password TEXT,
            display_name TEXT,
            user_type TEXT,
            phone TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ');

    $pdo->exec('
        CREATE TABLE IF NOT EXISTS user_site_permissions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            site_domain TEXT,
            permission_level TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )
    ');

} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Test users configuration
$test_users = [
    [
        'email' => 'admin@test.local',
        'password' => 'admin123',
        'display_name' => 'Test Admin',
        'user_type' => 'admin',
        'sites' => ['nycflirts.com', 'flirts.nyc'],
        'phone' => '+1-555-0001'
    ],
    [
        'email' => 'operator@test.local',
        'password' => 'operator123',
        'display_name' => 'Test Operator',
        'user_type' => 'operator',
        'sites' => ['nycflirts.com'],
        'phone' => '+1-555-0002'
    ],
    [
        'email' => 'customer@test.local',
        'password' => 'customer123',
        'display_name' => 'Test Customer',
        'user_type' => 'customer',
        'sites' => ['nycflirts.com'],
        'phone' => '+1-555-0003'
    ],
    [
        'email' => 'reseller@test.local',
        'password' => 'reseller123',
        'display_name' => 'Test Reseller',
        'user_type' => 'reseller',
        'sites' => ['nycflirts.com', 'flirts.nyc'],
        'phone' => '+1-555-0004'
    ]
];

echo "Creating test users...\n";

foreach ($test_users as $user) {
    // Check if user already exists
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$user['email']]);
    $existing_user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing_user) {
        echo "User {$user['email']} already exists - updating...\n";

        // Update existing user
        $stmt = $pdo->prepare("
            UPDATE users
            SET password = ?, display_name = ?, user_type = ?, phone = ?, updated_at = CURRENT_TIMESTAMP
            WHERE email = ?
        ");
        $stmt->execute([
            password_hash($user['password'], PASSWORD_DEFAULT),
            $user['display_name'],
            $user['user_type'],
            $user['phone'],
            $user['email']
        ]);

        $user_id = $existing_user['id'];
    } else {
        echo "Creating new user {$user['email']}...\n";

        // Create new user
        $stmt = $pdo->prepare("
            INSERT INTO users (email, password, display_name, user_type, phone, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([
            $user['email'],
            password_hash($user['password'], PASSWORD_DEFAULT),
            $user['display_name'],
            $user['user_type'],
            $user['phone']
        ]);

        $user_id = $pdo->lastInsertId();
    }

    // Clear existing site permissions
    $stmt = $pdo->prepare("DELETE FROM user_site_permissions WHERE user_id = ?");
    $stmt->execute([$user_id]);

    // Add site permissions
    foreach ($user['sites'] as $site) {
        $stmt = $pdo->prepare("
            INSERT INTO user_site_permissions (user_id, site_domain, permission_level, created_at)
            VALUES (?, ?, 'full', CURRENT_TIMESTAMP)
        ");
        $stmt->execute([$user_id, $site]);
        echo "  - Added {$site} permission\n";
    }

    echo "✅ User {$user['email']} created/updated successfully\n\n";
}

echo "🎉 All test users created successfully!\n\n";
echo "Test Login Credentials:\n";
echo "=====================\n";
foreach ($test_users as $user) {
    echo sprintf("%-10s | %s | %s\n",
        strtoupper($user['user_type']),
        $user['email'],
        $user['password']
    );
}
echo "\n";
?>