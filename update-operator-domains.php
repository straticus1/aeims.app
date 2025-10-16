<?php
/**
 * Update operators with domain assignments and services
 * URL: https://aeims.app/update-operator-domains.php
 */

header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/includes/DatabaseManager.php';

$db = DatabaseManager::getInstance();

if (!$db->isEnabled() || !$db->isAvailable()) {
    die("❌ Database not available\n");
}

echo "=== UPDATING OPERATOR DOMAINS & SERVICES ===\n\n";

// Define domain and service configurations for each operator
$operatorConfigs = [
    'sarah@example.com' => [
        'domains' => json_encode([
            'flirts.nyc' => ['active' => true, 'priority' => 1],
            'nycflirts.com' => ['active' => true, 'priority' => 2],
            'sexacomms.com' => ['active' => true, 'priority' => 3]
        ]),
        'services' => json_encode([
            'voice_calls' => true,
            'video_calls' => true,
            'text_chat' => true,
            'webcam' => true,
            'phone' => true
        ]),
        'metadata' => json_encode([
            'languages' => ['English'],
            'specialties' => ['Flirting', 'Conversation', 'Role Play'],
            'availability' => [
                'timezone' => 'America/New_York',
                'preferred_hours' => 'evening'
            ]
        ])
    ],
    'jessica@example.com' => [
        'domains' => json_encode([
            'flirts.nyc' => ['active' => true, 'priority' => 1],
            'nycflirts.com' => ['active' => true, 'priority' => 1]
        ]),
        'services' => json_encode([
            'voice_calls' => true,
            'video_calls' => false,
            'text_chat' => true,
            'webcam' => false,
            'phone' => true
        ]),
        'metadata' => json_encode([
            'languages' => ['English', 'Spanish'],
            'specialties' => ['Friendly Chat', 'Companionship'],
            'availability' => [
                'timezone' => 'America/New_York',
                'preferred_hours' => 'afternoon'
            ]
        ])
    ],
    'amanda@example.com' => [
        'domains' => json_encode([
            'sexacomms.com' => ['active' => true, 'priority' => 1],
            'flirts.nyc' => ['active' => true, 'priority' => 2]
        ]),
        'services' => json_encode([
            'voice_calls' => true,
            'video_calls' => true,
            'text_chat' => true,
            'webcam' => true,
            'phone' => true
        ]),
        'metadata' => json_encode([
            'languages' => ['English'],
            'specialties' => ['Fantasy', 'Role Play', 'Adult Entertainment'],
            'availability' => [
                'timezone' => 'America/New_York',
                'preferred_hours' => 'late_night'
            ]
        ])
    ]
];

// Update each operator
foreach ($operatorConfigs as $email => $config) {
    try {
        // First, check if columns exist - if not, add them
        $checkColumns = $db->fetchAll("
            SELECT column_name
            FROM information_schema.columns
            WHERE table_name = 'operators'
            AND column_name IN ('domains', 'services', 'metadata')
        ");

        $existingColumns = array_column($checkColumns, 'column_name');

        if (!in_array('domains', $existingColumns)) {
            echo "Adding 'domains' column...\n";
            $db->execute("ALTER TABLE operators ADD COLUMN domains JSONB");
        }

        if (!in_array('services', $existingColumns)) {
            echo "Adding 'services' column...\n";
            $db->execute("ALTER TABLE operators ADD COLUMN services JSONB");
        }

        if (!in_array('metadata', $existingColumns)) {
            echo "Adding 'metadata' column...\n";
            $db->execute("ALTER TABLE operators ADD COLUMN metadata JSONB");
        }

        // Now update the operator
        $result = $db->execute("
            UPDATE operators
            SET
                domains = :domains::jsonb,
                services = :services::jsonb,
                metadata = :metadata::jsonb,
                updated_at = CURRENT_TIMESTAMP
            WHERE email = :email
        ", [
            'email' => $email,
            'domains' => $config['domains'],
            'services' => $config['services'],
            'metadata' => $config['metadata']
        ]);

        echo "✅ Updated $email\n";
        echo "   Domains: " . $config['domains'] . "\n";
        echo "   Services: " . $config['services'] . "\n\n";

    } catch (Exception $e) {
        echo "❌ Error updating $email: " . $e->getMessage() . "\n\n";
    }
}

echo "\n=== VERIFICATION ===\n\n";

// Verify the updates
try {
    $operators = $db->fetchAll("
        SELECT email, display_name, domains, services, metadata
        FROM operators
        WHERE email LIKE '%example.com'
        ORDER BY email
    ");

    foreach ($operators as $op) {
        echo "Operator: {$op['display_name']} ({$op['email']})\n";
        echo "Domains: " . ($op['domains'] ?? 'NULL') . "\n";
        echo "Services: " . ($op['services'] ?? 'NULL') . "\n";
        echo "Metadata: " . ($op['metadata'] ?? 'NULL') . "\n";
        echo "---\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n✅ DONE! Operators now have domain assignments and services.\n";
echo "Try logging in again at: https://aeims.app/agents/login.php\n";
