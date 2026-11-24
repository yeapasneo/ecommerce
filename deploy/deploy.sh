#!/usr/bin/env bash
# Pull the latest code and rebuild. Run from the app folder as the deploy user:  bash deploy/deploy.sh
set -euo pipefail

php artisan down --refresh=15 || true
git pull --ff-only origin main
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build
php artisan migrate --force
php artisan optimize
php artisan storage:link 2>/dev/null || true
php artisan queue:restart
php artisan up
echo "Deployed $(git rev-parse --short HEAD)"
