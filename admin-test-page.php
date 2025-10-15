<?php
// EXACT copy of admin-dashboard.php but with different name to test if name matters
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/SecurityManager.php';
$security = SecurityManager::getInstance();
$security->initializeSecureSession();

$isLoggedIn = false;
$username = '';
$userType = '';

if (isset($_SESSION['user_id']) && $_SESSION['user_type'] === 'admin') {
    $isLoggedIn = true;
    $username = $_SESSION['username'];
    $userType = 'Admin';
}

if (!$isLoggedIn) {
    die("NOT LOGGED IN - Session data: " . print_r($_SESSION, true));
}

echo "✅ AUTHENTICATION PASSED\n\n";
echo "This is a test page with exact same logic as admin-dashboard.php\n";
echo "If you see this, the issue is specific to the admin-dashboard.php filename or routing\n";
?>