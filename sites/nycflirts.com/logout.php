<?php
/**
 * NYC Flirts - Customer Logout
 */

session_start();

// Load CustomerAuth class
require_once __DIR__ . '/../../includes/CustomerAuth.php';

$hostname = $_SERVER['HTTP_HOST'] ?? 'nycflirts.com';
$hostname = preg_replace('/^www\./', '', $hostname);
$hostname = preg_replace('/:\d+$/', '', $hostname);

$auth = new CustomerAuth($hostname);
$auth->logout();

// Redirect to homepage
header('Location: /?logged_out=1');
exit;
