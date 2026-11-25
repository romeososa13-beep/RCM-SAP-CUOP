<?php

namespace App\Services\WhatsApp;

interface WhatsAppClient
{
    /**
     * Envía una plantilla aprobada por Meta (obligatorio para iniciar conversación).
     *
     * @param  list<string>  $variables  parámetros {{1}}, {{2}}… del cuerpo
     * @return string id del mensaje en WhatsApp
     *
     * @throws WhatsAppException
     */
    public function enviarPlantilla(string $telefono, string $plantilla, string $idioma, array $variables): string;

    /**
     * Texto libre: solo permitido dentro de las 24 h siguientes al último mensaje del alumno.
     *
     * @throws WhatsAppException
     */
    public function enviarTexto(string $telefono, string $texto): string;
}
