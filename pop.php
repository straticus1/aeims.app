<?php
// Ultra simple population - no checks, just insert
// URL: https://aeims.app/pop.php

require_once __DIR__ . '/includes/DatabaseManager.php';
$db = DatabaseManager::getInstance();

if (!$db->isEnabled() || !$db->isAvailable()) die("DB not available\n");

echo "Connected. Inserting...\n\n";

// Try with NULL status (let DB use default)
$ops = [
    ['sarah@example.com', 'Sarah Johnson', '$2y$12$uP769e4CWOCmgm7iXSsqoeH0Vqoi5lSmsPizDmSTmxOoyyTNuykMm', '+1-555-0101'],
    ['jessica@example.com', 'Jessica Williams', '$2y$12$Au9qvnvZrHNoa6IDYSFRlOr54j3WI9hZ264Hh9u3W5kWqZWUSGzgW', '+1-555-0102'],
    ['amanda@example.com', 'Amanda Rodriguez', '$2y$12$PAW1bUSBAJzneXOqUYtdYelR/w.6CKPVK9ScuyrfvBR3dr3A43xMa', '+1-555-0103'],
];

foreach ($ops as list($email, $name, $hash, $phone)) {
    try {
        $db->execute("
            INSERT INTO operators (username, email, display_name, password_hash, phone, is_active, is_verified)
            VALUES (?, ?, ?, ?, ?, true, true)
            ON CONFLICT (email) DO UPDATE SET
                password_hash = EXCLUDED.password_hash,
                username = EXCLUDED.username
        ", [$email, $email, $name, $hash, $phone]);
        echo "OK: $name\n";
    } catch (Exception $e) {
        echo "ERR $name: " . $e->getMessage() . "\n";

        // Try with each status value
        foreach (['online', 'offline', 'available', 'busy', 'away', 'unavailable'] as $st) {
            try {
                $db->execute("
                    INSERT INTO operators (username, email, display_name, password_hash, phone, status, is_active, is_verified)
                    VALUES (?, ?, ?, ?, ?, ?, true, true)
                    ON CONFLICT (email) DO UPDATE SET password_hash = EXCLUDED.password_hash, username = EXCLUDED.username
                ", [$email, $email, $name, $hash, $phone, $st]);
                echo "OK: $name (status=$st)\n";
                break;
            } catch (Exception $e2) { continue; }
        }
    }
}

echo "\nDone! Test: https://aeims.app/agents/login.php\n";
