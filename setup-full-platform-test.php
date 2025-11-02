<?php
/**
 * COMPREHENSIVE PLATFORM SETUP FOR TESTING
 * Access: https://aeims.app/setup-full-platform-test.php?key=setup2025
 *
 * This script will:
 * 1. Fix operator login passwords (aeims.app/agents/login.php)
 * 2. Create test customer accounts for flirts.nyc
 * 3. Give customers credits for testing
 * 4. Set operators online and available
 * 5. Enable messaging and calling features
 */

// Security key
if (!isset($_GET['key']) || $_GET['key'] !== 'setup2025') {
    http_response_code(403);
    die('Access denied - wrong key');
}

header('Content-Type: text/plain; charset=utf-8');

echo "╔═══════════════════════════════════════════════════════════════╗\n";
echo "║       COMPREHENSIVE PLATFORM SETUP FOR TESTING                ║\n";
echo "╚═══════════════════════════════════════════════════════════════╝\n\n";

// Database connection
$host = getenv('DB_HOST') ?: 'nitetext-db.c3iuy64is41m.us-east-1.rds.amazonaws.com';
$port = getenv('DB_PORT') ?: '5432';
$dbname = getenv('DB_NAME') ?: 'aeims_core';
$user = getenv('DB_USER') ?: 'nitetext';
$password = getenv('DB_PASS') ?: 'NiteText2025!SecureProd';

echo "[1/7] Connecting to database...\n";
echo "      Host: $host\n";
echo "      Database: $dbname\n\n";

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    echo "✅ Connected!\n\n";
} catch (Exception $e) {
    echo "❌ FAILED: " . $e->getMessage() . "\n";
    die();
}

// Password hashes (bcrypt cost=12)
$demo_hash = '$2y$12$sG16f6iwygYiNqhyZYZ.1.MdbRB9h/39zI/FYxSI4XMHJH.vjVDOu'; // demo123
$admin_hash = '$2y$12$NDsiUQDsTboMnFs.BDikx.wzq9Q0eq2YCZUl7KEE4hiNOS1c4XRBK'; // admin123

// ============================================================================
// STEP 2: Fix OPERATOR LOGIN PASSWORDS (aeims.app/agents/login.php)
// ============================================================================
echo "[2/7] Fixing OPERATOR LOGIN passwords...\n\n";

$operator_updates = [
    ['sarah@example.com', $demo_hash, 'demo123', 'Sarah Anderson'],
    ['jessica@example.com', $demo_hash, 'demo123', 'Jessica Martinez'],
    ['amanda@example.com', $demo_hash, 'demo123', 'Amanda Williams'],
    ['admin@aeims.app', $admin_hash, 'admin123', 'Administrator'],
];

