<?php

namespace App\Enums;

enum EstatusAlumno: string
{
    case ACTIVO = 'ACTIVO';
    case PREINSCRITO = 'PREINSCRITO';
    case BAJA_TEMPORAL = 'BAJA_TEMPORAL';
    case BAJA_DEFINITIVA = 'BAJA_DEFINITIVA';
    case EGRESADO = 'EGRESADO';

    public function etiqueta(): string
    {
        return match ($this) {
            self::ACTIVO => 'Activo',
            self::PREINSCRITO => 'Preinscrito',
            self::BAJA_TEMPORAL => 'Baja temporal',
            self::BAJA_DEFINITIVA => 'Baja definitiva',
            self::EGRESADO => 'Egresado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ACTIVO => 'bg-emerald-100 text-emerald-800',
            self::PREINSCRITO => 'bg-violet-100 text-violet-800',
            self::BAJA_TEMPORAL => 'bg-amber-100 text-amber-800',
            self::BAJA_DEFINITIVA => 'bg-red-100 text-red-800',
            self::EGRESADO => 'bg-sky-100 text-sky-800',
        };
    }

    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }
}
