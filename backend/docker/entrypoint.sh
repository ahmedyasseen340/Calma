#!/bin/sh
set -e

# Apache must listen on the port the host gives us
PORT="${PORT:-10000}"
sed -ri "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# Optional: run the new migrations when the server starts (RUN_MIGRATIONS=true). Off by default:
# on the shared database only one person (or only the server) should migrate.
if [ "${RUN_MIGRATIONS}" = "true" ]; then
    php artisan migrate --force
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache

exec apache2-foreground
