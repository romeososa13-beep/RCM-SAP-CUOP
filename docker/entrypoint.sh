#!/usr/bin/env bash
# Arranque del contenedor: espera la base, prepara llaves y carpetas, migra y crea el catálogo.
set -euo pipefail
cd /var/www

mkdir -p storage/app/public storage/framework/{cache,sessions,views} storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# Llave de cifrado: se genera una sola vez y se guarda en el volumen de datos.
# Se valida el formato: un archivo dañado o con texto de error se reemplaza.
llave_valida() { [[ "$1" =~ ^base64:[A-Za-z0-9+/]{43}=$ ]]; }
if [ -z "${APP_KEY:-}" ]; then
    LLAVE="$(cat storage/app/.app_key 2>/dev/null || true)"
    if ! llave_valida "$LLAVE"; then
        LLAVE="$(php -r 'echo "base64:".base64_encode(random_bytes(32));')"
        llave_valida "$LLAVE" || { echo "No se pudo generar APP_KEY"; exit 1; }
        printf '%s' "$LLAVE" > storage/app/.app_key
        chmod 600 storage/app/.app_key
        echo "Se generó una llave de cifrado nueva."
    fi
    export APP_KEY="$LLAVE"
fi

echo "Esperando la base de datos…"
for i in $(seq 1 60); do
    php -r 'try { new PDO("mysql:host=".getenv("DB_HOST").";port=3306;dbname=".getenv("DB_DATABASE"), getenv("DB_USERNAME"), getenv("DB_PASSWORD")); exit(0); } catch (Throwable $e) { exit(1); }' && break
    sleep 2
done

php artisan migrate --force

# Primer arranque: catálogo real (roles, sedes con Loreto activa, carreras, planes) y administrador.
if [ "$(php artisan tinker --execute='echo Spatie\Permission\Models\Role::count();')" = "0" ]; then
    php artisan db:seed --class=CatalogosSeeder --force
fi
if [ "$(php artisan tinker --execute='echo App\Models\User::count();')" = "0" ] && [ -n "${ADMIN_EMAIL:-}" ] && [ -n "${ADMIN_PASSWORD:-}" ]; then
    php artisan tinker --execute='$u = App\Models\User::create(["name" => getenv("ADMIN_NOMBRE") ?: "Administrador", "email" => strtolower(getenv("ADMIN_EMAIL")), "password" => bcrypt(getenv("ADMIN_PASSWORD")), "email_verified_at" => now()]); $u->assignRole("Administrador"); echo "Administrador creado: ".$u->email.PHP_EOL;'
fi

php artisan optimize
php artisan storage:link >/dev/null 2>&1 || true
chown -R www-data:www-data storage bootstrap/cache

echo "ERP listo en http://localhost:${ERP_PUERTO:-8080}"
exec "$@"
