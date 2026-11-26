<?php

namespace App\Jobs;

use App\Models\WhatsappMensaje;
use App\Services\WhatsApp\Chatbot;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** El webhook responde a Meta de inmediato; el asistente (reglas o IA) contesta desde la cola. */
class ResponderWhatsApp implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;   // reintentar podría duplicar la respuesta al alumno

    public int $timeout = 90;

    public function __construct(public int $entranteId) {}

    public function handle(Chatbot $bot): void
    {
        $m = WhatsappMensaje::find($this->entranteId);
        if ($m && $m->direccion === 'entrante') {
            $bot->responder($m);
        }
    }
}
