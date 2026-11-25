<?php

namespace App\Exports;

use App\Enums\FormaPago;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;

class IngresosExport implements WithMultipleSheets
{
    public function __construct(private readonly array $r) {}

    public function sheets(): array
    {
        $formas = array_map(fn ($f) => $f->etiqueta(), FormaPago::cases());
        $fila = fn ($g) => array_merge([$g['clave'], $g['num']], array_values($g['por_forma']), [$g['total']]);
        $hoja = fn (string $titulo, array $filas) => new class($titulo, $filas) implements FromArray, ShouldAutoSize, WithTitle
        {
            public function __construct(private string $t, private array $f) {}

            public function array(): array
            {
                return $this->f;
            }

            public function title(): string
            {
                return $this->t;
            }
        };
        $encabezado = fn ($primera) => array_merge([$primera, 'Pagos'], $formas, ['Total']);
        $periodo = ['Periodo', $this->r['desde']->format('d/m/Y').' al '.$this->r['hasta']->format('d/m/Y')];

        return [
            $hoja('Por concepto', array_merge([$periodo, [], $encabezado('Concepto')], $this->r['por_concepto']->map($fila)->all(),
                [array_merge(['TOTAL', $this->r['num']], array_values($this->r['por_forma']), [$this->r['total']])])),
            $hoja('Por sede', array_merge([$encabezado('Sede')], $this->r['por_sede']->map($fila)->all())),
            $hoja('Por día', array_merge([$encabezado('Fecha')], $this->r['por_dia']->map($fila)->all())),
            $hoja('Detalle', array_merge([['Folio', 'Fecha', 'Sede', 'Matrícula', 'Alumno', 'Concepto', 'Tipo', 'Forma de pago', 'Referencia', 'Importe', 'Cajero']],
                $this->r['pagos']->map(fn ($x) => [$x['pago']->folio_pago, $x['pago']->fecha_pago->format('d/m/Y H:i'), $x['sede'],
                    $x['pago']->adeudo->matricula, $x['pago']->adeudo->alumno->nombre_completo, $x['pago']->adeudo->concepto,
                    $x['tipo'], $x['pago']->forma_pago->etiqueta(), $x['pago']->referencia, $x['monto'], $x['pago']->cajero->name])->all())),
        ];
    }
}
