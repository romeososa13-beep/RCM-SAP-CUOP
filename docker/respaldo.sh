#!/usr/bin/env bash
# Respaldo de la base de datos a la carpeta «respaldos» del equipo (Google Drive la sube a la nube).
#   (sin argumentos)       bucle: cada hora revisa si ya hay respaldo de las últimas 20 h; si no, lo hace
#   --ahora                respaldo inmediato
#   --restaurar ARCHIVO    restaura ARCHIVO (de la carpeta respaldos) sobre la base actual
set -euo pipefail
DIR=/respaldos
export MYSQL_PWD="$MYSQL_ROOT_PASSWORD"
BASE=${MYSQL_DATABASE:-erp_cuop}

respaldar() {
    mkdir -p "$DIR"
    local archivo="$DIR/${BASE}-$(date +%Y%m%d-%H%M).sql.gz"
    mysqldump -h db -uroot --single-transaction --routines --triggers --no-tablespaces "$BASE" | gzip -9 > "$archivo.tmp"
    gzip -t "$archivo.tmp"            # verifica que se pueda leer antes de darlo por bueno
    mv "$archivo.tmp" "$archivo"
    find "$DIR" -name "${BASE}-*.sql.gz" -mtime +45 -delete
    echo "$(date '+%F %T') Respaldo OK: $(basename "$archivo") ($(du -h "$archivo" | cut -f1))"
}

case "${1:-}" in
    --ahora) respaldar ;;
    --restaurar)
        f="$DIR/$(basename "${2:?Indique el nombre del archivo de respaldo}")"
        [ -f "$f" ] || { echo "No existe $f"; exit 1; }
        echo "Restaurando $(basename "$f")…"
        gunzip -c "$f" | mysql -h db -uroot "$BASE"
        echo "Restauración terminada." ;;
    *)
        until mysqladmin -h db -uroot ping --silent; do sleep 3; done
        while true; do
            if [ -z "$(find "$DIR" -name "${BASE}-*.sql.gz" -mmin -1200 2>/dev/null | head -1)" ]; then
                respaldar || echo "$(date '+%F %T') ERROR en el respaldo"
            fi
            sleep 3600
        done ;;
esac
