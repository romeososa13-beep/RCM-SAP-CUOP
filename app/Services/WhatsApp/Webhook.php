<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsappMensaje;

/** Procesa las notificaciones de Meta: estados de entrega y mensajes entrantes. */
class Webhook
{
    private const RANGO = ['en_cola' => 0, 'enviado' => 1, 'entregado' => 2, 'leido' => 3, 'fallido' => 4];

    private const ESTADOS = ['sent' => 'enviado', 'delivered' => 'entregado', 'read' => 'leido', 'failed' => 'fallido'];

    public function __construct(private readonly Chatbot $bot) {}

    public function procesar(array $payload): void
    {
        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $valor = $change['value'] ?? [];
                foreach ($valor['statuses'] ?? [] as $s) {
                    $this->estado($s);
                }
                foreach ($valor['messages'] ?? [] as $m) {
                    $this->entrante($m);
                }
            }
        }
    }

    private function estado(array $s): void
    {
        $nuevo = self::ESTADOS[$s['status'] ?? ''] ?? null;
        $m = $nuevo ? WhatsappMensaje::where('wa_id', $s['id'] ?? '')->first() : null;
        if (! $m) {
            return;
        }
        // Las notificaciones pueden llegar desordenadas: nunca se retrocede (leído no vuelve a entregado).
        if ($nuevo === 'fallido' || (self::RANGO[$nuevo] > (self::RANGO[$m->estado] ?? 0) && $m->estado !== 'fallido')) {
            $m->update([
                'estado' => $nuevo,
                'error' => $nuevo === 'fallido' ? mb_substr(($s['errors'][0]['title'] ?? 'Error').': '.($s['errors'][0]['error_data']['details'] ?? ''), 0, 1000) : $m->error,
            ]);
        }
    }

    private function entrante(array $m): void
    {
        $waId = $m['id'] ?? null;
        $telefono = Telefono::clave($m['from'] ?? '');
        if (! $waId || ! $telefono || WhatsappMensaje::where('wa_id', $waId)->exists()) {
            return; // Meta reintenta: se ignoran duplicados
        }

        $texto = match ($m['type'] ?? '') {
            'text' => $m['text']['body'] ?? '',
            'button' => $m['button']['text'] ?? '',
            'interactive' => $m['interactive']['button_reply']['title'] ?? $m['interactive']['list_reply']['title'] ?? '',
            default => '['.($m['type'] ?? 'mensaje').' recibido: el asistente solo lee texto]',
        };

        $alumnos = Mensajeria::alumnosDelTelefono($telefono);
        $entrante = WhatsappMensaje::create([
            'telefono' => $telefono, 'matricula' => $alumnos->count() === 1 ? $alumnos->first()->matricula : null,
            'direccion' => 'entrante', 'tipo' => 'texto', 'cuerpo' => mb_substr((string) $texto, 0, 4000) ?: '(vacío)',
            'estado' => 'recibido', 'wa_id' => $waId,
        ]);

        $this->bot->responder($entrante);
    }
}
