#!/usr/bin/env bash
# Prueba de concurrencia de caja (SOLO en una base de pruebas, nunca en producción).
# Lanza N cobros simultáneos sobre un adeudo y verifica que no haya sobrecobro
# ni folios duplicados.
#   Uso: scripts/prueba-concurrencia.sh <adeudo_id> [procesos=20] [monto=100]
set -euo pipefail
cd "$(dirname "$0")/.."
ADEUDO=${1:?Indique el adeudo_id}; N=${2:-20}; MONTO=${3:-100}
T=$(php -r 'echo microtime(true)+3;')
OUT=$(mktemp -d)
for i in $(seq 1 "$N"); do php scripts/cobro_concurrente.php "$PWD" "$ADEUDO" "$MONTO" "$T" > "$OUT/$i.txt" & done
wait
echo "Resultados de $N cobros simultáneos de \$$MONTO:"
cat "$OUT"/*.txt | cut -d' ' -f1 | sort | uniq -c
cat "$OUT"/*.txt | grep -v '^OK' | sort -u || true
php artisan tinker --execute='$t=App\Models\Pago::count(); $u=App\Models\Pago::distinct()->count("folio_pago"); echo "Pagos: $t | Folios únicos: $u ".($t===$u?"✔":"✘ DUPLICADOS").PHP_EOL;'
rm -rf "$OUT"
