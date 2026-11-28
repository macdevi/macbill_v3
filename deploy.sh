#!/usr/bin/env bash
# Rilis di server: jalankan dari folder proyek sebagai user deploy.
set -euo pipefail
cd "$(dirname "$0")"

php artisan down --retry=10 || true
trap 'php artisan up' EXIT

git pull --ff-only
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build
php artisan migrate --force
php artisan optimize

sudo systemctl reload php8.3-fpm
echo "Rilis selesai: $(git rev-parse --short HEAD)"
