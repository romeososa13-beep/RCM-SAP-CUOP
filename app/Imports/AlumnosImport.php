<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Lector del layout de padrón. Encabezados esperados (fila 1, sin importar
 * acentos/mayúsculas): MATRICULA | NOMBRE_COMPLETO | SEDE (clave) |
 * CARRERA (nombre) | GRUPO | PLAN_PAGO (id o nombre) | ESTATUS
 *
 * Solo se lee la PRIMERA hoja del libro (las demás pueden traer notas o revisiones).
 *
 * Solo lee: la validación y la persistencia viven en ImportarAlumnosService
 * para poder mostrar una previsualización antes de guardar.
 */
class AlumnosImport implements ToCollection, WithHeadingRow, WithMultipleSheets
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
        $this->filas = $rows
            ->map(fn ($r) => $r->map(fn ($v) => is_string($v) ? trim($v) : $v))
            ->filter(fn ($r) => $r->filter(fn ($v) => filled($v))->isNotEmpty()) // omite renglones vacíos
            ->values();
    }
}
