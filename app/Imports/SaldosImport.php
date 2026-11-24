<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Layout de saldos a migrar (primera hoja):
 * MATRICULA | CONCEPTO | TIPO | VENCIMIENTO | MONTO | RECARGO | PAGADO_PREVIO | EXENTO_RECARGO
 */
class SaldosImport implements ToCollection, WithHeadingRow, WithMultipleSheets
{
    public Collection $filas;

    public function __construct()
    {
        $this->filas = collect();
    }

    public function sheets(): array
    {
        return [0 => $this];
    }

    public function collection(Collection $rows): void
    {
        $this->filas = $rows->filter(fn ($r) => $r->filter(fn ($v) => filled($v))->isNotEmpty())->values();
    }
}
