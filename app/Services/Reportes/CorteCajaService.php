<?php

namespace App\Services\Reportes;

use App\Enums\FormaPago;
use App\Models\Pago;
use Carbon\CarbonInterface;

class CorteCajaService
{
    /**
     * @return array{desde:CarbonInterface, hasta:CarbonInterface, totales:array<string,array{num:int,monto:float}>, total:float, num:int, pagos:\Illuminate\Support\Collection, cancelados:int}
     */
    public function generar(CarbonInterface $desde, CarbonInterface $hasta, ?int $cajeroId = null): array
    {
        $desde = $desde->copy()->startOfDay();
        $hasta = $hasta->copy()->endOfDay();

        $base = fn () => Pago::query()
            ->whereBetween('fecha_pago', [$desde, $hasta])
            ->when($cajeroId, fn ($q) => $q->where('user_id', $cajeroId));

        $agrupado = $base()
            ->selectRaw('forma_pago, COUNT(*) as num, SUM(monto_pagado) as monto')
            ->groupBy('forma_pago')
            ->get()
            ->keyBy(fn ($r) => $r->forma_pago instanceof FormaPago ? $r->forma_pago->value : $r->forma_pago);

        $totales = [];
        foreach (FormaPago::cases() as $fp) {
            $totales[$fp->value] = [
                'etiqueta' => $fp->etiqueta(),
                'num' => (int) ($agrupado[$fp->value]->num ?? 0),
                'monto' => round((float) ($agrupado[$fp->value]->monto ?? 0), 2),
            ];
        }

        return [
            'desde' => $desde,
            'hasta' => $hasta,
            'totales' => $totales,
            'total' => round(array_sum(array_column($totales, 'monto')), 2),
            'num' => array_sum(array_column($totales, 'num')),
            'pagos' => $base()->with(['adeudo:id,matricula,concepto', 'adeudo.alumno:matricula,nombre_completo', 'cajero:id,name'])
                ->orderBy('fecha_pago')->get(),
            'cancelados' => $base()->onlyTrashed()->count(),
        ];
    }
}
