FROM php:8.1-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    supervisor \
    redis-server \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd \
    && pecl install redis \
    && docker-php-ext-enable redis

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . /var/www/html/

# Skip composer install for now - no composer.json exists

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Create supervisor config for multi-process
RUN echo '[supervisord]' > /etc/supervisor/conf.d/supervisord.conf \
    && echo 'nodaemon=true' >> /etc/supervisor/conf.d/supervisord.conf \
    && echo '' >> /etc/supervisor/conf.d/supervisord.conf \
    && echo '[program:apache]' >> /etc/supervisor/conf.d/supervisord.conf \
    && echo 'command=/usr/sbin/apache2ctl -D FOREGROUND' >> /etc/supervisor/conf.d/supervisord.conf \
    && echo 'autostart=true' >> /etc/supervisor/conf.d/supervisord.conf \
    && echo 'autorestart=true' >> /etc/supervisor/conf.d/supervisord.conf \
    && echo '' >> /etc/supervisor/conf.d/supervisord.conf \
    && echo '[program:redis]' >> /etc/supervisor/conf.d/supervisord.conf \
    && echo 'command=redis-server' >> /etc/supervisor/conf.d/supervisord.conf \
    && echo 'autostart=true' >> /etc/supervisor/conf.d/supervisord.conf \
    && echo 'autorestart=true' >> /etc/supervisor/conf.d/supervisord.conf

# Configure Apache virtual hosts and mod_rewrite
RUN a2enmod rewrite \
    && echo '<VirtualHost *:80>' > /etc/apache2/sites-available/000-default.conf \
    && echo '    DocumentRoot /var/www/html' >> /etc/apache2/sites-available/000-default.conf \
    && echo '    ServerName _' >> /etc/apache2/sites-available/000-default.conf \
    && echo '    AllowEncodedSlashes On' >> /etc/apache2/sites-available/000-default.conf \
    && echo '    <Directory /var/www/html>' >> /etc/apache2/sites-available/000-default.conf \
    && echo '        Options Indexes FollowSymLinks' >> /etc/apache2/sites-available/000-default.conf \
    && echo '        AllowOverride All' >> /etc/apache2/sites-available/000-default.conf \
    && echo '        Require all granted' >> /etc/apache2/sites-available/000-default.conf \
    && echo '    </Directory>' >> /etc/apache2/sites-available/000-default.conf \
    && echo '</VirtualHost>' >> /etc/apache2/sites-available/000-default.conf

# Create log directories and configure Redis sessions
RUN mkdir -p /var/log/aeims \
    && mkdir -p /var/lib/php/sessions \
    && chown -R www-data:www-data /var/lib/php/sessions \
    && chmod -R 700 /var/lib/php/sessions \
    && echo "session.save_handler = redis" > /usr/local/etc/php/conf.d/sessions.ini \
    && echo "session.save_path = \"tcp://127.0.0.1:6379\"" >> /usr/local/etc/php/conf.d/sessions.ini

# Expose port 80
EXPOSE 80

# Start supervisor (which starts apache and redis)
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]