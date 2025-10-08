#!/bin/bash
set -e

# Generate Apache virtual host with runtime environment variables and proper virtual host routing
cat > /etc/apache2/sites-available/000-default.conf << 'EOF'
<VirtualHost *:80>
    DocumentRoot /var/www/aeims
    ServerName localhost

    # Pass environment variables to PHP
    SetEnv DB_HOST "${DB_HOST:-aeims-postgres}"
    SetEnv DB_PORT "${DB_PORT:-5432}"
    SetEnv DB_NAME "${DB_NAME:-aeims_core}"
    SetEnv DB_USER "${DB_USER:-aeims_user}"
    SetEnv DB_PASS "${DB_PASS:-secure_password_123}"
    SetEnv DATABASE_HOST "${DB_HOST:-aeims-postgres}"
    SetEnv DATABASE_PORT "${DB_PORT:-5432}"
    SetEnv DATABASE_NAME "${DB_NAME:-aeims_core}"
    SetEnv DATABASE_USER "${DB_USER:-aeims_user}"
    SetEnv DATABASE_PASS "${DB_PASS:-secure_password_123}"

    <Directory /var/www/aeims>
        AllowOverride All
        Require all granted
        DirectoryIndex index.php router.php

        # Enable rewrite engine for virtual host routing
        RewriteEngine On

        # Route all requests to router.php for virtual host handling
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteRule ^(.*)$ router.php [L,QSA]

        # Special handling for API routes
        RewriteRule ^api/admin/sites(/.*)?$ api/admin/sites.php [L,QSA]

        # Health check endpoint
        RewriteRule ^health$ health.php [L]
    </Directory>

    # Enable error and access logging
    ErrorLog ${APACHE_LOG_DIR}/error.log
    CustomLog ${APACHE_LOG_DIR}/access.log combined

    # Enable rewrite module
    LoadModule rewrite_module modules/mod_rewrite.so
</VirtualHost>
EOF

# Enable rewrite module
a2enmod rewrite

# Start Apache in foreground
exec apache2-foreground