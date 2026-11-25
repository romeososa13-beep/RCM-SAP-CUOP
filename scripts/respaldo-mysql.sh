#!/usr/bin/env bash
# Respaldo diario de MySQL comprimido; conserva 30 días.
#   Cron (root):  30 2 * * * /var/www/erp-cuop/scripts/respaldo-mysql.sh
# Requiere /root/.my.cnf con [client] user=... password=... (no deje la contraseña en este archivo).
set -euo pipefail
DESTINO=${RESPALDO_DIR:-/var/backups/erp-cuop}
BASE=${DB_DATABASE:-erp_cuop}
mkdir -p "$DESTINO"
ARCHIVO="$DESTINO/${BASE}-$(date +%Y%m%d-%H%M).sql.gz"
mysqldump --single-transaction --routines --triggers --no-tablespaces "$BASE" | gzip -9 > "$ARCHIVO"
gzip -t "$ARCHIVO"                                   # verifica que el respaldo se pueda leer
find "$DESTINO" -name "${BASE}-*.sql.gz" -mtime +30 -delete
echo "Respaldo OK: $ARCHIVO ($(du -h "$ARCHIVO" | cut -f1))"
