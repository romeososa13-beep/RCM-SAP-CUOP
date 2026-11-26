<?php

namespace App\Services\WhatsApp;

interface AsistenteIa
{
    /**
     * Responde una pregunta libre usando SOLO el contexto dado.
     *
     * @param  list<array{rol: 'alumno'|'escuela', texto: string}>  $historial  conversación reciente (más antigua primero)
     * @return array{respuesta: string, requiere_asesor: bool}|null null = no disponible (usar reglas)
     */
    public function responder(string $pregunta, string $contexto, array $historial): ?array;
}
