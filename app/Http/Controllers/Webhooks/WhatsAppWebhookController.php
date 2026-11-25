<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\WhatsApp\Webhook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    /** Verificación al registrar el webhook en Meta (hub.mode / hub.verify_token / hub.challenge). */
    public function verificar(Request $request)
    {
        $token = config('services.whatsapp.verify_token');

        if ($request->query('hub_mode') === 'subscribe' && filled($token)
            && hash_equals((string) $token, (string) $request->query('hub_verify_token'))) {
            return response((string) $request->query('hub_challenge'), 200)->header('Content-Type', 'text/plain');
        }

        return response('Token inválido', 403);
    }

    public function recibir(Request $request, Webhook $webhook)
    {
        $secreto = config('services.whatsapp.app_secret');

        // Con la API real la firma es obligatoria: sin ella cualquiera podría inyectar mensajes.
        if (filled($secreto)) {
            $esperada = 'sha256='.hash_hmac('sha256', $request->getContent(), $secreto);
            if (! hash_equals($esperada, (string) $request->header('X-Hub-Signature-256'))) {
                return response('Firma inválida', 403);
            }
        } elseif (config('services.whatsapp.driver') === 'cloud') {
            Log::error('[WhatsApp] Webhook rechazado: falta WHATSAPP_APP_SECRET.');

            return response('Webhook sin configurar', 403);
        }

        $webhook->procesar($request->json()->all());

        return response()->json(['ok' => true]); // Meta reintenta si no recibe 200
    }
}
