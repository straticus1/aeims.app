<?php
// Initialize session using SecurityManager to ensure correct session name
require_once __DIR__ . '/includes/SecurityManager.php';
$security = SecurityManager::getInstance();
$security->initializeSecureSession();

header('Content-Type: application/json');
echo json_encode([
    'session_id' => session_id(),
    'session_data' => $_SESSION,
    'cookies' => $_COOKIE,
    'authenticated' => isset($_SESSION['user_id']) && isset($_SESSION['user_type'])
], JSON_PRETTY_PRINT);