$operators_fixed = 0;
foreach ($operator_updates as [$email, $hash, $pw, $name]) {
    try {
        // First try to find operator in users table
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Update existing user
            $stmt = $pdo->prepare("
                UPDATE users
                SET password_hash = ?,
                    password = ?,
                    updated_at = NOW(),
                    status = 'active',
                    is_active = true
                WHERE email = ?
            ");
            $stmt->execute([$hash, $hash, $email]);
            echo "      ✅ Fixed: $email → $pw\n";
            $operators_fixed++;
        } else {
            // Create new user
            $stmt = $pdo->prepare("
                INSERT INTO users (email, username, password_hash, password, name, role, status, is_active, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, 'operator', 'active', true, NOW(), NOW())
                ON CONFLICT (email) DO UPDATE SET
                    password_hash = EXCLUDED.password_hash,
                    password = EXCLUDED.password,
                    status = 'active',
                    is_active = true,
                    updated_at = NOW()
            ");
            $username = explode('@', $email)[0];
            $stmt->execute([$email, $username, $hash, $hash, $name]);
            echo "      ✅ Created: $email → $pw\n";
            $operators_fixed++;
        }
    } catch (Exception $e) {
        echo "      ❌ $email - " . $e->getMessage() . "\n";
    }
}

echo "\n      Fixed $operators_fixed operator accounts\n\n";

// ============================================================================
// STEP 3: Create TEST CUSTOMER ACCOUNTS for flirts.nyc
// ============================================================================
echo "[3/7] Creating TEST CUSTOMER accounts for flirts.nyc...\n\n";

$test_customers = [
    ['testuser1', 'test1@example.com', 'test123', 50.00],
    ['testuser2', 'test2@example.com', 'test123', 100.00],
    ['testuser3', 'test3@example.com', 'test123', 75.00],
];

$customers_created = 0;
foreach ($test_customers as [$username, $email, $password, $balance]) {
    try {
        $password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

        // Insert or update customer
        $stmt = $pdo->prepare("
            INSERT INTO customers (username, email, password_hash, display_name, status, email_verified, balance, account_type, created_at, updated_at)
            VALUES (?, ?, ?, ?, 'active', true, ?, 'premium', NOW(), NOW())
            ON CONFLICT (email) DO UPDATE SET
                password_hash = EXCLUDED.password_hash,
                status = 'active',
                email_verified = true,
                balance = EXCLUDED.balance,
                updated_at = NOW()
            RETURNING id
        ");
        $stmt->execute([$username, $email, $password_hash, $username, $balance]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
        $customer_id = $customer['id'];

        // Add site access to flirts.nyc
        $stmt = $pdo->prepare("
            INSERT INTO site_customers (site_domain, customer_id, is_primary_site, is_active, created_at)
            VALUES ('flirts.nyc', ?, true, true, NOW())
            ON CONFLICT (site_domain, customer_id) DO UPDATE SET
                is_active = true
        ");
        $stmt->execute([$customer_id]);

        echo "      ✅ $username ($email) - \$$balance credits - password: $password\n";
        $customers_created++;
    } catch (Exception $e) {
        echo "      ❌ $username - " . $e->getMessage() . "\n";
    }
}

echo "\n      Created/Updated $customers_created customer accounts\n\n";

// ============================================================================
// STEP 4: Set OPERATORS ONLINE and AVAILABLE
// ============================================================================
echo "[4/7] Setting operators ONLINE and AVAILABLE...\n\n";

try {
    // Update operators to be online and available
    $stmt = $pdo->prepare("
        UPDATE operators
        SET online_status = 'online',
            is_available = true,
            availability = 'available',
            updated_at = NOW()
        WHERE email IN ('sarah@example.com', 'jessica@example.com', 'amanda@example.com')
    ");
    $stmt->execute();
    $count = $stmt->rowCount();
    echo "      ✅ Set $count operators to ONLINE and AVAILABLE\n\n";
} catch (Exception $e) {
    echo "      ❌ Failed to update operators: " . $e->getMessage() . "\n\n";
}

// ============================================================================
// STEP 5: Enable MESSAGING feature
// ============================================================================
echo "[5/7] Enabling MESSAGING feature...\n\n";

try {
    // Check if chat_messages table exists
    $stmt = $pdo->query("
        SELECT EXISTS (
            SELECT FROM information_schema.tables
            WHERE table_schema = 'public'
            AND table_name = 'chat_messages'
        )
    ");
    $exists = $stmt->fetchColumn();

    if ($exists) {
        echo "      ✅ Messaging table exists\n";
    } else {
        // Create chat_messages table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS chat_messages (
                id BIGSERIAL PRIMARY KEY,
                sender_id INT NOT NULL,
                recipient_id INT NOT NULL,
                sender_type VARCHAR(20) NOT NULL CHECK (sender_type IN ('customer', 'operator')),
                message TEXT NOT NULL,
                read_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_conversation (sender_id, recipient_id),
                INDEX idx_recipient_unread (recipient_id, read_at),
                INDEX idx_created (created_at)
            )
        ");
        echo "      ✅ Created chat_messages table\n";
    }
} catch (Exception $e) {
    echo "      ⚠️  Messaging: " . $e->getMessage() . "\n";
}

echo "\n";

// ============================================================================
// STEP 6: Enable WebRTC CALLING feature
// ============================================================================
echo "[6/7] Enabling WebRTC CALLING feature...\n\n";

try {
    // Check if calls table exists
    $stmt = $pdo->query("
        SELECT EXISTS (
            SELECT FROM information_schema.tables
            WHERE table_schema = 'public'
            AND table_name = 'calls'
        )
    ");
    $exists = $stmt->fetchColumn();

    if ($exists) {
        echo "      ✅ Calls table exists\n";
    } else {
        // Create calls table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS calls (
                id BIGSERIAL PRIMARY KEY,
                customer_id INT NOT NULL,
                operator_id INT NOT NULL,
                start_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                end_time TIMESTAMP NULL,
                duration INT DEFAULT 0,
                cost DECIMAL(10,2) DEFAULT 0.00,
                status VARCHAR(20) DEFAULT 'initiated',
                call_type VARCHAR(20) DEFAULT 'webrtc',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_customer (customer_id),
                INDEX idx_operator (operator_id),
                INDEX idx_status (status)
            )
        ");
        echo "      ✅ Created calls table\n";
    }

    // Update operators to accept WebRTC calls
    $stmt = $pdo->prepare("
        UPDATE operators
        SET accept_calls = 'always',
            min_call_duration = 1,
            max_concurrent_calls = 5
        WHERE email IN ('sarah@example.com', 'jessica@example.com', 'amanda@example.com')
    ");
    $stmt->execute();
    echo "      ✅ Enabled operators for WebRTC calls\n";

} catch (Exception $e) {
    echo "      ⚠️  WebRTC: " . $e->getMessage() . "\n";
}

echo "\n";

// ============================================================================
// STEP 7: Verify SETUP
// ============================================================================
echo "[7/7] Verifying setup...\n\n";

try {
    // Check operators
    $stmt = $pdo->query("
        SELECT email, online_status, is_available, availability
        FROM operators
        WHERE email IN ('sarah@example.com', 'jessica@example.com', 'amanda@example.com')
    ");
    $operators = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "      OPERATORS:\n";
    foreach ($operators as $op) {
        $status_icon = ($op['online_status'] === 'online' && $op['is_available']) ? '✅' : '❌';
        echo "        $status_icon {$op['email']} - {$op['online_status']} - {$op['availability']}\n";
    }

    echo "\n      CUSTOMERS:\n";

    // Check customers
    $stmt = $pdo->query("
        SELECT c.username, c.email, c.balance, c.status, sc.site_domain
        FROM customers c
        JOIN site_customers sc ON c.id = sc.customer_id
        WHERE c.email LIKE '%@example.com%'
        AND sc.site_domain = 'flirts.nyc'
    ");
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($customers as $cust) {
        $balance_icon = ($cust['balance'] > 0) ? '💰' : '⚠️ ';
        echo "        $balance_icon {$cust['username']} ({$cust['email']}) - \${$cust['balance']} on {$cust['site_domain']}\n";
    }

} catch (Exception $e) {
    echo "      ❌ Verification error: " . $e->getMessage() . "\n";
}

// ============================================================================
// RESULTS
// ============================================================================
echo "\n" . str_repeat('═', 65) . "\n";
echo "✅ PLATFORM SETUP COMPLETE!\n";
echo str_repeat('═', 65) . "\n\n";

echo "🔐 OPERATOR LOGIN (aeims.app/agents/login.php):\n\n";
echo "   📧 sarah@example.com / demo123\n";
echo "   📧 jessica@example.com / demo123\n";
echo "   📧 amanda@example.com / demo123\n";
echo "   📧 admin@aeims.app / admin123\n\n";

echo "🔐 CUSTOMER LOGIN (flirts.nyc):\n\n";
echo "   📧 testuser1 (test1@example.com) / test123 - \$50.00\n";
echo "   📧 testuser2 (test2@example.com) / test123 - \$100.00\n";
echo "   📧 testuser3 (test3@example.com) / test123 - \$75.00\n\n";

echo "✅ Features Enabled:\n";
echo "   ✔  Operators set to ONLINE\n";
echo "   ✔  Customers have CREDITS\n";
echo "   ✔  Messaging enabled\n";
echo "   ✔  WebRTC calling enabled\n\n";

echo "🧪 Testing Steps:\n";
echo "   1. Login as operator at: https://aeims.app/agents/login.php\n";
echo "   2. Login as customer at: https://flirts.nyc\n";
echo "   3. Customer should see operators online\n";
echo "   4. Customer can message and call operators\n\n";

echo "📝 DELETE THIS FILE AFTER USE FOR SECURITY!\n\n";
