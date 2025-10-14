<?php
/**
 * SSO Session Middleware
 * Automatically logs in users with valid SSO tokens from other domains
 */

require_once __DIR__ . '/../../../services/SSOManager.php';

/**
 * Check for SSO token and auto-login if valid
 */
function checkSSOSession() {
    // Skip if already logged in
    if (isset($_SESSION['customer_id'])) {
        return;
    }

    try {
        $ssoManager = new \AEIMS\Services\SSOManager();

        // Check for SSO token in cookie
        $ssoToken = $_COOKIE['aeims_sso_token'] ?? '';

        if ($ssoToken) {
            $customer = $ssoManager->getCustomerFromToken($ssoToken);

            if ($customer) {
                // Auto-login the user
                $_SESSION['customer_id'] = $customer['customer_id'];
                $_SESSION['customer_data'] = $customer;
                $_SESSION['site_domain'] = 'flirts.nyc';
                $_SESSION['sso_token'] = $ssoToken;

                // Log SSO auto-login
                $customerManager = new \AEIMS\Services\CustomerManager();
                $customerManager->logActivity($customer['customer_id'], 'sso_auto_login', [
                    'site' => 'flirts.nyc',
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
            } else {
                // Invalid token, clear cookie
                setcookie('aeims_sso_token', '', [
                    'expires' => time() - 3600,
                    'path' => '/',
                    'domain' => '.afterdarksystems.net',
                    'secure' => true,
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]);
            }
        }
    } catch (Exception $e) {
        error_log("SSO Middleware error: " . $e->getMessage());
    }
}
?>