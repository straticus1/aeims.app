#!/usr/bin/env php
<?php
/**
 * EMERGENCY FIX - Run this to fix everything
 * URL: https://aeims.app/emergency-fix.php?key=emergency2025
 */

if (php_sapi_name() !== 'cli') {
    if (!isset($_GET['key']) || $_GET['key'] !== 'emergency2025') {
        http_response_code(401);
        die('Unauthorized');
    }
}

header('Content-Type: text/plain; charset=utf-8');
echo "=== EMERGENCY FIX ===\n\n";

require_once __DIR__ . '/includes/DatabaseManager.php';

$db = DatabaseManager::getInstance();

if (!$db->isEnabled() || !$db->isAvailable()) {
    die("❌ Database not available\n");
}

echo "✅ Connected\n\n";

// First, let's see what status values exist
echo "Checking existing status values...\n";
try {
    $existing = $db->fetchAll("SELECT DISTINCT status FROM operators WHERE status IS NOT NULL");
    echo "Existing status values:\n";
    foreach ($existing as $row) {
        echo "  - {$row['status']}\n";
    }
    echo "\n";
} catch (Exception $e) {
    echo "Could not check: " . $e->getMessage() . "\n\n";
}

// Try with NULL status (let database use default)
echo "Attempting to insert with NULL status...\n";
$operators = [
    ['sarah@example.com', 'Sarah Johnson', '$2y$12$uP769e4CWOCmgm7iXSsqoeH0Vqoi5lSmsPizDmSTmxOoyyTNuykMm', '+1-555-0101'],
    ['jessica@example.com', 'Jessica Williams', '$2y$12$Au9qvnvZrHNoa6IDYSFRlOr54j3WI9hZ264Hh9u3W5kWqZWUSGzgW', '+1-555-0102'],
    ['amanda@example.com', 'Amanda Rodriguez', '$2y$12$PAW1bUSBAJzneXOqUYtdYelR/w.6CKPVK9ScuyrfvBR3dr3A43xMa', '+1-555-0103'],
];

foreach ($operators as list($email, $name, $hash, $phone)) {
    try {
        // Try INSERT with NULL status
        $db->execute("
            INSERT INTO operators (username, email, display_name, password_hash, phone, is_active, is_verified)
            VALUES (:username, :email, :name, :hash, :phone, true, true)
            ON CONFLICT (email) DO UPDATE SET
                password_hash = EXCLUDED.password_hash,
                display_name = EXCLUDED.display_name,
                phone = EXCLUDED.phone,
                username = EXCLUDED.username,
                is_active = true,
                is_verified = true
        ", [
            'username' => $email,
            'email' => $email,
            'name' => $name,
            'hash' => $hash,
            'phone' => $phone,
        ]);
        echo "  ✅ $name\n";
    } catch (Exception $e) {
        echo "  ❌ $name: " . $e->getMessage() . "\n";

        // If that fails, try each valid status value
        foreach (['online', 'offline', 'available', 'busy', 'away'] as $status) {
            try {
                $db->execute("
                    INSERT INTO operators (username, email, display_name, password_hash, phone, status, is_active, is_verified)
                    VALUES (:username, :email, :name, :hash, :phone, :status, true, true)
                    ON CONFLICT (email) DO UPDATE SET
                        password_hash = EXCLUDED.password_hash,
                        display_name = EXCLUDED.display_name,
                        phone = EXCLUDED.phone,
                        username = EXCLUDED.username,
                        status = EXCLUDED.status,
                        is_active = true,
                        is_verified = true
                ", [
                    'username' => $email,
                    'email' => $email,
                    'name' => $name,
                    'hash' => $hash,
                    'phone' => $phone,
                    'status' => $status,
                ]);
                echo "  ✅ $name (with status=$status)\n";
                break;
            } catch (Exception $e2) {
                // Try next status
                continue;
            }
        }
    }
}

echo "\n✅ DONE!\n";
echo "Test at: https://aeims.app/agents/login.php\n";
echo "Credentials: sarah@example.com / demo123\n";
