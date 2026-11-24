<?php

namespace App\Enums;

enum TipoAdeudo: string
{
    case COLEGIATURA = 'COLEGIATURA';
    case INGLES = 'INGLES';
    case OTRO = 'OTRO';

    public function etiqueta(): string
    {
        return match ($this) {
            self::COLEGIATURA => 'Colegiatura',
            self::INGLES => 'Inglés',
            self::OTRO => 'Otro (inscripción, trámite, constancia…)',
        };
    }

    public function generaRecargo(): bool
    {
        return in_array($this->value, config('cobranza.tipos_con_recargo'), true);
    }

    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }
}
