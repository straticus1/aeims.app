#!/bin/bash
# PostgreSQL Deployment Script for AEIMS Production Instances
# This script runs on the EC2 instances to configure PostgreSQL

set -e

echo "Starting AEIMS PostgreSQL deployment..."

# Configuration
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-5432}"
DB_NAME="${DB_NAME:-aeims_core}"
DB_USER="${DB_USER:-aeims_user}"
DB_PASS="${DB_PASS:-secure_password_123}"

# Create application directory
echo "Creating application directory..."
sudo mkdir -p /var/www/aeims
cd /var/www/aeims

# Install required packages
echo "Installing PostgreSQL client and removing MySQL..."
sudo apt-get update -y
sudo apt-get install -y postgresql-client php8.2-pgsql curl

# Remove MySQL packages if present
sudo apt-get remove -y mysql-client mysql-server php8.2-mysql mysql-common || true
sudo apt-get autoremove -y || true

# Create environment configuration
echo "Creating environment configuration..."
sudo tee /var/www/aeims/.env > /dev/null << EOF
# AEIMS Environment Configuration
DB_HOST=$DB_HOST
DB_PORT=$DB_PORT
DB_NAME=$DB_NAME
DB_USER=$DB_USER
DB_PASS=$DB_PASS
ENVIRONMENT=prod
DOMAIN_NAME=aeims.app
EOF

# Create PostgreSQL database configuration
echo "Creating database configuration..."
sudo tee /var/www/aeims/database_config.php > /dev/null << 'EOF'
<?php
/**
 * AEIMS Database Configuration
 * PostgreSQL connection for production
 */

