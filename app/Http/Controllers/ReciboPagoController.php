<?php

namespace App\Http\Controllers;

use App\Models\Pago;
use App\Services\Cobranza\ReciboPdf;
use Illuminate\Http\Request;

class ReciboPagoController extends Controller
{
    public function __invoke(Request $request, Pago $pago, ReciboPdf $recibo)
    {
        $formato = $request->query('formato') === 'media-carta' ? 'media-carta' : 'ticket';

        return $recibo->generar($pago, $formato)->stream("recibo-{$pago->folio_pago}.pdf");
    }
}
