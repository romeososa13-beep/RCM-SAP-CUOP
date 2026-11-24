<?php

namespace App\Services\Cobranza;

use App\Models\Adeudo;
use App\Models\Alumno;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class MensualidadService
{
    private const MESES = ['ENE', 'FEB', 'MAR', 'ABR', 'MAY', 'JUN', 'JUL', 'AGO', 'SEP', 'OCT', 'NOV', 'DIC'];

    public static function concepto(CarbonInterface $mes): string
    {
        return 'MENSUALIDAD '.self::MESES[$mes->month - 1].' '.$mes->year;
    }

    /**
     * Genera el cargo de colegiatura del mes SOLO para alumnos ACTIVOS.
     * Vencimiento = día 1 del mes + días de tolerancia del plan.
     * Idempotente: omite alumnos que ya tienen el concepto del mes.
     *
     * @return array{generados:int, omitidos:int, concepto:string}
     */
    public function generar(CarbonInterface $mes, bool $simular = false): array
    {
        $inicioMes = $mes->copy()->startOfMonth();
        $concepto = self::concepto($inicioMes);
        $generados = 0;
        $omitidos = 0;

        Alumno::query()
            ->activos()
            ->with('planPago:id,monto_mensualidad,dias_tolerancia')
            ->withExists(['adeudos as ya_generado' => fn ($q) => $q->where('concepto', $concepto)])
            ->chunkById(500, function ($alumnos) use ($inicioMes, $concepto, $simular, &$generados, &$omitidos) {
                $filas = [];
                $ahora = now();

                foreach ($alumnos as $alumno) {
                    if ($alumno->ya_generado || ! $alumno->planPago) {
                        $omitidos++;
                        continue;
                    }

                    $filas[] = [
                        'matricula' => $alumno->matricula,
                        'concepto' => $concepto,
                        'fecha_vencimiento' => $inicioMes->copy()->addDays($alumno->planPago->dias_tolerancia)->toDateString(),
                        'monto_original' => $alumno->planPago->monto_mensualidad,
                        'recargo_aplicado' => 0,
                        'pagado' => false,
                        'created_at' => $ahora,
                        'updated_at' => $ahora,
                    ];
                }

                if ($filas && ! $simular) {
                    DB::transaction(fn () => Adeudo::query()->insert($filas));
                }

                $generados += count($filas);
            }, 'matricula');

        return compact('generados', 'omitidos', 'concepto');
    }
}