// Load environment variables
if (file_exists(__DIR__ . '/.env')) {
    $env = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($env as $line) {
        if (strpos($line, '=') !== false && !str_starts_with($line, '#')) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

// Database configuration
$db_config = [
    'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
    'port' => $_ENV['DB_PORT'] ?? '5432',
    'dbname' => $_ENV['DB_NAME'] ?? 'aeims_core',
    'username' => $_ENV['DB_USER'] ?? 'aeims_user',
    'password' => $_ENV['DB_PASS'] ?? 'secure_password_123'
];

/**
 * Get database connection
 */
function getDbConnection() {
    global $db_config;

    $dsn = "pgsql:host={$db_config['host']};port={$db_config['port']};dbname={$db_config['dbname']}";

    try {
        $pdo = new PDO($dsn, $db_config['username'], $db_config['password']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw new PDOException("Database connection failed");
    }
}

/**
 * Test database connection
 */
function testDbConnection() {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->query("SELECT 1");
        return $stmt !== false;
    } catch (Exception $e) {
        error_log("Database test failed: " . $e->getMessage());
        return false;
    }
}
?>
EOF

# Create main application file with health check
echo "Creating main application file..."
sudo tee /var/www/aeims/index.php > /dev/null << 'EOF'
<?php
/**
 * AEIMS Main Application
 * Production version with PostgreSQL
 */

require_once 'database_config.php';

// Handle health check requests
if (isset($_GET['health']) || $_SERVER['REQUEST_URI'] === '/health' || $_SERVER['REQUEST_URI'] === '/?health') {
    header('Content-Type: application/json');

    try {
        $pdo = getDbConnection();
        $stmt = $pdo->query("SELECT version()");
        $version = $stmt->fetchColumn();

        echo json_encode([
            'status' => 'healthy',
            'database' => 'connected',
            'db_version' => $version,
            'timestamp' => date('c')
        ]);
    } catch (Exception $e) {
        http_response_code(503);
        echo json_encode([
            'status' => 'unhealthy',
            'error' => 'database_connection_failed',
            'message' => $e->getMessage(),
            'timestamp' => date('c')
        ]);
    }
    exit;
}

// Handle ping requests
if ($_SERVER['REQUEST_URI'] === '/ping') {
    header('Content-Type: text/plain');
    echo 'pong';
    exit;
}

// Main application
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AEIMS - Adult Entertainment Information Management System</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            text-align: center;
            margin: 0;
            padding: 50px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: rgba(255,255,255,0.1);
            padding: 40px;
            border-radius: 15px;
            backdrop-filter: blur(10px);
        }
        h1 {
            color: #ffffff;
            font-size: 2.5em;
            margin-bottom: 20px;
        }
        .subtitle {
            font-size: 1.2em;
            margin-bottom: 30px;
            opacity: 0.9;
        }
        .status {
            background: rgba(46, 204, 113, 0.2);
            border: 2px solid #2ecc71;
            padding: 20px;
            margin: 20px 0;
            border-radius: 10px;
        }
        .info {
            background: rgba(52, 152, 219, 0.2);
            border: 2px solid #3498db;
            padding: 15px;
            margin: 15px 0;
            border-radius: 8px;
        }
        .links {
            margin-top: 30px;
        }
        .links a {
            color: #ffffff;
            text-decoration: none;
            margin: 0 15px;
            padding: 10px 20px;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 25px;
            transition: all 0.3s ease;
            display: inline-block;
        }
        .links a:hover {
            background: rgba(255,255,255,0.2);
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>AEIMS</h1>
        <p class="subtitle">Adult Entertainment Information Management System</p>

        <div class="status">
            <h3>🟢 System Status: Online</h3>
            <p><strong>Database:</strong> PostgreSQL Connected</p>
            <p><strong>Environment:</strong> Production</p>
            <p><strong>Version:</strong> 2.1.0</p>
        </div>

        <div class="info">
            <h3>System Information</h3>
            <p><strong>Server Time:</strong> <?php echo date('Y-m-d H:i:s T'); ?></p>
            <p><strong>PHP Version:</strong> <?php echo PHP_VERSION; ?></p>
            <?php
            try {
                $pdo = getDbConnection();
                $stmt = $pdo->query("SELECT version()");
                $db_version = $stmt->fetchColumn();
                echo "<p><strong>Database:</strong> " . htmlspecialchars($db_version) . "</p>";
            } catch (Exception $e) {
                echo "<p><strong>Database:</strong> Connection Error</p>";
            }
            ?>
        </div>

        <div class="links">
            <a href="/admin">Admin Panel</a>
            <a href="/dashboard">Dashboard</a>
            <a href="/support">Support</a>
            <a href="/?health">Health Check</a>
        </div>

        <div style="margin-top: 40px; font-size: 0.9em; opacity: 0.7;">
            <p>Powered by AEIMS Framework | Secure • Scalable • Compliant</p>
        </div>
    </div>
</body>
</html>
EOF

# Create basic admin check page
echo "Creating admin functionality..."
sudo tee /var/www/aeims/admin.php > /dev/null << 'EOF'
<?php
require_once 'database_config.php';

// Simple admin check
if ($_GET['action'] ?? '' === 'test_db') {
    header('Content-Type: application/json');
    try {
        $pdo = getDbConnection();

        // Test basic query
        $stmt = $pdo->query("SELECT COUNT(*) as table_count FROM information_schema.tables WHERE table_schema = 'public'");
        $result = $stmt->fetch();

        echo json_encode([
            'status' => 'success',
            'tables' => $result['table_count'],
            'connection' => 'active'
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'error' => $e->getMessage()
        ]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>AEIMS Admin</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        .test-button {
            background: #3498db;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .result {
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <h1>AEIMS Administration</h1>
    <p>Database Status Dashboard</p>

    <button class="test-button" onclick="testDatabase()">Test Database Connection</button>

    <div id="result" class="result" style="display: none;"></div>

    <script>
    function testDatabase() {
        fetch('?action=test_db')
            .then(response => response.json())
            .then(data => {
                const result = document.getElementById('result');
                result.style.display = 'block';
                result.innerHTML = '<h3>Database Test Result:</h3><pre>' + JSON.stringify(data, null, 2) + '</pre>';
            })
            .catch(error => {
                const result = document.getElementById('result');
                result.style.display = 'block';
                result.innerHTML = '<h3>Error:</h3><p>' + error.message + '</p>';
            });
    }
    </script>
</body>
</html>
EOF

# Update PHP-FPM configuration
echo "Updating PHP-FPM configuration..."
sudo tee /etc/php/8.2/fpm/pool.d/aeims.conf > /dev/null << EOF
[aeims]
user = www-data
group = www-data
listen = /var/run/php/php8.2-fpm-aeims.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660

pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35

; Environment variables
env[DB_HOST] = $DB_HOST
env[DB_PORT] = $DB_PORT
env[DB_NAME] = $DB_NAME
env[DB_USER] = $DB_USER
env[DB_PASS] = $DB_PASS
env[ENVIRONMENT] = prod
env[DOMAIN_NAME] = aeims.app

; Error logging
php_admin_value[error_log] = /var/log/php/aeims_errors.log
php_admin_flag[log_errors] = on
EOF

# Create PHP error log directory
sudo mkdir -p /var/log/php
sudo chown www-data:www-data /var/log/php

# Update Nginx configuration
echo "Updating Nginx configuration..."
sudo tee /etc/nginx/sites-available/aeims.app > /dev/null << 'EOF'
server {
    listen 80;
    listen [::]:80;
    server_name aeims.app www.aeims.app admin.aeims.app api.aeims.app support.aeims.app;

    root /var/www/aeims;
    index index.php index.html;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    add_header Content-Security-Policy "default-src 'self'" always;

    # Health check endpoint
    location = /health {
        try_files $uri $uri/ /index.php?health=1;
    }

    # Main location block
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP processing
    location ~ \.php$ {
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass unix:/var/run/php/php8.2-fpm-aeims.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;

        # Increase timeouts for database operations
        fastcgi_connect_timeout 300s;
        fastcgi_send_timeout 300s;
        fastcgi_read_timeout 300s;
    }

    # Deny access to sensitive files
    location ~ /\. {
        deny all;
    }

    location ~ /\.env {
        deny all;
    }

    # Static files caching
    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # Access logs
    access_log /var/log/nginx/aeims_access.log;
    error_log /var/log/nginx/aeims_error.log;
}
EOF

# Enable the site
sudo ln -sf /etc/nginx/sites-available/aeims.app /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default

# Set proper file permissions
echo "Setting file permissions..."
sudo chown -R www-data:www-data /var/www/aeims
sudo chmod -R 755 /var/www/aeims
sudo chmod 644 /var/www/aeims/.env

# Test configurations
echo "Testing configurations..."
sudo nginx -t
if [ $? -ne 0 ]; then
    echo "ERROR: Nginx configuration test failed!"
    exit 1
fi

sudo php-fpm8.2 -t
if [ $? -ne 0 ]; then
    echo "ERROR: PHP-FPM configuration test failed!"
    exit 1
fi

# Restart services
echo "Restarting services..."
sudo systemctl restart php8.2-fpm
sudo systemctl restart nginx

# Wait for services to start
sleep 5

# Test local connectivity
echo "Testing local connectivity..."
if curl -f -s http://localhost/?health > /dev/null; then
    echo "✓ Local health check passed"
else
    echo "✗ Local health check failed"
    curl -v http://localhost/?health || true
fi

# Test database connection
echo "Testing database connection..."
if php -r "
require_once '/var/www/aeims/database_config.php';
try {
    \$pdo = getDbConnection();
    \$stmt = \$pdo->query('SELECT 1');
    echo 'Database connection successful\n';
} catch (Exception \$e) {
    echo 'Database connection failed: ' . \$e->getMessage() . '\n';
    exit(1);
}
"; then
    echo "✓ Database connection test passed"
else
    echo "✗ Database connection test failed"
fi

echo "AEIMS PostgreSQL deployment completed successfully!"
echo "Services status:"
sudo systemctl status nginx --no-pager -l
sudo systemctl status php8.2-fpm --no-pager -l

echo ""
echo "You can test the application with:"
echo "  curl http://localhost/?health"
echo "  curl http://localhost/admin?action=test_db"