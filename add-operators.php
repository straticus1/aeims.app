<?php
/**
 * Add demo operators to PostgreSQL database
 * Access: https://aeims.app/add-operators.php?key=migrate2025
 */

if (!isset($_GET['key']) || $_GET['key'] !== 'migrate2025') {
    die('Unauthorized');
}

// Database connection
try {
    $pdo = new PDO(
        "pgsql:host=nitetext-db.c3iuy64is41m.us-east-1.rds.amazonaws.com;port=5432;dbname=aeims_core",
        'nitetext',
        'NiteText2025!SecureProd'
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

echo "✅ Connected to database\n\n";

// Add username column if it doesn't exist
try {
    $pdo->exec("ALTER TABLE operators ADD COLUMN IF NOT EXISTS username VARCHAR(255)");
    echo "✅ Added/checked username column\n";
} catch (PDOException $e) {
    echo "Note: " . $e->getMessage() . "\n";
}

// Create unique index on username
try {
    $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS operators_username_idx ON operators(username)");
    echo "✅ Created username index\n\n";
} catch (PDOException $e) {
    echo "Note: " . $e->getMessage() . "\n\n";
}

// Demo operators
$operators = [
    [
        'id' => 'op_001',
        'username' => 'sarah@example.com',
        'email' => 'sarah@example.com',
        'password_hash' => password_hash('demo123', PASSWORD_DEFAULT),
        'name' => 'Sarah Johnson',
        'phone' => '+1-555-0101',
        'status' => 'active',
        'verified' => true,
        'domains' => json_encode(['beastybitches.com' => ['active' => true], 'cavern.love' => ['active' => true]]),
        'services' => json_encode(['calls', 'text', 'chat', 'video']),
        'profile' => json_encode(['bio' => 'Experienced operator', 'age' => 28]),
        'settings' => json_encode(['calls_enabled' => true, 'text_enabled' => true])
    ],
    [
        'id' => 'op_002',
        'username' => 'jessica@example.com',
        'email' => 'jessica@example.com',
        'password_hash' => password_hash('demo456', PASSWORD_DEFAULT),
        'name' => 'Jessica Williams',
        'phone' => '+1-555-0102',
        'status' => 'active',
        'verified' => true,
        'domains' => json_encode(['nycflirts.com' => ['active' => true], 'gfecalls.com' => ['active' => true]]),
        'services' => json_encode(['calls', 'text', 'chat']),
        'profile' => json_encode(['bio' => 'Friendly and professional', 'age' => 26]),
        'settings' => json_encode(['calls_enabled' => true, 'text_enabled' => true])
    ],
    [
        'id' => 'op_003',
        'username' => 'amanda@example.com',
        'email' => 'amanda@example.com',
        'password_hash' => password_hash('demo789', PASSWORD_DEFAULT),
        'name' => 'Amanda Rodriguez',
        'phone' => '+1-555-0103',
        'status' => 'active',
        'verified' => true,
        'domains' => json_encode(['dommecats.com' => ['active' => true], 'fantasyflirts.live' => ['active' => true]]),
        'services' => json_encode(['calls', 'text', 'domination']),
        'profile' => json_encode(['bio' => 'Dominant and confident', 'age' => 32]),
        'settings' => json_encode(['calls_enabled' => true, 'text_enabled' => false])
    ]
];

$stmt = $pdo->prepare("
    INSERT INTO operators (id, username, email, password_hash, name, phone, status, verified, domains, services, profile, settings)
    VALUES (:id, :username, :email, :password_hash, :name, :phone, :status, :verified, :domains::jsonb, :services::jsonb, :profile::jsonb, :settings::jsonb)
    ON CONFLICT (id) DO UPDATE SET
        username = EXCLUDED.username,
        password_hash = EXCLUDED.password_hash,
        updated_at = CURRENT_TIMESTAMP
");

echo "Adding operators...\n";
foreach ($operators as $operator) {
    try {
        $stmt->execute($operator);
        echo "✅ Added/Updated: {$operator['email']}\n";
    } catch (PDOException $e) {
        echo "❌ Error with {$operator['email']}: " . $e->getMessage() . "\n";
    }
}

echo "\n✅ Done! Test login at: https://aeims.app/agents/login.php\n";
echo "Credentials: sarah@example.com / demo123\n";
