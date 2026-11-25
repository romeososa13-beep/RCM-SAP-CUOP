<?php

namespace App\Jobs;

use App\Models\WhatsappMensaje;
use App\Services\WhatsApp\Telefono;
use App\Services\WhatsApp\WhatsAppClient;
use App\Services\WhatsApp\WhatsAppException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class EnviarWhatsApp implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [30, 120];

    public function __construct(public int $mensajeId) {}

    public function handle(WhatsAppClient $cliente): void
    {
        $m = WhatsappMensaje::find($this->mensajeId);
        if (! $m || $m->estado !== 'en_cola') {
            return; // ya se procesó (reintento duplicado)
        }

        $destino = Telefono::internacional($m->telefono);
        $waId = $m->tipo === 'plantilla'
            ? $cliente->enviarPlantilla($destino, config("cobranza.whatsapp_plantillas.{$m->plantilla}", $m->plantilla),
                config('services.whatsapp.idioma'), $m->variables ?? [])
            : $cliente->enviarTexto($destino, $m->cuerpo);

        $m->update(['estado' => 'enviado', 'wa_id' => $waId, 'error' => null]);
    }

    public function failed(Throwable $e): void
    {
        WhatsappMensaje::whereKey($this->mensajeId)->update([
            'estado' => 'fallido',
            'error' => mb_substr($e instanceof WhatsAppException ? $e->getMessage() : get_class($e).': '.$e->getMessage(), 0, 1000),
        ]);
    }
}
