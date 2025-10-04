# AEIMS Application Dockerfile
# Creates a containerized version of the AEIMS application

FROM ubuntu:22.04

# Prevent interactive prompts during package installation
ENV DEBIAN_FRONTEND=noninteractive

# Set timezone
ENV TZ=UTC

# Install system packages
RUN apt-get update && apt-get install -y \
    software-properties-common \
    ca-certificates \
    lsb-release \
    apt-transport-https \
    curl \
    wget \
    unzip \
    git \
    && add-apt-repository ppa:ondrej/php \
    && apt-get update \
    && apt-get install -y \
    nginx \
    php8.2 \
    php8.2-fpm \
    php8.2-mysql \
    php8.2-redis \
    php8.2-curl \
    php8.2-mbstring \
    php8.2-xml \
    php8.2-zip \
    php8.2-gd \
    php8.2-intl \
    php8.2-bcmath \
    redis-server \
    supervisor \
    && rm -rf /var/lib/apt/lists/*

# Create application directory and backend component directories (outside web root)
RUN mkdir -p /var/www/aeims /opt/aeims-backend/aeims /opt/aeims-backend/aeimsLib /opt/aeims-backend/aeims-control

# Copy web application files from aeims.app
COPY . /var/www/aeims/

# Copy the AEIMS core system from parent directory
# Note: These are handled by the backend services in production
# COPY ../aeims/services/ /var/www/aeims/services/
# COPY ../aeims/sites/ /var/www/aeims/sites/

# Backend components will be connected via network - no need to copy them into this container

# Set proper ownership
RUN chown -R www-data:www-data /var/www/aeims

# Backend components are accessed via network - update integration to use HTTP endpoints
RUN if [ -f /var/www/aeims/includes/AeimsIntegration.php ]; then \
    sed -i 's|dirname(__DIR__) . \x27/aeims\x27|"http://aeims-core:8000"|g' /var/www/aeims/includes/AeimsIntegration.php && \
    sed -i 's|dirname(__DIR__) . \x27/aeimsLib\x27|"http://aeims-lib:8080"|g' /var/www/aeims/includes/AeimsIntegration.php; \
    fi

# Copy multi-site nginx configuration
COPY infrastructure/docker/nginx.conf /etc/nginx/sites-available/default

# Remove default PHP-FPM pool and copy our configuration
RUN rm -f /etc/php/8.2/fpm/pool.d/www.conf
COPY infrastructure/docker/php-fpm.conf /etc/php/8.2/fpm/pool.d/www.conf

# Copy supervisor configuration
COPY infrastructure/docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Create log directories and ensure proper permissions
RUN mkdir -p /var/log/nginx /var/log/php /var/log/supervisor /var/run/php \
    && chown -R www-data:www-data /var/log/nginx \
    && chown -R www-data:www-data /var/log/php \
    && chown -R www-data:www-data /var/run/php

# Remove default nginx site and enable our site
RUN rm -f /etc/nginx/sites-enabled/default
RUN ln -s /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default

# Set proper permissions for application
RUN chmod -R 755 /var/www/aeims \
    && chown -R www-data:www-data /var/www/aeims

# Test nginx configuration
RUN nginx -t

# Expose port 80
EXPOSE 80

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/ || exit 1

# Start supervisor which will manage nginx, php-fpm, and redis
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]