<?php

namespace App\Actions\Cobranza;

use App\Exceptions\CobranzaException;
use App\Models\Adeudo;
use App\Models\Bitacora;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CondonarRecargoAction
{
    /**
     * Quita el recargo de un adeudo (y lo marca exento para que no vuelva a aplicarse).
     * Solo Administrador, con motivo, y solo si el recargo no se ha pagado ya.
     */
    public function execute(int $adeudoId, User $usuario, string $motivo): Adeudo
    {
        if (! $usuario->hasRole('Administrador')) {
            throw new CobranzaException('Solo un Administrador puede condonar recargos.');
        }
        $motivo = mb_strtoupper(trim($motivo));
        if (mb_strlen($motivo) < 5) {
            throw new CobranzaException('Indique el motivo de la condonación (mínimo 5 caracteres).');
        }

        return DB::transaction(function () use ($adeudoId, $motivo) {
            $adeudo = Adeudo::query()->whereKey($adeudoId)->lockForUpdate()->first()
                ?? throw new CobranzaException('El adeudo no existe.');
            $adeudo->load('pagos');

            $recargo = (float) $adeudo->recargo_aplicado;
            if ($recargo <= 0) {
                throw new CobranzaException('Ese adeudo no tiene recargo.');
            }
            if (RegistrarPagoAction::aCentavos($adeudo->total_pagado) > RegistrarPagoAction::aCentavos($adeudo->monto_original)) {
                throw new CobranzaException('El recargo ya fue pagado (total o parcialmente); no se puede condonar.');
            }

            $adeudo->forceFill(['recargo_aplicado' => 0, 'exento_recargo' => true])->save();
            $adeudo->load('pagos');
            if (RegistrarPagoAction::aCentavos($adeudo->saldo_pendiente) <= 0) {
                $adeudo->forceFill(['pagado' => true])->save();
            }

            Bitacora::registrar('condonado', 'adeudo', $adeudo->id,
                "{$adeudo->matricula} · {$adeudo->concepto} · recargo $".number_format($recargo, 2)." condonado · {$motivo}");

            return $adeudo;
        });
    }
}
