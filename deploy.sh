#!/usr/bin/env bash
# Rilis di server: jalankan dari folder proyek sebagai user deploy.
set -euo pipefail
cd "$(dirname "$0")"

# Versi PHP-FPM mengikuti versi php CLI (mis. 8.4), bukan angka yang dikunci.
PHP_MM="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"

php artisan down --retry=10 || true
trap 'php artisan up' EXIT

git pull --ff-only
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build
php artisan migrate --force
php artisan optimize

# Jika dijalankan sebagai root, pastikan web server tetap bisa menulis.
if [ "$(id -u)" -eq 0 ]; then
    chown -R www-data:www-data storage bootstrap/cache
fi

sudo systemctl reload "php${PHP_MM}-fpm"
echo "Rilis selesai: $(git rev-parse --short HEAD)"
