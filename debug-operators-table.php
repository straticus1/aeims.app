#!/usr/bin/env php
<?php
/**
 * Debug: Check operators table structure
 */

if (php_sapi_name() !== 'cli') {
    if (!isset($_GET['key']) || $_GET['key'] !== 'debug2025') {
        http_response_code(401);
        die('Unauthorized');
    }
}

require_once __DIR__ . '/includes/DatabaseManager.php';

echo "=== Operators Table Debug ===\n\n";

$db = DatabaseManager::getInstance();

if (!$db->isEnabled() || !$db->isAvailable()) {
    die("❌ Database not available\n");
}

echo "✅ Connected to database\n\n";

// Check if operators table exists
try {
    $result = $db->fetchOne("
        SELECT EXISTS (
            SELECT FROM information_schema.tables
            WHERE table_schema = 'public'
            AND table_name = 'operators'
        )
    ");

    if ($result['exists']) {
        echo "✅ Operators table EXISTS\n\n";

        // Get table structure
        $columns = $db->fetchAll("
            SELECT column_name, data_type, character_maximum_length
            FROM information_schema.columns
            WHERE table_schema = 'public'
            AND table_name = 'operators'
            ORDER BY ordinal_position
        ");

        echo "Table structure:\n";
        foreach ($columns as $col) {
            $type = $col['data_type'];
            if ($col['character_maximum_length']) {
                $type .= "(" . $col['character_maximum_length'] . ")";
            }
            echo "  - " . $col['column_name'] . ": " . $type . "\n";
        }

        // Count rows
        $count = $db->fetchOne("SELECT COUNT(*) as count FROM operators");
        echo "\nRows in table: " . $count['count'] . "\n";

        // EMERGENCY: Populate operators if requested
        if (isset($_GET['populate']) && $_GET['populate'] === 'yes') {
            echo "\n=== POPULATING OPERATORS ===\n";

            $operators = [
                ['sarah@example.com', 'Sarah Johnson', '$2y$12$uP769e4CWOCmgm7iXSsqoeH0Vqoi5lSmsPizDmSTmxOoyyTNuykMm', '+1-555-0101'],
                ['jessica@example.com', 'Jessica Williams', '$2y$12$Au9qvnvZrHNoa6IDYSFRlOr54j3WI9hZ264Hh9u3W5kWqZWUSGzgW', '+1-555-0102'],
                ['amanda@example.com', 'Amanda Rodriguez', '$2y$12$PAW1bUSBAJzneXOqUYtdYelR/w.6CKPVK9ScuyrfvBR3dr3A43xMa', '+1-555-0103'],
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
                            username = EXCLUDED.username,
                            is_active = true,
                            is_verified = true,
                            status = 'active',
                            updated_at = CURRENT_TIMESTAMP
                    ", [
                        'username' => $email,
                        'email' => $email,
                        'name' => $name,
                        'hash' => $hash,
                        'phone' => $phone,
                    ]);
                    echo "✅ $name ($email)\n";
                } catch (Exception $e) {
                    echo "❌ $name: " . $e->getMessage() . "\n";
                }
            }

            echo "\n✅ DONE! Test at: https://aeims.app/agents/login.php\n";
            echo "Credentials: sarah@example.com / demo123\n";
        }

    } else {
        echo "❌ Operators table does NOT exist\n";
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
