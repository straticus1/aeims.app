# AEIMS Platform Deployment Guide

## Overview

This guide covers the deployment of AEIMS Platform v2.3.0 with complete multi-site infrastructure, virtual host configuration, and production-ready containerization.

## Prerequisites

- Docker and Docker Compose
- Nginx or reverse proxy capability
- SSL certificates for HTTPS
- PHP 8.2+ with required extensions
- File system permissions for data directories

## Quick Start

### 1. Clone and Setup

```bash
git clone <repository-url> aeims-platform
cd aeims-platform
chmod +x infrastructure/docker-entrypoint.sh
```

### 2. Configure Environment

```bash
cp config.php.example config.php
# Edit config.php with your database and site configurations
```

### 3. Deploy with Docker

```bash
cd infrastructure
docker-compose up -d
```

### 4. Configure Virtual Hosts

Copy the nginx configuration:

```bash
cp infrastructure/docker/nginx.conf /etc/nginx/sites-available/aeims
ln -s /etc/nginx/sites-available/aeims /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx
```

## Site Configuration

### Individual Site Setup

Each site in the `sites/` directory requires:

1. Site-specific configuration in `config.json`
2. Authentication setup in `auth.php`
3. SSO middleware configuration
4. Domain-specific SSL certificates

### Multi-Site Management

- All sites share core services but maintain separate configurations
- SSO enables cross-site authentication
- Virtual host routing handles domain-specific requests
- Centralized logging and monitoring

## Security

### Authentication

- Site-specific authentication with unified customer management
- Operator authentication with role-based access
- Session management with configurable timeouts
- CSRF protection and XSS prevention

### SSL/TLS

- Required for all production deployments
- Let's Encrypt integration available
- Proper certificate management for multiple domains

## Monitoring and Maintenance

### Health Checks

The platform includes health check endpoints:

- `/health.php` - Overall system health
- Site-specific health checks per domain

### Logging

- Centralized logging in `includes/Logger.php`
- Site-specific logs in individual site directories
- Error tracking and debugging capabilities

### Backups

- Automated data backup procedures
- Configuration backup and restore
- Database backup for critical data

## Troubleshooting

### Common Issues

1. **Dashboard Blank Pages**: Ensure proper include paths and file permissions
2. **Virtual Host Routing**: Check nginx configuration and DNS settings
3. **Authentication Issues**: Verify session configuration and cookie settings
4. **Container Networking**: Ensure proper port mapping and service discovery

### Debug Mode

Enable debug mode in `config.php`:

```php
$config['debug'] = true;
$config['error_reporting'] = E_ALL;
```

## Support

For deployment assistance:
- Email: rjc@afterdarksys.com
- Documentation: See additional files in `/docs/`
- Response time: Within 24 hours