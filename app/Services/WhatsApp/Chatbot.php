<?php

namespace App\Services\WhatsApp;

use App\Models\Alumno;
use App\Models\Pago;
use App\Models\WhatsappMensaje;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Chatbot: responde dentro de la ventana de 24 h que abre el alumno.
 * BAJA/ALTA, el menú y los números no registrados van siempre por reglas; las preguntas
 * libres de un número registrado las contesta la IA (si está encendida) con los datos
 * de ESE alumno únicamente. Si la IA no está disponible, responde por reglas.
 */
class Chatbot
{
    public const MENU = "Escribe el número de la opción:\n1️⃣ Mi saldo\n2️⃣ Fechas de pago y recargos\n3️⃣ Cómo pagar\n4️⃣ Hablar con una persona";

    public function __construct(private readonly Mensajeria $mensajeria, private readonly AsistenteIa $ia) {}

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
            default => $this->conIa($entrante, $alumnos) ?? $this->noEntendi($entrante),
        };
    }

    /** Pregunta libre: la contesta la IA con los datos del alumno, o null para usar reglas. */
    private function conIa(WhatsappMensaje $entrante, Collection $alumnos): ?WhatsappMensaje
    {
        $usadasHoy = WhatsappMensaje::where('telefono', $entrante->telefono)->where('tipo', 'bot')
            ->where('variables->ia', true)->where('created_at', '>=', today())->count();
        if ($usadasHoy >= config('services.anthropic.max_respuestas_dia')) {
            return null;
        }

        $historial = WhatsappMensaje::where('telefono', $entrante->telefono)->where('id', '<', $entrante->id)
            ->where('created_at', '>=', now()->subHours(24))->latest('id')->limit(8)->get()->reverse()
            ->map(fn ($m) => ['rol' => $m->direccion === 'entrante' ? 'alumno' : 'escuela', 'texto' => $m->cuerpo])->values()->all();

        $r = $this->ia->responder($entrante->cuerpo, $this->contexto($alumnos), $historial);
        if (! $r) {
            return null;
        }
        if ($r['requiere_asesor']) {
            $entrante->update(['requiere_atencion' => true]);
        }

        $m = $this->decir($entrante, $r['respuesta']);
        $m?->update(['variables' => ['ia' => true, 'modelo' => config('services.anthropic.modelo'), 'requiere_asesor' => $r['requiere_asesor']]]);

        return $m;
    }

    /** Hechos verificados que puede usar la IA: solo de los alumnos ligados a este número. */
    public function contexto(Collection $alumnos): string
    {
        $dinero = fn ($v) => '$'.number_format((float) $v, 2);
        $bloques = $alumnos->map(function (Alumno $a) use ($dinero) {
            $a->loadMissing(['sede', 'carrera', 'planPago']);
            $pend = $a->adeudos()->where('pagado', false)->with('pagos:id,adeudo_id,monto_pagado')->orderBy('fecha_vencimiento')->get();
            $pagos = Pago::whereHas('adeudo', fn ($q) => $q->where('matricula', $a->matricula))
                ->with('adeudo:id,concepto')->latest('fecha_pago')->limit(5)->get();
            $bloqueo = Alumno::conBloqueo()->whereKey($a->matricula)->exists();

            return implode("\n", array_filter([
                "Alumno: {$a->nombre_completo} · matrícula {$a->matricula} · estatus {$a->estatus->etiqueta()}",
                "Sede: {$a->sede->nombre} · carrera: {$a->carrera->nombre} · grupo {$a->grupo}",
                $a->planPago ? "Plan: {$a->planPago->nombre}, mensualidad {$dinero($a->planPago->monto_mensualidad)}, recargo {$dinero($a->planPago->monto_recargo)}" : 'Plan: sin plan asignado',
                (float) $a->costo_ingles > 0 ? "Inglés: {$dinero($a->costo_ingles)} al mes" : null,
                (float) $a->beca_porcentaje > 0 ? 'Beca: '.rtrim(rtrim(number_format($a->beca_porcentaje, 2), '0'), '.').'%'.($a->beca_ingles ? ' (también en inglés)' : '') : 'Beca: no tiene',
                'Bloqueo de plataformas: '.($bloqueo ? 'SÍ, hasta liquidar lo vencido de colegiatura o inglés' : 'no'),
                'Pendiente de pago (total '.$dinero($pend->sum('saldo_pendiente')).'):',
                $pend->isEmpty() ? '  (nada: está al corriente)' : $pend->map(fn ($d) => "  • {$d->concepto}: saldo {$dinero($d->saldo_pendiente)}"
                    .((float) $d->recargo_aplicado > 0 ? " (incluye recargo {$dinero($d->recargo_aplicado)})" : '')
                    .', fecha límite '.$d->fecha_vencimiento->format('d/m/Y').($d->esta_vencido ? ' (VENCIDO)' : ''))->implode("\n"),
                'Últimos pagos registrados en caja:',
                $pagos->isEmpty() ? '  (ninguno en este sistema)' : $pagos->map(fn ($p) => "  • {$p->fecha_pago->format('d/m/Y')} folio {$p->folio_pago}: "
                    ."{$dinero($p->monto_pagado)} a {$p->adeudo->concepto} ({$p->forma_pago->etiqueta()})")->implode("\n"),
            ]));
        })->implode("\n\n");

        return 'Fecha de hoy: '.today()->format('d/m/Y')."\n\nDATOS DEL ALUMNO (verificados; el número que escribe está registrado con estas cuentas)\n"
            .$bloques."\n\nREGLAS DE COBRO\n".$this->fechas()
            ."\nCómo pagar: ".config('cobranza.datos_pago')."\nHorario de atención: ".config('cobranza.horario_atencion').'.';
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
