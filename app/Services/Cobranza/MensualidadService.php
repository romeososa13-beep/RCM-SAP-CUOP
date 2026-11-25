<?php

namespace App\Services\Cobranza;

use App\Enums\TipoAdeudo;
use App\Models\Adeudo;
use App\Models\Alumno;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class MensualidadService
{
    private const MESES = ['ENE', 'FEB', 'MAR', 'ABR', 'MAY', 'JUN', 'JUL', 'AGO', 'SEP', 'OCT', 'NOV', 'DIC'];

    public static function concepto(CarbonInterface $mes, TipoAdeudo $tipo = TipoAdeudo::COLEGIATURA): string
    {
        $prefijo = $tipo === TipoAdeudo::INGLES ? 'INGLÉS' : 'MENSUALIDAD';

        return $prefijo.' '.self::MESES[$mes->month - 1].' '.$mes->year;
    }

    /**
     * Último día para pagar sin recargo: el día "dias_tolerancia" del mes (10 → día 10).
     * Desde el día siguiente aplica el recargo.
     */
    public static function vencimiento(CarbonInterface $inicioMes, int $diasTolerancia): string
    {
        $dia = max(1, min($diasTolerancia, $inicioMes->daysInMonth));

        return $inicioMes->copy()->day($dia)->toDateString();
    }

    /**
     * Genera, SOLO para alumnos ACTIVOS, el cargo de colegiatura del mes según su plan
     * y, si cursan inglés (costo_ingles), el cargo de inglés. Si el alumno tiene beca,
     * el monto ya lleva el descuento (y se guarda cuánto en adeudos.descuento).
     * Idempotente por concepto.
     *
     * @return array{generados:int, omitidos:int, concepto:string, ingles:int}
     */
    public function generar(CarbonInterface $mes, bool $simular = false): array
    {
        $inicioMes = $mes->copy()->startOfMonth();
        $concepto = self::concepto($inicioMes);
        $conceptoIngles = self::concepto($inicioMes, TipoAdeudo::INGLES);
        $generados = 0;
        $omitidos = 0;
        $ingles = 0;

        Alumno::query()
            ->activos()
            ->with('planPago:id,monto_mensualidad,dias_tolerancia')
            ->withExists([
                'adeudos as ya_generado' => fn ($q) => $q->where('concepto', $concepto),
                'adeudos as ingles_generado' => fn ($q) => $q->where('concepto', $conceptoIngles),
            ])
            ->chunkById(500, function ($alumnos) use ($inicioMes, $concepto, $conceptoIngles, $simular, &$generados, &$omitidos, &$ingles) {
                $filas = [];
                $ahora = now();

                foreach ($alumnos as $alumno) {
                    if (! $alumno->planPago) {
                        $omitidos++;

                        continue;
                    }

                    $vence = self::vencimiento($inicioMes, $alumno->planPago->dias_tolerancia);
                    $base = ['matricula' => $alumno->matricula, 'fecha_vencimiento' => $vence, 'recargo_aplicado' => 0,
                        'pagado' => false, 'created_at' => $ahora, 'updated_at' => $ahora];

                    if ($alumno->ya_generado) {
                        $omitidos++;
                    } else {
                        [$monto, $descuento] = $alumno->aplicarBeca($alumno->planPago->monto_mensualidad);
                        $filas[] = $base + ['concepto' => $concepto, 'tipo' => TipoAdeudo::COLEGIATURA->value,
                            'monto_original' => $monto, 'descuento' => $descuento];
                        $generados++;
                    }

                    if ((float) $alumno->costo_ingles > 0 && ! $alumno->ingles_generado) {
                        [$monto, $descuento] = $alumno->aplicarBeca($alumno->costo_ingles, esIngles: true);
                        $filas[] = $base + ['concepto' => $conceptoIngles, 'tipo' => TipoAdeudo::INGLES->value,
                            'monto_original' => $monto, 'descuento' => $descuento];
                        $ingles++;
                    }
                }

                if ($filas && ! $simular) {
                    DB::transaction(fn () => Adeudo::query()->insert($filas));
                }
            }, 'matricula');

        return compact('generados', 'omitidos', 'concepto', 'ingles');
    }
}
