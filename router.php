<?php
/**
 * PHP Built-in Server Router with Virtual Host Support
 * Handles URL routing for development server and virtual hosts
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$host = $_SERVER['HTTP_HOST'] ?? '';

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

// Virtual Host Routing
switch (true) {
    case ($host === 'sexacomms.com' || $host === 'www.sexacomms.com' || $host === 'login.sexacomms.com'):
        // Admin interface
        require_once 'sites/sexacomms/index.php';
        return;

    case ($host === 'flirts.nyc' || $host === 'www.flirts.nyc'):
        // Customer site
        require_once '../aeims/sites/flirts.nyc/index.php';
        return;

    case ($host === 'nycflirts.com' || $host === 'www.nycflirts.com'):
        // Customer site
        require_once '../aeims/sites/nycflirts.com/index.php';
        return;

    case strpos($host, 'aeims.app') !== false:
    default:
        // Default AEIMS platform
        require_once 'index.php';
        return;
}
?>