<?php

namespace App\Services\WhatsApp;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/** WhatsApp Business Cloud API (graph.facebook.com). */
class CloudApiClient implements WhatsAppClient
{
    public function __construct(
        private readonly string $token,
        private readonly string $phoneNumberId,
        private readonly string $version,
    ) {}

    public function enviarPlantilla(string $telefono, string $plantilla, string $idioma, array $variables): string
    {
        return $this->enviar($telefono, [
            'type' => 'template',
            'template' => [
                'name' => $plantilla,
                'language' => ['code' => $idioma],
                'components' => $variables ? [[
                    'type' => 'body',
                    'parameters' => array_map(fn ($v) => ['type' => 'text', 'text' => (string) $v], array_values($variables)),
                ]] : [],
            ],
        ]);
    }

    public function enviarTexto(string $telefono, string $texto): string
    {
        return $this->enviar($telefono, ['type' => 'text', 'text' => ['preview_url' => false, 'body' => $texto]]);
    }

    private function enviar(string $telefono, array $mensaje): string
    {
        try {
            $r = Http::withToken($this->token)->acceptJson()->timeout(20)
                // Solo se reintenta lo transitorio (red, 5xx); un 4xx de Meta no se arregla reintentando.
                ->retry(2, 500, fn ($e) => $e instanceof ConnectionException
                    || ($e instanceof RequestException && $e->response->serverError()), throw: false)
                ->post("https://graph.facebook.com/{$this->version}/{$this->phoneNumberId}/messages",
                    ['messaging_product' => 'whatsapp', 'recipient_type' => 'individual', 'to' => $telefono] + $mensaje);
        } catch (ConnectionException $e) {
            throw new WhatsAppException('Sin conexión con WhatsApp: '.$e->getMessage(), previous: $e);
        }

        if ($r->failed() || ! $r->json('messages.0.id')) {
            $error = $r->json('error.error_data.details') ?: $r->json('error.message') ?: $r->body();
            throw new WhatsAppException("WhatsApp respondió {$r->status()}: ".mb_substr((string) $error, 0, 300));
        }

        return $r->json('messages.0.id');
    }
}
