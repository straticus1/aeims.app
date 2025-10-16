#!/bin/bash
set -e

echo "=== AEIMS Container Starting ==="

# Run auto-migration
echo "Running auto-migration..."
php /var/www/html/auto-migrate.php || true

echo "Starting supervisor..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
