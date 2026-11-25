<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/** Driver de desarrollo: no envía nada, solo registra en el log. */
class LogClient implements WhatsAppClient
{
    public function enviarPlantilla(string $telefono, string $plantilla, string $idioma, array $variables): string
    {
        Log::info('[WhatsApp:log] plantilla', compact('telefono', 'plantilla', 'idioma', 'variables'));

        return 'log.'.Str::uuid();
    }

    public function enviarTexto(string $telefono, string $texto): string
    {
        Log::info('[WhatsApp:log] texto', compact('telefono', 'texto'));

        return 'log.'.Str::uuid();
    }
}
