<?php

namespace App\Actions\Cobranza;

use App\Exceptions\CobranzaException;
use App\Models\Bitacora;
use App\Models\CorteCaja;
use App\Models\User;
use App\Services\Reportes\CorteCajaService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class CorteCajaAction
{
    public function __construct(private readonly CorteCajaService $cortes) {}

    /** Cierra el corte del día de un cajero. Lo cierra el propio cajero o un Administrador. */
    public function cerrar(User $cajero, CarbonInterface $fecha, User $usuario): CorteCaja
    {
        if ($usuario->id !== $cajero->id && ! $usuario->hasRole('Administrador')) {
            throw new CobranzaException('Solo el propio cajero o un Administrador puede cerrar este corte.');
        }
        if ($fecha->isFuture() && ! $fecha->isToday()) {
            throw new CobranzaException('No se puede cerrar un día que todavía no llega.');
        }

        return DB::transaction(function () use ($cajero, $fecha, $usuario) {
            $existente = CorteCaja::where('user_id', $cajero->id)->whereDate('fecha', $fecha->toDateString())->lockForUpdate()->first();
            if ($existente?->esta_cerrado) {
                throw new CobranzaException('Ese corte ya está cerrado.');
            }

            $c = $this->cortes->generar($fecha, $fecha, $cajero->id);
            $datos = [
                'num_pagos' => $c['num'], 'total' => $c['total'],
                'por_forma' => collect($c['totales'])->map(fn ($t) => ['num' => $t['num'], 'monto' => $t['monto']])->all(),
                'cerrado_at' => now(), 'cerrado_por' => $usuario->id,
                'reabierto_at' => null, 'reabierto_por' => null, 'motivo_reapertura' => null,
            ];
            $corte = $existente
                ? tap($existente)->update($datos)
                : CorteCaja::create($datos + ['user_id' => $cajero->id, 'fecha' => $fecha->toDateString()]);

            Bitacora::registrar('cierre', 'corte_caja', $corte->id,
                "Corte de {$cajero->name} del {$fecha->format('d/m/Y')} cerrado: {$c['num']} pagos por $".number_format($c['total'], 2));

            return $corte;
        });
    }

    public function reabrir(int $corteId, User $usuario, string $motivo): CorteCaja
    {
        if (! $usuario->hasRole('Administrador')) {
            throw new CobranzaException('Solo un Administrador puede reabrir un corte.');
        }
        $motivo = mb_strtoupper(trim($motivo));
        if (mb_strlen($motivo) < 5) {
            throw new CobranzaException('Indique el motivo de la reapertura (mínimo 5 caracteres).');
        }

        $corte = CorteCaja::with('cajero:id,name')->findOrFail($corteId);
        if (! $corte->esta_cerrado) {
            throw new CobranzaException('Ese corte ya está abierto.');
        }
        $corte->update(['reabierto_at' => now(), 'reabierto_por' => $usuario->id, 'motivo_reapertura' => $motivo]);

        Bitacora::registrar('reapertura', 'corte_caja', $corte->id,
            "Corte de {$corte->cajero->name} del {$corte->fecha->format('d/m/Y')} reabierto · {$motivo}");

        return $corte;
    }
}
