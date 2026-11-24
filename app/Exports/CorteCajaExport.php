<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class CorteCajaExport implements FromView, ShouldAutoSize
{
    public function __construct(private readonly array $corte, private readonly string $cajero)
    {
    }

    public function view(): View
    {
        return view('exports.corte-caja', ['corte' => $this->corte, 'cajero' => $this->cajero]);
    }
}
