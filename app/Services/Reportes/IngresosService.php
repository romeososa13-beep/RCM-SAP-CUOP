<?php

namespace App\Services\Reportes;

use App\Enums\FormaPago;
use App\Models\Pago;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Ingresos cobrados en caja (pagos vigentes por fecha de pago). Lo abonado en el
 * sistema anterior (pagado_previo) no es un ingreso de esta caja y no se incluye.
 */
class IngresosService
{
    /** "MENSUALIDAD NOV 2026" → "MENSUALIDAD"; "INSCRIPCIÓN 2027-1" → "INSCRIPCIÓN". */
    public static function conceptoBase(string $concepto): string
    {
        $c = preg_replace('/\s+\(.*\)$/u', '', trim($concepto));
        $c = preg_replace('/\s+(ENE|FEB|MAR|ABR|MAY|JUN|JUL|AGO|SEP|OCT|NOV|DIC)\s+\d{4}$/u', '', $c);
        $c = preg_replace('/\s+\d{2,4}(-\d{1,2})?$/u', '', $c);

        return $c !== '' ? $c : $concepto;
    }

    public function generar(CarbonInterface $desde, CarbonInterface $hasta, ?int $sedeId = null): array
    {
        $pagos = Pago::query()
            ->whereBetween('fecha_pago', [$desde->copy()->startOfDay(), $hasta->copy()->endOfDay()])
            ->when($sedeId, fn ($q) => $q->whereHas('adeudo.alumno', fn ($q) => $q->where('sede_id', $sedeId)))
            ->with(['adeudo:id,matricula,concepto,tipo', 'adeudo.alumno:matricula,nombre_completo,sede_id', 'adeudo.alumno.sede:id,clave', 'cajero:id,name'])
            ->orderBy('fecha_pago')
            ->get()
            ->map(fn (Pago $p) => [
                'pago' => $p,
                'fecha' => $p->fecha_pago->toDateString(),
                'sede' => $p->adeudo->alumno->sede->clave,
                'tipo' => $p->adeudo->tipo->value,
                'concepto' => self::conceptoBase($p->adeudo->concepto),
                'forma' => $p->forma_pago->value,
                'monto' => (float) $p->monto_pagado,
            ]);

        $formas = array_map(fn ($f) => $f->value, FormaPago::cases());
        $agrupar = fn (Collection $c, string $clave) => $c->groupBy($clave)->map(fn ($g, $k) => [
            'clave' => $k,
            'num' => $g->count(),
            'total' => round($g->sum('monto'), 2),
            'por_forma' => collect($formas)->mapWithKeys(fn ($f) => [$f => round($g->where('forma', $f)->sum('monto'), 2)])->all(),
            'tipo' => $g->first()['tipo'],
        ])->sortByDesc('total')->values();

        return [
            'desde' => $desde, 'hasta' => $hasta,
            'total' => round($pagos->sum('monto'), 2),
            'num' => $pagos->count(),
            'por_forma' => collect($formas)->mapWithKeys(fn ($f) => [$f => round($pagos->where('forma', $f)->sum('monto'), 2)])->all(),
            'por_concepto' => $agrupar($pagos, 'concepto'),
            'por_sede' => $agrupar($pagos, 'sede')->sortBy('clave')->values(),
            'por_dia' => $agrupar($pagos, 'fecha')->sortBy('clave')->values(),
            'pagos' => $pagos,
        ];
    }
}
