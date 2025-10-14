<?php
/**
 * SexaComms.com - Operator Portal
 * Entry point for operators to manage customers and earnings
 */

// Clear any previous output
if (ob_get_level()) ob_end_clean();

// Absolute path to agents login
$agentsLogin = dirname(dirname(__DIR__)) . '/agents/login.php';

if (file_exists($agentsLogin)) {
    require_once $agentsLogin;
} else {
    // Fallback error
    http_response_code(500);
    die('Operator portal configuration error');
}
?>
