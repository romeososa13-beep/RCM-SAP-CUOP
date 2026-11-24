<?php

namespace App\Services\Reportes;

use App\Enums\EstatusAlumno;
use App\Models\Alumno;
use App\Models\Sede;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Cifras del tablero ejecutivo (mismas definiciones que el tablero de INTELL/EDUCAPP):
 *  - Esperado: todo lo cargado con vencimiento en el periodo (cargo + recargo).
 *  - Recuperado: lo pagado de eso (en esta caja + lo abonado en el sistema anterior).
 *  - Por recuperar: esperado − recuperado, separado por el mes de vencimiento:
 *    meses anteriores, mes en curso y meses siguientes (por vencer).
 *  - Avance de la cobranza: recuperado / esperado de lo que ya tocaba pagar (hasta el mes en curso).
 */
class TableroService
{
    /** Cuatrimestres: ene–abr, may–ago, sep–dic. */
    public static function periodoDe(CarbonInterface $fecha): array
    {
        $inicioMes = intdiv($fecha->month - 1, 4) * 4 + 1;
        $desde = Carbon::create($fecha->year, $inicioMes, 1)->startOfDay();

        return [$desde, $desde->copy()->addMonths(3)->endOfMonth()];
    }

    public function generar(CarbonInterface $desde, CarbonInterface $hasta, ?int $sedeId = null, ?CarbonInterface $hoy = null): array
    {
        $hoy ??= today();
        $mesActual = $hoy->format('Y-m');

        $pagos = DB::table('pagos')->whereNull('deleted_at')
            ->selectRaw('adeudo_id, SUM(monto_pagado) as pagado')->groupBy('adeudo_id');

        // Una fila por sede × tipo × mes de vencimiento.
        $filas = DB::table('adeudos as a')
            ->join('alumnos as al', 'al.matricula', '=', 'a.matricula')
            ->leftJoinSub($pagos, 'p', 'p.adeudo_id', '=', 'a.id')
            ->whereBetween('a.fecha_vencimiento', [$desde->toDateString(), $hasta->toDateString()])
            ->when($sedeId, fn ($q) => $q->where('al.sede_id', $sedeId))
            ->selectRaw('al.sede_id, a.tipo, SUBSTR(a.fecha_vencimiento, 1, 7) as mes,
                SUM(a.monto_original + a.recargo_aplicado) as esperado,
                SUM(a.pagado_previo + COALESCE(p.pagado, 0)) as recuperado')
            ->groupBy('al.sede_id', 'a.tipo', DB::raw('SUBSTR(a.fecha_vencimiento, 1, 7)'))
            ->get()
            ->map(function ($f) use ($mesActual) {
                $f->esperado = round((float) $f->esperado, 2);
                $f->recuperado = round((float) $f->recuperado, 2);
                $f->pendiente = round($f->esperado - $f->recuperado, 2);
                $f->tramo = $f->mes < $mesActual ? 'anteriores' : ($f->mes === $mesActual ? 'mes' : 'siguientes');

                return $f;
            });

        $sedes = Sede::orderBy('clave')->when($sedeId, fn ($q) => $q->whereKey($sedeId))->get(['id', 'clave', 'nombre']);
        $activos = Alumno::query()->where('estatus', EstatusAlumno::ACTIVO)
            ->when($sedeId, fn ($q) => $q->where('sede_id', $sedeId))
            ->selectRaw('sede_id, COUNT(*) n')->groupBy('sede_id')->pluck('n', 'sede_id');
        $deben = $this->alumnosQueDeben($hoy, $sedeId);
        $bloqueos = Alumno::conBloqueo($hoy)->when($sedeId, fn ($q) => $q->where('sede_id', $sedeId))
            ->selectRaw('sede_id, COUNT(*) n')->groupBy('sede_id')->pluck('n', 'sede_id');

        $resumen = fn (Collection $c) => $this->resumir($c);

        return [
            'desde' => $desde, 'hasta' => $hasta, 'hoy' => $hoy,
            'total' => $resumen($filas) + [
                'activos' => (int) $activos->sum(),
                'deben' => (int) $deben->sum(),
                'bloqueos' => (int) $bloqueos->sum(),
            ],
            'por_sede' => $sedes->map(fn ($s) => ['sede' => $s] + $resumen($filas->where('sede_id', $s->id)) + [
                'activos' => (int) ($activos[$s->id] ?? 0),
                'deben' => (int) ($deben[$s->id] ?? 0),
                'bloqueos' => (int) ($bloqueos[$s->id] ?? 0),
            ])->values(),
            'por_mes' => $filas->groupBy('mes')->sortKeys()->map(fn ($c, $mes) => ['mes' => $mes] + $resumen($c))->values(),
            'por_tipo' => $filas->groupBy('tipo')->map(fn ($c, $tipo) => ['tipo' => $tipo] + $resumen($c))->sortByDesc('esperado')->values(),
        ];
    }

    private function resumir(Collection $c): array
    {
        $esperado = round($c->sum('esperado'), 2);
        $recuperado = round($c->sum('recuperado'), 2);
        $tocaba = $c->whereIn('tramo', ['anteriores', 'mes']);
        $esperadoTocaba = $tocaba->sum('esperado');

        return [
            'esperado' => $esperado,
            'recuperado' => $recuperado,
            'por_recuperar' => round($esperado - $recuperado, 2),
            'anteriores' => round($c->where('tramo', 'anteriores')->sum('pendiente'), 2),
            'mes' => round($c->where('tramo', 'mes')->sum('pendiente'), 2),
            'por_vencer' => round($c->where('tramo', 'siguientes')->sum('pendiente'), 2),
            'pct_recuperado' => $esperado > 0 ? $recuperado / $esperado : null,
            'avance' => $esperadoTocaba > 0 ? $tocaba->sum('recuperado') / $esperadoTocaba : null,
        ];
    }

    /** Alumnos con algún saldo de lo que ya tocaba pagar (vencido hasta hoy). */
    private function alumnosQueDeben(CarbonInterface $hoy, ?int $sedeId): Collection
    {
        return Alumno::query()
            ->when($sedeId, fn ($q) => $q->where('sede_id', $sedeId))
            ->whereHas('adeudos', fn ($q) => $q->where('pagado', false)->whereDate('fecha_vencimiento', '<', $hoy->toDateString()))
            ->selectRaw('sede_id, COUNT(*) n')->groupBy('sede_id')->pluck('n', 'sede_id');
    }
}
