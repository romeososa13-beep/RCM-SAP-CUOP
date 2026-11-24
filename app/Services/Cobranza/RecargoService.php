<?php

namespace App\Services\Cobranza;

use App\Enums\TipoAdeudo;
use App\Models\Adeudo;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class RecargoService
{
    /**
     * Aplica UN recargo a cada adeudo de colegiatura o inglés no pagado cuyo
     * último día sin recargo (fecha_vencimiento) ya pasó:
     *   - Colegiatura: monto fijo del plan del alumno ($180).
     *   - Inglés: config('cobranza.recargo_ingles_porcentaje')% de su costo.
     * Los cargos de tipo OTRO (inscripción, trámites…) y los marcados como
     * exento_recargo (p. ej. saldos migrados con su monto final) no llevan recargo.
     *
     * @return array{aplicados:int, monto:float, por_tipo:array<string,int>}
     */
    public function aplicar(?CarbonInterface $fechaCorte = null, bool $simular = false): array
    {
        $fechaCorte ??= today();
        $aplicados = 0;
        $montoTotal = 0;
        $porTipo = [];

        Adeudo::query()
            ->where('pagado', false)
            ->whereIn('tipo', config('cobranza.tipos_con_recargo'))
            ->whereDate('fecha_vencimiento', '<', $fechaCorte->toDateString())
            ->where('recargo_aplicado', 0)
            ->where('exento_recargo', false)
            ->with('alumno.planPago:id,monto_recargo')
            ->chunkById(500, function ($adeudos) use (&$aplicados, &$montoTotal, &$porTipo, $simular) {
                DB::transaction(function () use ($adeudos, &$aplicados, &$montoTotal, &$porTipo, $simular) {
                    foreach ($adeudos as $adeudo) {
                        $recargo = $this->calcular($adeudo);

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
                        $porTipo[$adeudo->tipo->value] = ($porTipo[$adeudo->tipo->value] ?? 0) + 1;
                    }
                });
            });

        return ['aplicados' => $aplicados, 'monto' => round($montoTotal, 2), 'por_tipo' => $porTipo];
    }

    public function calcular(Adeudo $adeudo): float
    {
        return match ($adeudo->tipo) {
            TipoAdeudo::COLEGIATURA => round((float) ($adeudo->alumno?->planPago?->monto_recargo ?? 0), 2),
            TipoAdeudo::INGLES => round((float) $adeudo->monto_original * config('cobranza.recargo_ingles_porcentaje') / 100, 2),
            TipoAdeudo::OTRO => 0.0,
        };
    }
}
