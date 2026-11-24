<?php

namespace App\Support;

use NumberFormatter;

class ImporteConLetra
{
    /** 1250.50 → "MIL DOSCIENTOS CINCUENTA PESOS 50/100 M.N." */
    public static function convertir(float|string $importe): string
    {
        $centavosTotales = (int) round((float) $importe * 100);
        $pesos = intdiv($centavosTotales, 100);
        $centavos = $centavosTotales % 100;

        $letras = class_exists(NumberFormatter::class)
            ? (new NumberFormatter('es_MX', NumberFormatter::SPELLOUT))->format($pesos)
            : (string) $pesos;

        $letras = preg_replace('/^uno$/u', 'un', $letras);
        $letras = preg_replace('/ uno$/u', ' un', $letras);

        return sprintf('%s %s %02d/100 M.N.', mb_strtoupper($letras), $pesos === 1 ? 'PESO' : 'PESOS', $centavos);
    }
}
