<?php

namespace App\Services\WhatsApp;

use App\Models\Alumno;
use App\Models\WhatsappMensaje;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Chatbot por reglas (sin IA): responde dentro de la ventana de 24 h que abre el alumno.
 * Solo da información del alumno cuyo celular / tel. de tutor está registrado con ESE número.
 */
class Chatbot
{
    public const MENU = "Escribe el número de la opción:\n1️⃣ Mi saldo\n2️⃣ Fechas de pago y recargos\n3️⃣ Cómo pagar\n4️⃣ Hablar con una persona";

    public function __construct(private readonly Mensajeria $mensajeria) {}

    public function responder(WhatsappMensaje $entrante): ?WhatsappMensaje
    {
        $texto = Str::of($entrante->cuerpo)->lower()->ascii()->squish()->toString();
        $alumnos = Mensajeria::alumnosDelTelefono($entrante->telefono);

        // Consentimiento: siempre se atiende, aunque una persona esté conversando.
        if (preg_match('/^(baja|stop|cancelar|no molestar)\b/', $texto)) {
            Alumno::whereIn('matricula', $alumnos->pluck('matricula'))->update(['whatsapp_baja_at' => now()]);

            return $this->decir($entrante, 'Listo, ya no te enviaremos avisos por WhatsApp. Si cambias de opinión escribe ALTA.');
        }
        if (preg_match('/^(alta|start)\b/', $texto)) {
            Alumno::whereIn('matricula', $alumnos->pluck('matricula'))->update(['whatsapp_baja_at' => null]);

            return $this->decir($entrante, '¡Gracias! Volverás a recibir tus avisos de pago.');
        }

        // Si alguien del personal respondió en las últimas 2 h, el bot no interrumpe.
        if (WhatsappMensaje::where('telefono', $entrante->telefono)->where('direccion', 'saliente')->where('tipo', 'texto')
            ->where('created_at', '>=', now()->subHours(2))->exists()) {
            $entrante->update(['requiere_atencion' => true]);

            return null;
        }

        if ($alumnos->isEmpty()) {
            $entrante->update(['requiere_atencion' => true]);

            return $this->decir($entrante, 'Hola 👋 Este número no está registrado con ningún alumno, por eso no podemos darte información de pagos. '
                .'Acude a Control Escolar de tu sede para actualizar tu celular. Un asesor revisará tu mensaje.');
        }

        return match (true) {
            in_array($texto, ['1', 'saldo', 'mi saldo', 'cuanto debo', 'adeudo'], true) => $this->decir($entrante, $this->saldo($alumnos)),
            in_array($texto, ['2', 'fechas', 'recargos', 'fechas de pago'], true) => $this->decir($entrante, $this->fechas()),
            in_array($texto, ['3', 'como pagar', 'pagar', 'transferencia', 'datos bancarios'], true) => $this->decir($entrante,
                config('cobranza.datos_pago')."\n\nHorario de caja: ".config('cobranza.horario_atencion').'.'),
            in_array($texto, ['4', 'asesor', 'persona', 'humano', 'ayuda'], true) => $this->asesor($entrante),
            (bool) preg_match('/^(hola|buen[oa]s|menu|inicio|hi)\b/', $texto) => $this->decir($entrante,
                'Hola '.Plantillas::nombre($alumnos->first())." 👋 Soy el asistente de cobranza.\n\n".self::MENU),
            default => $this->noEntendi($entrante),
        };
    }

    private function saldo(Collection $alumnos): string
    {
        return $alumnos->map(function (Alumno $a) {
            $pend = $a->adeudos()->where('pagado', false)->with('pagos:id,adeudo_id,monto_pagado')->orderBy('fecha_vencimiento')->get();
            if ($pend->isEmpty()) {
                return "✅ {$a->matricula} · ".Str::title($a->nombre_completo).': estás al corriente.';
            }
            $vencido = $pend->filter->esta_vencido->sum('saldo_pendiente');
            $lineas = $pend->take(6)->map(fn ($d) => "• {$d->concepto}: $".number_format($d->saldo_pendiente, 2)
                .($d->esta_vencido ? ' (vencido)' : ' (vence '.$d->fecha_vencimiento->format('d/m').')'));
            $bloqueo = Alumno::conBloqueo()->whereKey($a->matricula)->exists() ? "\n⛔ Tienes bloqueo de plataformas hasta liquidar lo vencido." : '';

            return "📄 {$a->matricula} · ".Str::title($a->nombre_completo)."\n".$lineas->implode("\n")
                .($pend->count() > 6 ? "\n• …y ".($pend->count() - 6).' más' : '')
                ."\nTotal: $".number_format($pend->sum('saldo_pendiente'), 2).($vencido > 0 ? ' · vencido: $'.number_format($vencido, 2) : '').$bloqueo;
        })->implode("\n\n")."\n\nEscribe 3 para ver cómo pagar.";
    }

    private function fechas(): string
    {
        $bloqueo = 10 + config('cobranza.bloqueo_dias_despues_vencimiento');

        return "📅 Del día 1 al 10 de cada mes pagas tu colegiatura según tu plan.\n"
            .'Desde el día 11 se agrega un recargo ($180 en colegiatura y '.rtrim(rtrim(number_format(config('cobranza.recargo_ingles_porcentaje'), 2), '0'), '.')."% en inglés).\n"
            ."Desde el día {$bloqueo} se bloquea el acceso a plataformas hasta liquidar.";
    }

    private function asesor(WhatsappMensaje $entrante): WhatsappMensaje
    {
        $entrante->update(['requiere_atencion' => true]);

        return $this->decir($entrante, 'Un asesor te responderá por aquí en horario de atención ('.config('cobranza.horario_atencion').'). Gracias por tu paciencia.');
    }

    private function noEntendi(WhatsappMensaje $entrante): WhatsappMensaje
    {
        $entrante->update(['requiere_atencion' => true]);

        return $this->decir($entrante, "Recibimos tu mensaje y un asesor lo revisará.\n\n".self::MENU);
    }

    private function decir(WhatsappMensaje $entrante, string $texto): ?WhatsappMensaje
    {
        return $this->mensajeria->texto($entrante->telefono, $texto, null, 'bot', $entrante->matricula);
    }
}
