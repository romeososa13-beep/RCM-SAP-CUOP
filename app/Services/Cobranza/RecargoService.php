<?php

namespace App\Services\Cobranza;

use App\Models\Adeudo;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class RecargoService
{
    /**
     * Aplica el recargo del plan del alumno a los adeudos no pagados, vencidos
     * y sin recargo previo. Idempotente: un adeudo solo recibe un recargo.
     *
     * @return array{aplicados:int, monto:float}
     */
    public function aplicar(?CarbonInterface $fechaCorte = null, bool $simular = false): array
    {
        $fechaCorte ??= today();
        $aplicados = 0;
        $montoTotal = 0;

        Adeudo::query()
            ->where('pagado', false)
            ->whereDate('fecha_vencimiento', '<', $fechaCorte->toDateString())
            ->where('recargo_aplicado', 0)
            ->with('alumno.planPago:id,monto_recargo')
            ->chunkById(500, function ($adeudos) use (&$aplicados, &$montoTotal, $simular) {
                DB::transaction(function () use ($adeudos, &$aplicados, &$montoTotal, $simular) {
                    foreach ($adeudos as $adeudo) {
                        $recargo = (float) ($adeudo->alumno?->planPago?->monto_recargo ?? 0);

                        if ($recargo <= 0) {
                            continue;
                        }

                        if (! $simular) {
                            // Condición repetida en el UPDATE: protege contra ejecuciones simultáneas.
                            $afectados = Adeudo::query()
                                ->whereKey($adeudo->id)
                                ->where('pagado', false)
                                ->where('recargo_aplicado', 0)
                                ->update(['recargo_aplicado' => $recargo, 'updated_at' => now()]);

                            if ($afectados === 0) {
                                continue;
                            }
                        }

                        $aplicados++;
                        $montoTotal += $recargo;
                    }
                });
            });

        return ['aplicados' => $aplicados, 'monto' => round($montoTotal, 2)];
    }
}
