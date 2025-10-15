<?php
// Minimal test - just check session auth
require_once __DIR__ . '/includes/SecurityManager.php';
$security = SecurityManager::getInstance();
$security->initializeSecureSession();

header('Content-Type: text/plain');

if (isset($_SESSION['user_id']) && $_SESSION['user_type'] === 'admin') {
    echo "✅ AUTHENTICATED AS ADMIN\n";
    echo "User ID: " . $_SESSION['user_id'] . "\n";
    echo "Username: " . $_SESSION['username'] . "\n";
} else {
    echo "❌ NOT AUTHENTICATED\n";
    echo "Session data: " . print_r($_SESSION, true);
}
