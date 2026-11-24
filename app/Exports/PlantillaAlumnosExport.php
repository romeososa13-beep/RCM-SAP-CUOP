<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PlantillaAlumnosExport implements FromArray, WithHeadings, ShouldAutoSize
{
    public function headings(): array
    {
        return ['MATRICULA', 'NOMBRE_COMPLETO', 'SEDE', 'CARRERA', 'GRUPO', 'PLAN_PAGO', 'ESTATUS'];
    }

    public function array(): array
    {
        return [
            ['CUOP27001', 'APELLIDO APELLIDO NOMBRE', 'CUOP-LOR', 'EDUCACIÓN NO ESCOLARIZADO', '27-1 LIC-EDUN-NESC-SAB-6A', '1', 'ACTIVO'],
        ];
    }
}
