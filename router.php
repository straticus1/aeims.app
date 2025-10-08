<?php
/**
 * PHP Built-in Server Router with Virtual Host Support
 * Handles URL routing for development server and virtual hosts
 */

require_once 'includes/Logger.php';

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$host = $_SERVER['HTTP_HOST'] ?? '';

// Log the routing request
aeims_log_debug("Router Request: Host={$host}, URI={$uri}", [
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET'
]);

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
    return false; // Let Apache handle static files
}

// Virtual Host Routing
switch (true) {
    case ($host === 'sexacomms.com' || $host === 'www.sexacomms.com' || $host === 'login.sexacomms.com'):
        // Admin interface - check if sites/sexacomms/index.php exists
        if (file_exists('sites/sexacomms/index.php')) {
            require_once 'sites/sexacomms/index.php';
        } else {
            // Fallback to main index with admin context
            $_GET['admin'] = true;
            $_SERVER['SCRIPT_NAME'] = '/admin.php';
            require_once 'admin.php';
        }
        return;

    case ($host === 'flirts.nyc' || $host === 'www.flirts.nyc'):
        // Customer site - use correct path
        if (file_exists('sites/flirts.nyc/index.php')) {
            require_once 'sites/flirts.nyc/index.php';
        } else {
            // Fallback to main site with customer context
            $_GET['site'] = 'flirts.nyc';
            require_once 'index.php';
        }
        return;

    case ($host === 'nycflirts.com' || $host === 'www.nycflirts.com'):
        // Customer site - use correct path
        if (file_exists('sites/nycflirts.com/index.php')) {
            require_once 'sites/nycflirts.com/index.php';
        } else {
            // Fallback to main site with customer context
            $_GET['site'] = 'nycflirts.com';
            require_once 'index.php';
        }
        return;

    case strpos($host, 'aeims.app') !== false:
    default:
        // Default AEIMS platform
        require_once 'index.php';
        return;
}
?>