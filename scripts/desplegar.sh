#!/usr/bin/env bash
# Actualiza el sistema en el servidor con lo último de la rama main.
#   Uso (como el usuario dueño de /var/www/erp-cuop): scripts/desplegar.sh
set -euo pipefail
cd "$(dirname "$0")/.."

php artisan down --retry=30 || true
git pull --ff-only origin main
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan optimize          # cachea config, rutas y vistas
php artisan queue:restart     # el worker toma el código nuevo
php artisan up
echo "Listo: $(git log --oneline -1)"
