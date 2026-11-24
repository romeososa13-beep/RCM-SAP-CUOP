<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Recibo {{ $pago->folio_pago }}</title>
<style>
    @page { margin: {{ $formato === 'media-carta' ? '24pt 32pt' : '8pt 10pt' }}; }
    * { font-family: DejaVu Sans, sans-serif; }
    body { font-size: {{ $formato === 'media-carta' ? '9pt' : '7pt' }}; color: #111; }
    .c { text-align: center; } .r { text-align: right; } .b { font-weight: bold; }
    h1 { font-size: {{ $formato === 'media-carta' ? '13pt' : '9pt' }}; margin: 0; }
    .sep { border-top: 1px dashed #444; margin: 5pt 0; }
    table { width: 100%; border-collapse: collapse; }
    td { padding: 1.5pt 0; vertical-align: top; }
    .total td { font-size: {{ $formato === 'media-carta' ? '12pt' : '9pt' }}; font-weight: bold; }
    .cancelado { color: #b91c1c; font-size: 14pt; font-weight: bold; text-align: center; border: 2px solid #b91c1c; padding: 3pt; margin: 4pt 0; }
    .muted { color: #555; }
</style>
</head>
<body>
    <div class="c">
        <h1>CENTRO UNIVERSITARIO</h1>
        <div>{{ $alumno->sede->nombre }} ({{ $alumno->sede->clave }})</div>
        <div class="b" style="margin-top:3pt">RECIBO DE PAGO</div>
        <div>Folio: <span class="b">{{ $pago->folio_pago }}</span></div>
    </div>

    @if ($pago->trashed())
        <div class="cancelado">CANCELADO</div>
        <div class="c muted">{{ $pago->motivo_cancelacion }}</div>
    @endif

    <div class="sep"></div>
    <table>
        <tr><td>Fecha:</td><td class="r">{{ $pago->fecha_pago->format('d/m/Y H:i') }}</td></tr>
        <tr><td>Matrícula:</td><td class="r b">{{ $alumno->matricula }}</td></tr>
        <tr><td colspan="2">{{ $alumno->nombre_completo }}</td></tr>
        <tr><td colspan="2" class="muted">{{ $alumno->carrera->nombre }} · {{ $alumno->grupo }}</td></tr>
    </table>

    <div class="sep"></div>
    <table>
        <tr><td colspan="2" class="b">{{ $adeudo->concepto }} <span class="muted">(Adeudo #{{ $adeudo->id }})</span></td></tr>
        <tr><td>Cargo original</td><td class="r">${{ number_format($adeudo->monto_original, 2) }}</td></tr>
        @if ((float) $adeudo->recargo_aplicado > 0)
            <tr><td>Recargo</td><td class="r">${{ number_format($adeudo->recargo_aplicado, 2) }}</td></tr>
        @endif
        <tr><td>Forma de pago</td><td class="r">{{ $pago->forma_pago->etiqueta() }}</td></tr>
        @if ($pago->referencia)
            <tr><td>Referencia</td><td class="r">{{ $pago->referencia }}</td></tr>
        @endif
    </table>

    <div class="sep"></div>
    <table>
        <tr class="total"><td>IMPORTE PAGADO</td><td class="r">${{ number_format($pago->monto_pagado, 2) }}</td></tr>
    </table>
    <div class="muted" style="margin-top:2pt">({{ $importeLetra }})</div>
    @if (! is_null($saldoDespues))
        <table style="margin-top:4pt">
            <tr><td>Saldo restante del concepto</td><td class="r b">${{ number_format($saldoDespues, 2) }}</td></tr>
        </table>
    @endif

    <div class="sep"></div>
    <div class="c muted">
        Atendió: {{ $pago->cajero->name }}<br>
        Impreso: {{ now()->format('d/m/Y H:i') }}<br>
        Este recibo es un comprobante interno de pago.<br>Consérvelo para cualquier aclaración.
    </div>
</body>
</html>
