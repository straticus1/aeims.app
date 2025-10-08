<?php
/**
 * SSO Logout Endpoint
 * Handles cross-domain SSO logout
 */

session_start();

require_once '../../../services/SSOManager.php';

try {
    $ssoManager = new \AEIMS\Services\SSOManager();
} catch (Exception $e) {
    error_log("SSO Logout error: " . $e->getMessage());
}

// Get customer ID for global logout
$customerId = $_GET['customer_id'] ?? ($_SESSION['customer_id'] ?? '');

if ($customerId && isset($_SESSION['customer_id']) && $_SESSION['customer_id'] === $customerId) {
    // This is a valid logout request
    try {
        $ssoManager->globalLogout($customerId);
    } catch (Exception $e) {
        error_log("Global logout failed: " . $e->getMessage());
    }
}

// Clear local session
session_destroy();

// Redirect to homepage
header('Location: /');
exit;
?>