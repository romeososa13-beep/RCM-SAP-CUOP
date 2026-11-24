<?php

namespace App\Services\Cobranza;

use App\Models\FolioConsecutivo;
use Illuminate\Support\Facades\DB;
use LogicException;

class FolioService
{
    /**
     * Reserva el siguiente folio de la serie. DEBE llamarse dentro de una
     * transacción: el renglón queda bloqueado (SELECT ... FOR UPDATE) hasta el
     * COMMIT, de modo que dos cajas concurrentes se serializan y, si la
     * transacción hace ROLLBACK, el consecutivo tampoco avanza (sin huecos).
     */
    public function siguiente(string $serie = 'PAGOS'): string
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('FolioService::siguiente() requiere una transacción activa.');
        }

        $control = FolioConsecutivo::query()->whereKey($serie)->lockForUpdate()->first();

        if (! $control) {
            FolioConsecutivo::query()->insertOrIgnore([
                'serie' => $serie, 'ultimo_folio' => 0, 'longitud' => 5,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $control = FolioConsecutivo::query()->whereKey($serie)->lockForUpdate()->firstOrFail();
        }

        $control->ultimo_folio++;
        $control->save();

        return str_pad((string) $control->ultimo_folio, $control->longitud, '0', STR_PAD_LEFT);
    }
}
