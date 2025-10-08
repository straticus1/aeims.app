<?php
/**
 * SSO Login Endpoint
 * Handles cross-domain SSO authentication
 */

session_start();

require_once '../../../services/SSOManager.php';
require_once '../../../services/SiteManager.php';

try {
    $ssoManager = new \AEIMS\Services\SSOManager();
    $siteManager = new \AEIMS\Services\SiteManager();

    $site = $siteManager->getSite('flirts.nyc');
    if (!$site || !$site['active']) {
        http_response_code(503);
        die('Site temporarily unavailable');
    }
} catch (Exception $e) {
    error_log("SSO Login error: " . $e->getMessage());
    http_response_code(500);
    die('SSO system unavailable');
}

$ssoToken = $_GET['sso_token'] ?? '';
$returnPath = $_GET['return_path'] ?? '/dashboard.php';

if (empty($ssoToken)) {
    $_SESSION['auth_message'] = 'Invalid SSO request';
    $_SESSION['auth_message_type'] = 'error';
    header('Location: /');
    exit;
}

try {
    // Process SSO login
    $customer = $ssoManager->processSSOLogin($ssoToken, 'flirts.nyc');

    if ($customer) {
        // Set session data
        $_SESSION['customer_id'] = $customer['customer_id'];
        $_SESSION['customer_data'] = $customer;
        $_SESSION['site_domain'] = 'flirts.nyc';
        $_SESSION['sso_token'] = $ssoToken;

        // Redirect to return path
        header('Location: ' . $returnPath);
        exit;
    } else {
        throw new Exception('SSO authentication failed');
    }
} catch (Exception $e) {
    error_log("SSO Login failed: " . $e->getMessage());
    $_SESSION['auth_message'] = 'SSO login failed. Please try logging in directly.';
    $_SESSION['auth_message_type'] = 'error';
    header('Location: /');
    exit;
}
?>