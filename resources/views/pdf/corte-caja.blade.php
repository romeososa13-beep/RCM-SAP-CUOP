<!DOCTYPE html>
<html lang="es"><head><meta charset="utf-8"><title>Corte de caja</title>
<style>
    * { font-family: DejaVu Sans, sans-serif; } body { font-size: 8pt; }
    h1 { font-size: 13pt; margin: 0 0 4pt; } table { width: 100%; border-collapse: collapse; margin-top: 8pt; }
    th, td { border: 1px solid #ccc; padding: 3pt 4pt; } th { background: #f1f5f9; text-align: left; }
    .r { text-align: right; } .tot td { font-weight: bold; background: #f8fafc; }
</style></head>
<body>
    <h1>CORTE DE CAJA</h1>
    <div>Periodo: <strong>{{ $corte['desde']->format('d/m/Y') }} al {{ $corte['hasta']->format('d/m/Y') }}</strong> · Cajero: <strong>{{ $cajero }}</strong></div>
    <div>Generado: {{ now()->format('d/m/Y H:i') }} · Pagos cancelados en el periodo (no suman): {{ $corte['cancelados'] }}</div>

    <table style="width: 50%">
        <tr><th>Forma de pago</th><th class="r">Operaciones</th><th class="r">Importe</th></tr>
        @foreach ($corte['totales'] as $t)
            <tr><td>{{ $t['etiqueta'] }}</td><td class="r">{{ $t['num'] }}</td><td class="r">${{ number_format($t['monto'], 2) }}</td></tr>
        @endforeach
        <tr class="tot"><td>TOTAL</td><td class="r">{{ $corte['num'] }}</td><td class="r">${{ number_format($corte['total'], 2) }}</td></tr>
    </table>

    <table>
        <tr><th>Folio</th><th>Fecha</th><th>Matrícula</th><th>Alumno</th><th>Concepto</th><th>Forma</th><th>Referencia</th><th class="r">Importe</th></tr>
        @foreach ($corte['pagos'] as $p)
            <tr><td>{{ $p->folio_pago }}</td><td>{{ $p->fecha_pago->format('d/m/Y H:i') }}</td><td>{{ $p->adeudo->matricula }}</td>
                <td>{{ $p->adeudo->alumno->nombre_completo }}</td><td>{{ $p->adeudo->concepto }}</td><td>{{ $p->forma_pago->etiqueta() }}</td>
                <td>{{ $p->referencia }}</td><td class="r">${{ number_format($p->monto_pagado, 2) }}</td></tr>
        @endforeach
    </table>

    <table style="width: 60%; margin-top: 40pt; border: none">
        <tr><td style="border:none; border-top: 1px solid #000; text-align:center">Entrega (cajero)</td><td style="border:none; width: 20%"></td>
            <td style="border:none; border-top: 1px solid #000; text-align:center">Recibe (tesorería)</td></tr>
    </table>
</body></html>
