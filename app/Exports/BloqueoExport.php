<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class BloqueoExport implements FromCollection, ShouldAutoSize, WithHeadings
{
    public function __construct(private readonly Collection $filas) {}

    public function headings(): array
    {
        return ['MATRICULA', 'NOMBRE_COMPLETO', 'SEDE', 'CARRERA', 'GRUPO', 'BLOQUEO_DESDE', 'CONCEPTOS_VENCIDOS', 'SALDO', 'CELULAR', 'TEL_TUTOR'];
    }

    public function collection(): Collection
    {
        return $this->filas->map(fn ($f) => [
            $f['alumno']->matricula, $f['alumno']->nombre_completo, $f['alumno']->sede->clave, $f['alumno']->carrera->nombre,
            $f['alumno']->grupo, $f['desde']->format('d/m/Y'), $f['conceptos'], $f['saldo'],
            $f['alumno']->celular, $f['alumno']->tel_tutor,
        ]);
    }
}
