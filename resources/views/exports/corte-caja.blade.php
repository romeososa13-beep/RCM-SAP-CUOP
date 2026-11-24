<table>
    <tr><td colspan="7"><strong>CORTE DE CAJA</strong></td></tr>
    <tr><td>Periodo</td><td colspan="6">{{ $corte['desde']->format('d/m/Y') }} al {{ $corte['hasta']->format('d/m/Y') }}</td></tr>
    <tr><td>Cajero</td><td colspan="6">{{ $cajero }}</td></tr>
    <tr></tr>
    <tr><th>Forma de pago</th><th>Operaciones</th><th>Importe</th></tr>
    @foreach ($corte['totales'] as $t)
        <tr><td>{{ $t['etiqueta'] }}</td><td>{{ $t['num'] }}</td><td>{{ $t['monto'] }}</td></tr>
    @endforeach
    <tr><td><strong>TOTAL</strong></td><td><strong>{{ $corte['num'] }}</strong></td><td><strong>{{ $corte['total'] }}</strong></td></tr>
    <tr></tr>
    <tr><th>Folio</th><th>Fecha</th><th>Matrícula</th><th>Alumno</th><th>Concepto</th><th>Forma pago</th><th>Referencia</th><th>Importe</th><th>Cajero</th></tr>
    @foreach ($corte['pagos'] as $p)
        <tr>
            <td>{{ $p->folio_pago }}</td><td>{{ $p->fecha_pago->format('d/m/Y H:i') }}</td><td>{{ $p->adeudo->matricula }}</td>
            <td>{{ $p->adeudo->alumno->nombre_completo }}</td><td>{{ $p->adeudo->concepto }}</td><td>{{ $p->forma_pago->etiqueta() }}</td>
            <td>{{ $p->referencia }}</td><td>{{ $p->monto_pagado }}</td><td>{{ $p->cajero->name }}</td>
        </tr>
    @endforeach
</table>
