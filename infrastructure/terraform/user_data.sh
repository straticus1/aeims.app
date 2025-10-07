#!/bin/bash
# AEIMS EC2 User Data Script
# This script runs on first boot to set up the AEIMS environment

set -e

# Variables from Terraform
DOMAIN_NAME="${domain_name}"
ENVIRONMENT="${environment}"

# Log all output
exec > >(tee /var/log/user-data.log|logger -t user-data -s 2>/dev/console) 2>&1

echo "Starting AEIMS setup for domain: $DOMAIN_NAME"
echo "Environment: $ENVIRONMENT"
echo "Date: $(date)"

# Update system
echo "Updating system packages..."
apt-get update -y
apt-get upgrade -y

# Install essential packages
echo "Installing essential packages..."
apt-get install -y \
    nginx \
    php8.2 \
    php8.2-fpm \
    php8.2-pgsql \
    php8.2-redis \
    php8.2-curl \
    php8.2-json \
    php8.2-mbstring \
    php8.2-xml \
    php8.2-zip \
    php8.2-gd \
    postgresql-client \
    redis-server \
    git \
    curl \
    wget \
    unzip \
    certbot \
    python3-certbot-nginx \
    awscli \
    htop \
    fail2ban \
    ufw

# Create application directory
echo "Creating application directory..."
mkdir -p /var/www/aeims
chown -R www-data:www-data /var/www/aeims

# Start and enable services
echo "Starting services..."
systemctl start nginx
systemctl start php8.2-fpm
systemctl start redis-server

systemctl enable nginx
systemctl enable php8.2-fpm
systemctl enable redis-server

# Note: PostgreSQL server runs on AEIMS Core, not locally

# Configure firewall
echo "Configuring firewall..."
ufw allow ssh
ufw allow 'Nginx Full'
ufw --force enable

# Create a basic nginx configuration
echo "Configuring nginx..."
cat > /etc/nginx/sites-available/default << EOF
server {
    listen 80;
    server_name $DOMAIN_NAME www.$DOMAIN_NAME;

    root /var/www/aeims;
    index index.php index.html;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
    }
}
EOF

# Restart nginx to apply configuration
systemctl restart nginx

# Create a simple index file
echo "Creating index file..."
cat > /var/www/aeims/index.html << EOF
<!DOCTYPE html>
<html>
<head>
    <title>AEIMS - Coming Soon</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; margin-top: 100px; }
        h1 { color: #ef4444; }
    </style>
</head>
<body>
    <h1>AEIMS</h1>
    <p>Adult Entertainment Information Management System</p>
    <p>Server setup completed. Domain: $DOMAIN_NAME</p>
    <p>Ready for application deployment.</p>
</body>
</html>
EOF

chown www-data:www-data /var/www/aeims/index.html

echo "AEIMS infrastructure setup completed successfully!"
echo "Server is ready for application deployment."
echo "Domain: $DOMAIN_NAME"
echo "Environment: $ENVIRONMENT"
echo "Setup completed at: $(date)"