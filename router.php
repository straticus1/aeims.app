<?php
/**
 * PHP Built-in Server Router
 * Handles URL routing for development server
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Handle API routes
if (preg_match('/^\/api\/admin\/sites/', $uri)) {
    require_once 'api/admin/sites.php';
    return;
}

// Handle health check
if ($uri === '/health') {
    require_once 'health.php';
    return;
}

// Handle static files
if (file_exists(__DIR__ . $uri)) {
    return false; // Let PHP's built-in server handle static files
}

// Default to index.php
require_once 'index.php';
?>