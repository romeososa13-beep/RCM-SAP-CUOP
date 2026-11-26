# Imagen del ERP para uso local (Windows con Docker Desktop, o cualquier equipo con Docker).
# Un solo contenedor: nginx + PHP-FPM + programador de tareas + cola de envíos (supervisord).

# 1) Dependencias de PHP
FROM composer:2.8 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-autoloader --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative --no-scripts

# 2) Estilos y JavaScript (Tailwind revisa también vistas de app/ y vendor/)
FROM node:22-bookworm-slim AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY --from=vendor /app /app
RUN npm run build

# 3) Imagen final: Ubuntu 24.04 (trae PHP 8.3 oficial, sin compilar extensiones)
FROM ubuntu:24.04
ENV DEBIAN_FRONTEND=noninteractive TZ=America/Mexico_City
RUN apt-get update \
 && apt-get install -y --no-install-recommends ca-certificates tzdata nginx supervisor \
      php8.3-fpm php8.3-cli php8.3-mysql php8.3-intl php8.3-zip php8.3-gd php8.3-bcmath \
      php8.3-mbstring php8.3-xml php8.3-curl php8.3-opcache \
 && rm -rf /var/lib/apt/lists/* \
 && rm -f /etc/nginx/sites-enabled/default \
 && mkdir -p /run/php

COPY docker/php.ini /etc/php/8.3/fpm/conf.d/99-erp.ini
COPY docker/php.ini /etc/php/8.3/cli/conf.d/99-erp.ini
COPY docker/php-fpm.conf /etc/php/8.3/fpm/pool.d/zz-erp.conf
COPY docker/nginx.conf /etc/nginx/sites-enabled/erp.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/erp.conf
COPY docker/entrypoint.sh /usr/local/bin/erp-entrypoint

WORKDIR /var/www
COPY --from=vendor --chown=www-data:www-data /app /var/www
COPY --from=assets --chown=www-data:www-data /app/public/build /var/www/public/build
RUN chmod +x /usr/local/bin/erp-entrypoint \
 && rm -f /var/www/.env /var/www/public/hot /var/www/bootstrap/cache/*.php \
 && cd /var/www && APP_KEY=base64:$(head -c 32 /dev/urandom | base64) php artisan package:discover \
 && mkdir -p /var/www/importar

EXPOSE 80
ENTRYPOINT ["erp-entrypoint"]
CMD ["supervisord", "-n", "-c", "/etc/supervisor/supervisord.conf"]
