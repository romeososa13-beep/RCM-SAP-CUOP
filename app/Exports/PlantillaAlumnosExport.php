<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PlantillaAlumnosExport implements FromArray, ShouldAutoSize, WithHeadings
{
    public function headings(): array
    {
        return ['MATRICULA', 'NOMBRE_COMPLETO', 'SEDE', 'CARRERA', 'GRUPO', 'PLAN_PAGO', 'ESTATUS', 'INGLES', 'CELULAR', 'TEL_TUTOR', 'TEL_ALTERNO', 'EMAIL'];
    }

    public function array(): array
    {
        return [
            ['CUOP-LOR02999', 'APELLIDO APELLIDO NOMBRE', 'CUOP-LOR', 'LICENCIATURA EN EDUCACIÓN', '27-1 LIC-EDUN-NESC-SAB-1A', 'PLAN 1370', 'ACTIVO', '350', '4961234567', '', '', 'alumno@correo.com'],
        ];
    }
}
