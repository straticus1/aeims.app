#!/usr/bin/env php
<?php
// CLI Setup Script - No web server needed
echo "AEIMS Platform Setup\n";
echo "===================\n\n";

try {
    $pdo = new PDO("pgsql:host=nitetext-db.c3iuy64is41m.us-east-1.rds.amazonaws.com;port=5432;dbname=aeims_core", 'nitetext', 'NiteText2025!SecureProd');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✓ Connected to database\n\n";

    // Password hashes
    $demo = '$2y$12$sG16f6iwygYiNqhyZYZ.1.MdbRB9h/39zI/FYxSI4XMHJH.vjVDOu'; // demo123
    $admin = '$2y$12$NDsiUQDsTboMnFs.BDikx.wzq9Q0eq2YCZUl7KEE4hiNOS1c4XRBK'; // admin123

    // Fix operator passwords
    echo "Fixing operator passwords...\n";
    $ops = [
        ['sarah@example.com', $demo], ['jessica@example.com', $demo],
        ['amanda@example.com', $demo], ['admin@aeims.app', $admin]
    ];
    foreach ($ops as list($email, $hash)) {
        $pdo->prepare("UPDATE users SET password_hash = ?, password = ?, status = 'active', is_active = true, updated_at = NOW() WHERE email = ?")->execute([$hash, $hash, $email]);
        echo "  ✓ Fixed: $email\n";
    }

    // Set operators online
    echo "\nSetting operators online...\n";
    $pdo->exec("UPDATE operators SET online_status = 'online', is_available = true, availability = 'available', updated_at = NOW() WHERE email IN ('sarah@example.com', 'jessica@example.com', 'amanda@example.com')");
    echo "  ✓ Done\n";

    // Create test customers
    echo "\nCreating test customers...\n";
    $customers = [
        ['testuser1', 'test1@example.com', 50.00],
        ['testuser2', 'test2@example.com', 100.00],
        ['testuser3', 'test3@example.com', 75.00]
    ];
    foreach ($customers as list($user, $email, $bal)) {
        $hash = password_hash('test123', PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt = $pdo->prepare("INSERT INTO customers (username, email, password_hash, display_name, status, email_verified, balance, account_type, created_at, updated_at) VALUES (?, ?, ?, ?, 'active', true, ?, 'premium', NOW(), NOW()) ON CONFLICT (email) DO UPDATE SET password_hash = EXCLUDED.password_hash, balance = EXCLUDED.balance, status = 'active', updated_at = NOW() RETURNING id");
        $stmt->execute([$user, $email, $hash, $user, $bal]);
        $id = $stmt->fetchColumn();
        $pdo->prepare("INSERT INTO site_customers (site_domain, customer_id, is_primary_site, is_active, created_at) VALUES ('flirts.nyc', ?, true, true, NOW()) ON CONFLICT (site_domain, customer_id) DO UPDATE SET is_active = true")->execute([$id]);
        echo "  ✓ Created: $user ($email) with \$$bal\n";
    }

    echo "\n✓ SETUP COMPLETE\n\n";
    echo "Test Credentials:\n";
    echo "-----------------\n";
    echo "Operator Login (https://aeims.app/agents/login.php):\n";
    echo "  - sarah@example.com / demo123\n";
    echo "  - jessica@example.com / demo123\n";
    echo "  - amanda@example.com / demo123\n";
    echo "  - admin@aeims.app / admin123\n\n";
    echo "Customer Login (https://flirts.nyc):\n";
    echo "  - testuser1 / test123 (\$50)\n";
    echo "  - testuser2 / test123 (\$100)\n";
    echo "  - testuser3 / test123 (\$75)\n";

} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
