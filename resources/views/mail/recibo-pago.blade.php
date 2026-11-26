<x-mail::message>
# Recibo de pago

Hola **{{ $alumno->nombre_completo }}**:

Registramos tu pago. Adjuntamos tu recibo en PDF.

<x-mail::table>
| | |
|:--|--:|
| Folio | **{{ $pago->folio_pago }}** |
| Concepto | {{ $pago->adeudo->concepto }} |
| Fecha | {{ $pago->fecha_pago->format('d/m/Y H:i') }} |
| Forma de pago | {{ $pago->forma_pago->etiqueta() }} |
| Importe | **${{ number_format($pago->monto_pagado, 2) }}** |
</x-mail::table>

Matrícula: {{ $alumno->matricula }}

Si tienes alguna duda sobre este pago, acude a caja con tu folio.

{{ \App\Models\Ajuste::institucion()['institucion_nombre'] }}
</x-mail::message>
