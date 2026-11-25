<?php

namespace App\Services\WhatsApp;

use App\Jobs\EnviarWhatsApp;
use App\Models\Alumno;
use App\Models\Pago;
use App\Models\User;
use App\Models\WhatsappMensaje;

class Mensajeria
{
    /**
     * Encola una plantilla para el alumno. Devuelve [mensaje|null, motivo si no se envió].
     *
     * @return array{0: ?WhatsappMensaje, 1: ?string}
     */
    public function plantilla(Alumno $alumno, string $clave, ?User $usuario = null, ?Pago $pago = null, bool $unaVezAlDia = true): array
    {
        if (! isset(Plantillas::TEXTOS[$clave])) {
            return [null, 'Plantilla desconocida.'];
        }
        if ($alumno->whatsapp_baja_at) {
            return [null, 'El alumno pidió no recibir mensajes (BAJA).'];
        }
        $telefono = Telefono::clave($alumno->celular) ?? Telefono::clave($alumno->tel_tutor);
        if (! $telefono) {
            return [null, 'Sin celular registrado.'];
        }
        if ($unaVezAlDia && WhatsappMensaje::where('matricula', $alumno->matricula)->where('plantilla', $clave)
            ->where('created_at', '>=', today())->where('estado', '!=', 'fallido')->exists()) {
            return [null, 'Ya se le envió este aviso hoy.'];
        }
        $variables = Plantillas::variables($clave, $alumno, $pago);
        if ($variables === null) {
            return [null, 'No aplica: el alumno no tiene adeudos para este aviso.'];
        }

        $m = WhatsappMensaje::create([
            'telefono' => $telefono, 'matricula' => $alumno->matricula, 'direccion' => 'saliente', 'tipo' => 'plantilla',
            'plantilla' => $clave, 'variables' => $variables, 'cuerpo' => Plantillas::vistaPrevia($clave, $variables),
            'estado' => 'en_cola', 'user_id' => $usuario?->id,
        ]);
        EnviarWhatsApp::dispatch($m->id);

        return [$m, null];
    }

    /** Texto libre (personal o chatbot). Solo dentro de la ventana de 24 h. */
    public function texto(string $telefono, string $texto, ?User $usuario = null, string $tipo = 'texto', ?string $matricula = null): ?WhatsappMensaje
    {
        $telefono = Telefono::clave($telefono);
        if (! $telefono || trim($texto) === '' || ! WhatsappMensaje::ventanaAbierta($telefono)) {
            return null;
        }

        $m = WhatsappMensaje::create([
            'telefono' => $telefono, 'matricula' => $matricula, 'direccion' => 'saliente', 'tipo' => $tipo,
            'cuerpo' => mb_substr(trim($texto), 0, 4000), 'estado' => 'en_cola', 'user_id' => $usuario?->id,
        ]);
        EnviarWhatsApp::dispatch($m->id);

        return $m;
    }

    /** Alumnos cuyo celular, teléfono de tutor o alterno es este número. */
    public static function alumnosDelTelefono(string $telefono)
    {
        $clave = Telefono::clave($telefono);
        if (! $clave) {
            return collect();
        }
        $variantes = [$clave, '52'.$clave, '521'.$clave];

        return Alumno::query()->where(fn ($q) => $q->whereIn('celular', $variantes)
            ->orWhereIn('tel_tutor', $variantes)->orWhereIn('tel_alterno', $variantes))
            ->orderBy('nombre_completo')->get();
    }
}
