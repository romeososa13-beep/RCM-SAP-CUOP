<?php

namespace App\Services\Reportes;

use App\Models\Adeudo;
use App\Models\Alumno;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class BloqueoService
{
    /**
     * Alumnos ACTIVOS que hoy deben bloquearse: deben colegiatura o inglés con
     * más de config('cobranza.bloqueo_dias_despues_vencimiento') días de vencido.
     *
     * @return Collection<int, array{alumno:Alumno, desde:CarbonInterface, conceptos:string, saldo:float}>
     */
    public function alumnos(?int $sedeId = null, ?int $carreraId = null, ?CarbonInterface $fecha = null): Collection
    {
        $fecha ??= today();

        return Alumno::query()
            ->conBloqueo($fecha)
            ->when($sedeId, fn ($q) => $q->where('sede_id', $sedeId))
            ->when($carreraId, fn ($q) => $q->where('carrera_id', $carreraId))
            ->with([
                'sede:id,clave', 'carrera:id,nombre',
                'adeudos' => fn ($q) => $q->causanBloqueo($fecha)->with('pagos:id,adeudo_id,monto_pagado')->orderBy('fecha_vencimiento'),
            ])
            ->orderBy('sede_id')->orderBy('nombre_completo')
            ->get()
            ->map(fn (Alumno $a) => [
                'alumno' => $a,
                'desde' => $a->adeudos->first()->bloqueo_desde,
                'conceptos' => $a->adeudos->pluck('concepto')->implode(', '),
                'saldo' => round($a->adeudos->sum(fn (Adeudo $d) => $d->saldo_pendiente), 2),
            ]);
    }
}
