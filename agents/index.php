<?php
/**
 * AEIMS Agents Portal - Index Page
 * Redirects to login or dashboard based on authentication
 */

require_once 'includes/OperatorAuth.php';

$auth = new OperatorAuth();

// Check if operator is already logged in
if ($auth->isLoggedIn()) {
    // Redirect to dashboard
    header('Location: dashboard.php');
    exit;
}

// Redirect to login page
header('Location: login.php');
exit;
?>