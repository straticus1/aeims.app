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

// Demo operators from JSON file
$operators = [
    [
        'operator_id' => 'op_001',
        'username' => 'sarah@example.com',
        'email' => 'sarah@example.com',
        'password_hash' => password_hash('demo123', PASSWORD_DEFAULT),
        'name' => 'Sarah Johnson',
        'phone' => '+1-555-0101',
        'status' => 'active',
        'verified' => true,
        'sites' => json_encode(['beastybitches.com', 'cavern.love', 'nycflirts.com', 'nitetext.com', 'nineinchesof.com', 'holyflirts.com']),
        'services' => json_encode(['calls', 'text', 'chat', 'video', 'cam']),
    ],
    [
        'operator_id' => 'op_002',
        'username' => 'jessica@example.com',
        'email' => 'jessica@example.com',
        'password_hash' => password_hash('demo456', PASSWORD_DEFAULT),
        'name' => 'Jessica Williams',
        'phone' => '+1-555-0102',
        'status' => 'active',
        'verified' => true,
        'sites' => json_encode(['nycflirts.com', 'gfecalls.com', 'latenite.love', 'fantasyflirts.live', 'holyflirts.com', 'dommecats.com']),
        'services' => json_encode(['calls', 'text', 'chat', 'video', 'domination']),
    ],
    [
        'operator_id' => 'op_003',
        'username' => 'amanda@example.com',
        'email' => 'amanda@example.com',
        'password_hash' => password_hash('demo789', PASSWORD_DEFAULT),
        'name' => 'Amanda Rodriguez',
        'phone' => '+1-555-0103',
        'status' => 'active',
        'verified' => true,
        'sites' => json_encode(['dommecats.com', 'fantasyflirts.live', 'nineinchesof.com', 'beastybitches.com', 'latenite.love', 'nitetext.com', 'cavern.love']),
        'services' => json_encode(['text', 'chat', 'calls', 'domination']),
    ],
];

$sql = "
    INSERT INTO operators (
        operator_id, username, email, password_hash, name, phone,
        status, verified, sites, services, created_at
    ) VALUES (
        :operator_id, :username, :email, :password_hash, :name, :phone,
        :status, :verified, :sites::jsonb, :services::jsonb, CURRENT_TIMESTAMP
    )
    ON CONFLICT (username) DO UPDATE SET
        password_hash = EXCLUDED.password_hash,
        name = EXCLUDED.name,
        phone = EXCLUDED.phone,
        status = EXCLUDED.status,
        verified = EXCLUDED.verified,
        sites = EXCLUDED.sites,
        services = EXCLUDED.services,
        updated_at = CURRENT_TIMESTAMP
";

$added = 0;
$updated = 0;

foreach ($operators as $operator) {
    try {
        // Check if exists
        $existing = $db->fetchOne(
            "SELECT operator_id FROM operators WHERE username = :username",
            ['username' => $operator['username']]
        );

        $db->execute($sql, $operator);

        if ($existing) {
            $updated++;
            echo "✅ Updated: {$operator['name']} ({$operator['email']})\n";
        } else {
            $added++;
            echo "✅ Added: {$operator['name']} ({$operator['email']})\n";
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
