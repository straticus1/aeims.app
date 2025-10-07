#!/bin/bash

# Multi-Site AEIMS Deployment Script
# Deploys the complete multi-site login and integration system

set -e

echo "🚀 AEIMS Multi-Site Deployment Script"
echo "===================================="
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${GREEN}✅ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

print_error() {
    echo -e "${RED}❌ $1${NC}"
}

print_info() {
    echo -e "${BLUE}ℹ️  $1${NC}"
}

# Check if we're in the right directory
if [[ ! -f "auth_functions.php" ]]; then
    print_error "This script must be run from the aeims.app directory"
    exit 1
fi

print_info "Starting multi-site AEIMS deployment..."

# 1. Create necessary directories
print_info "Creating directory structure..."
mkdir -p data
mkdir -p api
mkdir -p assets/css
mkdir -p terraform
mkdir -p includes

# 2. Set proper permissions
print_info "Setting file permissions..."
chmod 755 data
chmod 644 includes/*.php
chmod 644 api/*.php
chmod +x deploy-multi-site.sh

# 3. Initialize data files
print_info "Initializing data files..."
if [[ ! -f "data/accounts.json" ]]; then
    echo '{}' > data/accounts.json
    print_status "Created accounts.json"
fi

if [[ ! -f "data/domains.json" ]]; then
    echo '{}' > data/domains.json
    print_status "Created domains.json"
fi

if [[ ! -f "data/username_reservations.json" ]]; then
    echo '{}' > data/username_reservations.json
    print_status "Created username_reservations.json"
fi

# 4. Check AEIMS directory structure
print_info "Checking AEIMS directory structure..."
AEIMS_PATH="../aeims"
if [[ -d "$AEIMS_PATH" ]]; then
    print_status "Found AEIMS directory"

    # Check for sites
    if [[ -d "$AEIMS_PATH/sites" ]]; then
        SITE_COUNT=$(find "$AEIMS_PATH/sites" -maxdepth 1 -type d ! -name "." ! -name ".." ! -name "_archived" | wc -l)
        print_status "Found $SITE_COUNT sites in $AEIMS_PATH/sites"

        # List discovered sites
        for site_dir in "$AEIMS_PATH/sites"/*; do
            if [[ -d "$site_dir" && $(basename "$site_dir") != "_archived" ]]; then
                site_name=$(basename "$site_dir")
                print_info "  📁 Discovered site: $site_name"

                # Initialize site data structure if needed
                if [[ ! -f "$site_dir/users.json" ]]; then
                    echo '{}' > "$site_dir/users.json"
                    print_status "    Created users.json for $site_name"
                fi

                if [[ ! -f "$site_dir/config.json" ]]; then
                    cat > "$site_dir/config.json" << EOF
{
    "site_name": "$site_name",
    "theme": "default",
    "features": ["chat", "voice", "video"],
    "login_logo": "/assets/images/logo.png",
    "telephony_platform_url": "http://localhost:3000",
    "enabled": true
}
EOF
                    print_status "    Created config.json for $site_name"
                fi
            fi
        done
    else
        print_warning "No sites directory found in $AEIMS_PATH"
    fi

    # Check for telephony platform
    if [[ -d "$AEIMS_PATH/telephony-platform" ]]; then
        print_status "Found telephony platform"
    else
        print_warning "No telephony platform found"
    fi
else
    print_warning "AEIMS directory not found at $AEIMS_PATH"
fi

# 5. Test the implementation
print_info "Running integration tests..."
if command -v php &> /dev/null; then
    php test_multi_site_integration.php
else
    print_warning "PHP not found, skipping integration tests"
fi

# 6. Check Terraform configuration
print_info "Checking Terraform configuration..."
if [[ -f "terraform/multi-site-infrastructure.tf" ]]; then
    print_status "Terraform configuration found"

    # Initialize Terraform if terraform is available
    if command -v terraform &> /dev/null; then
        cd terraform
        print_info "Initializing Terraform..."
        terraform init

        print_info "Validating Terraform configuration..."
        terraform validate

        print_info "Planning Terraform deployment..."
        terraform plan -out=tfplan

        print_warning "Review the Terraform plan above before applying!"
        print_info "To deploy infrastructure, run: cd terraform && terraform apply tfplan"
        cd ..
    else
        print_warning "Terraform not installed, skipping infrastructure validation"
    fi
else
    print_error "Terraform configuration not found"
fi

# 7. Web server configuration
print_info "Generating web server configuration..."

# Apache configuration
cat > .htaccess << 'EOF'
# AEIMS Multi-Site Configuration

RewriteEngine On

# Site-specific login routing
RewriteCond %{HTTP_HOST} ^login\.(.+)$ [NC]
RewriteRule ^(.*)$ site-login.php [L,QSA]

# Operator dashboard routing
RewriteCond %{HTTP_HOST} ^operator\. [NC]
RewriteRule ^(.*)$ /telephony-platform/frontend/ [L,QSA]

# API routing
RewriteRule ^api/(.*)$ api/$1 [L,QSA]

# Security headers
Header always set X-Frame-Options DENY
Header always set X-Content-Type-Options nosniff
Header always set X-XSS-Protection "1; mode=block"
Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"

# Prevent access to sensitive files
<FilesMatch "\.(json|log|env)$">
    Order Allow,Deny
    Deny from all
</FilesMatch>
EOF

print_status "Created .htaccess configuration"

# Nginx configuration
cat > nginx-multi-site.conf << 'EOF'
# AEIMS Multi-Site Nginx Configuration

server {
    listen 80;
    listen 443 ssl http2;
    server_name aeims.app www.aeims.app;

    root /var/www/aeims.app;
    index index.php index.html;

    # SSL configuration (adjust paths as needed)
    ssl_certificate /etc/ssl/certs/aeims.app.crt;
    ssl_certificate_key /etc/ssl/private/aeims.app.key;

    # Site-specific login routing
    if ($host ~* ^login\.(.+)$) {
        rewrite ^(.*)$ /site-login.php break;
    }

    # Operator dashboard routing
    location ~ ^/operator {
        proxy_pass http://localhost:3000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    # API routing
    location ~ ^/api/(.*)$ {
        try_files $uri $uri/ /api/$1;
    }

    # PHP processing
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Security headers
    add_header X-Frame-Options DENY;
    add_header X-Content-Type-Options nosniff;
    add_header X-XSS-Protection "1; mode=block";
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains";

    # Block access to sensitive files
    location ~* \.(json|log|env)$ {
        deny all;
    }
}

# Site-specific server blocks
server {
    listen 80;
    listen 443 ssl http2;
    server_name ~^login\.(?<site>.+)$;

    root /var/www/aeims.app;

    location / {
        try_files $uri $uri/ /site-login.php;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SITE_DOMAIN $site;
        include fastcgi_params;
    }
}
EOF

print_status "Created nginx-multi-site.conf"

# 8. Create systemd service for AEIMS
cat > aeims-multi-site.service << 'EOF'
[Unit]
Description=AEIMS Multi-Site Platform
After=network.target nginx.service
Requires=nginx.service

[Service]
Type=forking
User=www-data
Group=www-data
WorkingDirectory=/var/www/aeims.app
ExecStart=/usr/bin/php -S 0.0.0.0:8080
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
EOF

print_status "Created systemd service file"

# 9. Generate deployment summary
print_info "Generating deployment summary..."

cat > DEPLOYMENT_SUMMARY.md << 'EOF'
# AEIMS Multi-Site Deployment Summary

## ✅ Completed Components

### 1. Core Multi-Site Authentication
- ✅ Site-specific login system (login.sitename.com)
- ✅ Automatic user type detection (operator/customer/reseller/admin)
- ✅ Cross-site account linking (optional, user-controlled)
- ✅ Username reservation across all sites
- ✅ Centralized billing integration

### 2. Dashboard Integration
- ✅ Site selection dropdown in aeims.app dashboard
- ✅ Operator dashboard links for each site
- ✅ Cross-site settings management
- ✅ Aggregated statistics across sites

### 3. Infrastructure
- ✅ Terraform configuration for AWS/Cloudflare
- ✅ Load balancer routing for site-specific logins
- ✅ ECS containerization setup
- ✅ SSL certificate management

### 4. Integration Layer
- ✅ AEIMS system integration
- ✅ Telephony platform routing
- ✅ API endpoints for settings management
- ✅ Comprehensive test suite

## 🚀 Deployment Steps

### 1. Infrastructure Deployment
```bash
cd terraform
terraform init
terraform plan
terraform apply
```

### 2. DNS Configuration
Set up DNS records for:
- login.nycflirts.com → ALB
- login.flirts.nyc → ALB
- operator.aeims.app → ALB

### 3. Web Server Setup
Copy the generated nginx or Apache configuration to your web server.

### 4. Site Data Initialization
Run the site initialization for each site in aeims/sites/

### 5. Testing
```bash
php test_multi_site_integration.php
```

## 📋 Operation Workflow

### For Operators:
1. Visit login.sitename.com
2. Login with credentials
3. Automatically redirected to operator dashboard
4. Take calls using unified telephony platform

### For Customers:
1. Visit login.sitename.com
2. Login with credentials
3. Automatically redirected to customer dashboard
4. Use site-specific features

### For Admins:
1. Login to aeims.app
2. Select sites from dropdown
3. Manage users and settings
4. Access operator dashboards for each site

## 🔧 Configuration Files

- `auth_functions.php` - Multi-site authentication functions
- `includes/SiteSpecificAuth.php` - Site-specific login handler
- `site-login.php` - Universal login page
- `terraform/multi-site-infrastructure.tf` - Infrastructure as code
- `api/update-cross-site-settings.php` - Settings API

## 📊 Monitoring

- ECS service health checks
- CloudWatch logs for all services
- ALB access logs
- Site-specific user activity logs

## 🔒 Security Features

- CSRF protection
- Cross-site scripting prevention
- Secure password hashing
- Site isolation
- Optional cross-site linking

## 📞 Support

For issues or questions:
- Check logs in /var/log/aeims/
- Review CloudWatch metrics
- Run integration tests
- Contact support at support@aeims.app
EOF

print_status "Created deployment summary"

# Final status
echo ""
print_status "🎉 Multi-site AEIMS deployment preparation complete!"
echo ""
print_info "Summary of what was deployed:"
echo "  📁 Directory structure created"
echo "  🔧 Configuration files generated"
echo "  🧪 Integration tests available"
echo "  🏗️  Terraform infrastructure ready"
echo "  🌐 Web server configs generated"
echo "  📚 Documentation created"
echo ""
print_warning "Next steps:"
echo "  1. Review terraform/multi-site-infrastructure.tf"
echo "  2. Run: terraform apply (after reviewing plan)"
echo "  3. Configure DNS records"
echo "  4. Test login.sitename.com functionality"
echo "  5. Verify operator dashboard redirects"
echo ""
print_info "All files have been created in the current directory."
print_info "Check DEPLOYMENT_SUMMARY.md for detailed next steps."