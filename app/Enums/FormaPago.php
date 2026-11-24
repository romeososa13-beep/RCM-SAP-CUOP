<?php

namespace App\Enums;

enum FormaPago: string
{
    case EFECTIVO = 'EFECTIVO';
    case TRANSFERENCIA = 'TRANSFERENCIA';
    case TARJETA = 'TARJETA';
    case DEPOSITO = 'DEPOSITO';

    public function etiqueta(): string
    {
        return match ($this) {
            self::EFECTIVO => 'Efectivo',
            self::TRANSFERENCIA => 'Transferencia',
            self::TARJETA => 'Tarjeta',
            self::DEPOSITO => 'Depósito',
        };
    }

    /** Transferencias y depósitos deben llevar referencia bancaria para conciliar. */
    public function requiereReferencia(): bool
    {
        return in_array($this, [self::TRANSFERENCIA, self::DEPOSITO], true);
    }

    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }
}
