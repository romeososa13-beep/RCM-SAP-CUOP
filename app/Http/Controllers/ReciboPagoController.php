<?php

namespace App\Http\Controllers;

use App\Models\Pago;
use App\Support\ImporteConLetra;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class ReciboPagoController extends Controller
{
    /** Ancho de ticket térmico 80 mm en puntos PDF (1 mm = 2.8346 pt). */
    private const TICKET_80MM = 226.77;

    public function __invoke(Request $request, Pago $pago)
    {
        $pago->load([
            'adeudo' => fn ($q) => $q->with(['pagos', 'alumno.sede', 'alumno.carrera']),
            'cajero:id,name',
        ]);

        $formato = $request->query('formato', 'ticket'); // ticket | media-carta

        // Saldo que quedó INMEDIATAMENTE después de este pago (no el saldo actual),
        // para que una reimpresión muestre exactamente lo mismo que el original.
        $abonadoHastaEste = $pago->adeudo->pagos
            ->filter(fn ($p) => $p->id <= $pago->id)
            ->sum('monto_pagado');
        $saldoDespues = round($pago->adeudo->total_cargo - $abonadoHastaEste, 2);

        $pdf = Pdf::loadView('pdf.recibo', [
            'pago' => $pago,
            'adeudo' => $pago->adeudo,
            'alumno' => $pago->adeudo->alumno,
            'importeLetra' => ImporteConLetra::convertir($pago->monto_pagado),
            'formato' => $formato,
            'saldoDespues' => $pago->trashed() ? null : $saldoDespues,
        ]);

        $formato === 'media-carta'
            ? $pdf->setPaper([0, 0, 612, 396])                     // 8.5" x 5.5"
            : $pdf->setPaper([0, 0, self::TICKET_80MM, 520]);      // 80 mm x ~219 mm

        return $pdf->stream("recibo-{$pago->folio_pago}.pdf");
    }
}
