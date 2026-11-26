<?php

namespace App\Actions\Cobranza;

use App\Enums\FormaPago;
use App\Exceptions\CobranzaException;
use App\Models\Adeudo;
use App\Models\CorteCaja;
use App\Models\Pago;
use App\Models\User;
use App\Services\Cobranza\FolioService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class RegistrarPagoAction
{
    public function __construct(private readonly FolioService $folios) {}

    /**
     * Registra un pago (total o abono parcial) contra un adeudo.
     *
     * @throws CobranzaException si viola una regla de negocio
     */
    public function execute(
        int $adeudoId,
        float|string $monto,
        FormaPago $formaPago,
        User $cajero,
        ?string $referencia = null,
        ?CarbonInterface $fechaPago = null,
    ): Pago {
        $montoCentavos = self::aCentavos($monto);
        $referencia = filled($referencia) ? mb_strtoupper(trim($referencia)) : null;

        if ($montoCentavos <= 0) {
            throw new CobranzaException('El monto a pagar debe ser mayor a cero.');
        }

        if ($formaPago->requiereReferencia() && $referencia === null) {
            throw new CobranzaException("La forma de pago {$formaPago->etiqueta()} requiere número de referencia.");
        }

        if (CorteCaja::cerrado($cajero->id, $fechaPago ?? today())) {
            throw new CobranzaException('Tu corte de caja de ese día ya está cerrado. Pide a Administración que lo reabra para cobrar.');
        }

        return DB::transaction(function () use ($adeudoId, $montoCentavos, $formaPago, $cajero, $referencia, $fechaPago) {
            // 1) Bloquear el adeudo: dos cajas no pueden cobrar el mismo saldo a la vez.
            $adeudo = Adeudo::query()->whereKey($adeudoId)->lockForUpdate()->first();

            if (! $adeudo) {
                throw new CobranzaException('El adeudo no existe.');
            }

            $adeudo->load('pagos');
            $saldoCentavos = self::aCentavos($adeudo->saldo_pendiente);

            if ($adeudo->pagado || $saldoCentavos <= 0) {
                throw new CobranzaException("El adeudo #{$adeudo->id} ya está liquidado.");
            }

            // 2) Validar que no se cobre de más.
            if ($montoCentavos > $saldoCentavos) {
                throw new CobranzaException(sprintf(
                    'El monto ($%s) excede el saldo pendiente ($%s).',
                    number_format($montoCentavos / 100, 2),
                    number_format($saldoCentavos / 100, 2),
                ));
            }

            // 3) Folio consecutivo con bloqueo pesimista.
            $folio = $this->folios->siguiente('PAGOS');

            // 4) Registrar el pago.
            $pago = $adeudo->pagos()->create([
                'folio_pago' => $folio,
                'fecha_pago' => $fechaPago ?? now(),
                'forma_pago' => $formaPago,
                'monto_pagado' => number_format($montoCentavos / 100, 2, '.', ''),
                'referencia' => $referencia,
                'user_id' => $cajero->getKey(),
            ]);

            // 5) Si el saldo llega a 0.00, el adeudo queda liquidado.
            if ($saldoCentavos - $montoCentavos === 0) {
                $adeudo->forceFill(['pagado' => true])->save();
            }

            return $pago->setRelation('adeudo', $adeudo->unsetRelation('pagos'));
        }, attempts: 3); // reintenta ante deadlocks de InnoDB
    }

    /** Aritmética monetaria en enteros (centavos) para evitar errores de punto flotante. */
    public static function aCentavos(float|int|string|null $monto): int
    {
        return (int) round(((float) $monto) * 100);
    }
}
