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
        // Customer site - check for specific routes first
        if ($uri === '/login.php' && file_exists('sites/flirts.nyc/login.php')) {
            require_once 'sites/flirts.nyc/login.php';
            return;
        }
        if ($uri === '/auth.php' && file_exists('sites/flirts.nyc/auth.php')) {
            require_once 'sites/flirts.nyc/auth.php';
            return;
        }
        if ($uri === '/logout.php' && file_exists('sites/flirts.nyc/logout.php')) {
            require_once 'sites/flirts.nyc/logout.php';
            return;
        }
        if (preg_match('#^/(dashboard|messages|chat|search-operators|activity-log)\.php#', $uri, $matches)) {
            $file = 'sites/flirts.nyc/' . $matches[0];
            if (file_exists($file)) {
                require_once $file;
                return;
            }
        }
        // Default to index
        if (file_exists('sites/flirts.nyc/index.php')) {
            require_once 'sites/flirts.nyc/index.php';
        } else {
            // Fallback to main site with customer context
            $_GET['site'] = 'flirts.nyc';
            require_once 'index.php';
        }
        return;

    case ($host === 'nycflirts.com' || $host === 'www.nycflirts.com'):
        // Customer site - check for specific routes first
        if ($uri === '/login.php' && file_exists('sites/nycflirts.com/login.php')) {
            require_once 'sites/nycflirts.com/login.php';
            return;
        }
        if ($uri === '/auth.php' && file_exists('sites/nycflirts.com/auth.php')) {
            require_once 'sites/nycflirts.com/auth.php';
            return;
        }
        if ($uri === '/logout.php' && file_exists('sites/nycflirts.com/logout.php')) {
            require_once 'sites/nycflirts.com/logout.php';
            return;
        }
        if (preg_match('#^/(dashboard|messages|chat|search-operators|activity-log)\.php#', $uri, $matches)) {
            $file = 'sites/nycflirts.com/' . $matches[0];
            if (file_exists($file)) {
                require_once $file;
                return;
            }
        }
        // Default to index
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
        // Handle agent portal routes
        if (preg_match('#^/agents/(.+)#', $uri, $matches)) {
            // SECURITY FIX: Proper directory traversal prevention
            require_once __DIR__ . '/includes/SecurityManager.php';
            $security = SecurityManager::getInstance();

            $safePath = $security->validateFilePath($matches[1], __DIR__ . '/agents');

            if ($safePath && file_exists($safePath)) {
                require_once $safePath;
                return;
            } else {
                http_response_code(404);
                echo "Page not found";
                return;
            }
        }

        // Handle admin routes
        if (preg_match('#^/admin#', $uri)) {
            if (file_exists('admin.php')) {
                require_once 'admin.php';
                return;
            }
        }

        // Default to index.php
        require_once 'index.php';
        return;
}
?>