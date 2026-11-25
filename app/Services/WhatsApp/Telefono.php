<?php

namespace App\Services\WhatsApp;

/** Teléfonos de México para WhatsApp. */
class Telefono
{
    /** Clave de conversación: los últimos 10 dígitos (521XXXXXXXXXX, 52XXXXXXXXXX y XXXXXXXXXX coinciden). */
    public static function clave(?string $telefono): ?string
    {
        $d = preg_replace('/\D+/', '', (string) $telefono);

        return strlen($d) >= 10 ? substr($d, -10) : null;
    }

    /** Formato internacional sin "+" para enviar: 52 + 10 dígitos. */
    public static function internacional(?string $telefono): ?string
    {
        $clave = self::clave($telefono);

        return $clave ? config('services.whatsapp.lada', '52').$clave : null;
    }
}
