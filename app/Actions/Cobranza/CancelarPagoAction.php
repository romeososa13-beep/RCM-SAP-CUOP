<?php

namespace App\Actions\Cobranza;

use App\Exceptions\CobranzaException;
use App\Models\Adeudo;
use App\Models\Pago;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CancelarPagoAction
{
    /**
     * Cancela un pago (soft delete auditable) y reabre el adeudo si queda saldo.
     * El folio NO se reutiliza: queda en el historial como cancelado.
     */
    public function execute(int $pagoId, User $usuario, string $motivo): void
    {
        if (! $usuario->hasRole('Administrador')) {
            throw new CobranzaException('Solo un Administrador puede cancelar pagos.');
        }

        if (mb_strlen(trim($motivo)) < 5) {
            throw new CobranzaException('Indique el motivo de la cancelación (mínimo 5 caracteres).');
        }

        DB::transaction(function () use ($pagoId, $usuario, $motivo) {
            $pago = Pago::query()->whereKey($pagoId)->lockForUpdate()->first();

            if (! $pago) {
                throw new CobranzaException('El pago no existe o ya fue cancelado.');
            }

            $adeudo = Adeudo::query()->whereKey($pago->adeudo_id)->lockForUpdate()->firstOrFail();

            $pago->forceFill([
                'cancelado_por' => $usuario->getKey(),
                'motivo_cancelacion' => mb_strtoupper(trim($motivo)),
            ])->save();
            $pago->delete();

            $adeudo->load('pagos');
            $adeudo->forceFill([
                'pagado' => RegistrarPagoAction::aCentavos($adeudo->saldo_pendiente) <= 0,
            ])->save();
        });
    }
}
