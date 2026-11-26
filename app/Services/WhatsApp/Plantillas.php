<?php

namespace App\Services\WhatsApp;

use App\Models\Adeudo;
use App\Models\Alumno;
use App\Models\Pago;
use Illuminate\Support\Str;

/**
 * Plantillas de WhatsApp. El TEXTO debe registrarse igual en Meta (WhatsApp Manager)
 * con categoría «Utilidad» e idioma es_MX; aquí solo se arman sus variables {{1}}, {{2}}…
 */
class Plantillas
{
    public const TEXTOS = [
        'recordatorio_pago' => 'Hola {{1}}, te recordamos que tu pago de {{2}} por ${{3}} vence el {{4}}. Paga a tiempo y evita el recargo. Responde BAJA si no deseas recibir avisos.',
        'aviso_recargo' => 'Hola {{1}}, tu pago de {{2}} ya generó recargo. Tu saldo es de ${{3}}. Si liquidas antes del día 16 evitas el bloqueo de plataformas. Responde BAJA si no deseas recibir avisos.',
        'aviso_bloqueo' => 'Hola {{1}}, por un adeudo de ${{2}} ({{3}}) tu acceso a plataformas está bloqueado. Se libera al liquidar. Responde a este mensaje si necesitas ayuda.',
        'pago_recibido' => 'Hola {{1}}, recibimos tu pago de ${{2}} por {{3}}. Folio {{4}}. ¡Gracias!',
    ];

    public const ETIQUETAS = [
        'recordatorio_pago' => 'Recordatorio de pago (antes del día 10)',
        'aviso_recargo' => 'Aviso de recargo',
        'aviso_bloqueo' => 'Aviso de bloqueo de plataformas',
        'pago_recibido' => 'Confirmación de pago',
    ];

    /** Nombre de pila aproximado: el padrón guarda «APELLIDO APELLIDO NOMBRE(S)». */
    public static function nombre(Alumno $a): string
    {
        $partes = preg_split('/\s+/', trim($a->nombre_completo));

        return Str::title(count($partes) > 2 ? implode(' ', array_slice($partes, 2)) : $a->nombre_completo);
    }

    /**
     * Variables de la plantilla para el alumno, o null si hoy no aplica
     * (p. ej. recordatorio sin nada pendiente del mes).
     *
     * @return list<string>|null
     */
    public static function variables(string $clave, Alumno $a, ?Pago $pago = null): ?array
    {
        // Solo lo exigible hasta este mes: no se cobra por WhatsApp un cargo futuro ya generado.
        $pendientes = $a->adeudos()->where('pagado', false)->whereIn('tipo', ['COLEGIATURA', 'INGLES'])
            ->whereDate('fecha_vencimiento', '<=', today()->endOfMonth()->toDateString())
            ->with('pagos:id,adeudo_id,monto_pagado')->orderBy('fecha_vencimiento')->get();
        $dinero = fn ($v) => number_format($v, 2);

        switch ($clave) {
            case 'recordatorio_pago':
                $mes = $pendientes->filter(fn (Adeudo $d) => $d->fecha_vencimiento->isSameMonth(today()) && $d->fecha_vencimiento->gte(today()));
                if ($mes->isEmpty()) {
                    return null;
                }

                return [self::nombre($a), $mes->pluck('concepto')->implode(' y '), $dinero($mes->sum('saldo_pendiente')),
                    $mes->min('fecha_vencimiento')->format('d/m/Y')];

            case 'aviso_recargo':
                $conRecargo = $pendientes->filter(fn (Adeudo $d) => (float) $d->recargo_aplicado > 0);
                if ($conRecargo->isEmpty()) {
                    return null;
                }

                return [self::nombre($a), $conRecargo->pluck('concepto')->implode(' y '), $dinero($pendientes->sum('saldo_pendiente'))];

            case 'aviso_bloqueo':
                $causas = $a->adeudos()->causanBloqueo()->with('pagos:id,adeudo_id,monto_pagado')->get();
                if ($causas->isEmpty()) {
                    return null;
                }

                return [self::nombre($a), $dinero($causas->sum('saldo_pendiente')), $causas->pluck('concepto')->implode(', ')];

            case 'pago_recibido':
                return $pago ? [self::nombre($a), $dinero($pago->monto_pagado), $pago->adeudo->concepto, $pago->folio_pago] : null;
        }

        return null;
    }

    /** Texto con las variables sustituidas (lo que verá el alumno). */
    public static function vistaPrevia(string $clave, array $variables): string
    {
        return preg_replace_callback('/\{\{(\d+)\}\}/', fn ($m) => $variables[$m[1] - 1] ?? $m[0], self::TEXTOS[$clave]);
    }
}
