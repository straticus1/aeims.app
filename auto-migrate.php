#!/usr/bin/env php
<?php
/**
 * Auto-Migration Script - Runs automatically to populate operators if needed
 * Called from Docker entrypoint or can be run manually
 */

require_once __DIR__ . '/includes/DatabaseManager.php';

error_log("AUTO-MIGRATE: Starting...");

$db = DatabaseManager::getInstance();

if (!$db->isEnabled() || !$db->isAvailable()) {
    error_log("AUTO-MIGRATE: Database not available, skipping");
    exit(0);
}

error_log("AUTO-MIGRATE: Database connected");

// Check if operators exist with password_hash
try {
    $count = $db->fetchOne("SELECT COUNT(*) as count FROM operators WHERE password_hash IS NOT NULL AND password_hash != ''");

    if ($count && $count['count'] > 0) {
        error_log("AUTO-MIGRATE: Operators already exist ({$count['count']}), skipping");
        exit(0);
    }

    error_log("AUTO-MIGRATE: No operators with passwords found, populating...");

    // Add columns if missing
    $db->execute("ALTER TABLE operators ADD COLUMN IF NOT EXISTS password_hash VARCHAR(255)");
    $db->execute("ALTER TABLE operators ADD COLUMN IF NOT EXISTS phone VARCHAR(50)");

    // Insert operators
    $operators = [
        ['sarah@example.com', 'Sarah Johnson', '$2y$12$uP769e4CWOCmgm7iXSsqoeH0Vqoi5lSmsPizDmSTmxOoyyTNuykMm', '+1-555-0101'],
        ['jessica@example.com', 'Jessica Williams', '$2y$12$Au9qvnvZrHNoa6IDYSFRlOr54j3WI9hZ264Hh9u3W5kWqZWUSGzgW', '+1-555-0102'],
        ['amanda@example.com', 'Amanda Rodriguez', '$2y$12$PAW1bUSBAJzneXOqUYtdYelR/w.6CKPVK9ScuyrfvBR3dr3A43xMa', '+1-555-0103'],
    ];

    foreach ($operators as list($email, $name, $hash, $phone)) {
        $db->execute("
            INSERT INTO operators (username, email, display_name, password_hash, phone, status, is_active, is_verified, created_at)
            VALUES (:username, :email, :name, :hash, :phone, 'active', true, true, CURRENT_TIMESTAMP)
            ON CONFLICT (email) DO UPDATE SET
                password_hash = EXCLUDED.password_hash,
                display_name = EXCLUDED.display_name,
                phone = EXCLUDED.phone,
                updated_at = CURRENT_TIMESTAMP
        ", [
            'username' => $email,
            'email' => $email,
            'name' => $name,
            'hash' => $hash,
            'phone' => $phone,
        ]);
        error_log("AUTO-MIGRATE: Added/updated $name");
    }

    error_log("AUTO-MIGRATE: ✅ Complete! Operators populated.");

} catch (Exception $e) {
    error_log("AUTO-MIGRATE: ❌ Error: " . $e->getMessage());
    // Don't fail - just log and continue
}

exit(0);
