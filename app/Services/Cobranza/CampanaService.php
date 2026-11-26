<?php

namespace App\Services\Cobranza;

use App\Models\Adeudo;
use App\Models\Alumno;
use App\Models\Bitacora;
use App\Models\CampanaCobranza;
use App\Models\Pago;
use App\Models\User;
use App\Services\WhatsApp\Mensajeria;
use App\Services\WhatsApp\Plantillas;
use App\Services\WhatsApp\Telefono;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Campañas de cobranza por WhatsApp: elegir a quién (segmento + filtros),
 * enviar la plantilla y medir quién pagó después del mensaje.
 */
class CampanaService
{
    public const SEGMENTOS = [
        'por_vencer' => ['Por vencer este mes', 'recordatorio_pago', 'Deben la colegiatura o el inglés del mes y aún están en tolerancia.'],
        'con_recargo' => ['Con recargo (sin bloqueo)', 'aviso_recargo', 'Ya pasó el día 10 y tienen recargo, pero todavía no llegan al bloqueo.'],
        'con_bloqueo' => ['Con bloqueo de plataformas', 'aviso_bloqueo', 'Adeudo con bloqueo de plataformas.'],
        'atraso' => ['Por días de atraso', 'aviso_bloqueo', 'El adeudo pendiente más antiguo tiene al menos N días de vencido.'],
    ];

    /** Plantillas que se pueden usar en una campaña de cobro. */
    public const PLANTILLAS = ['recordatorio_pago', 'aviso_recargo', 'aviso_bloqueo'];

    public function __construct(private Mensajeria $mensajeria) {}

    /**
     * Alumnos ACTIVOS del segmento, con saldo de colegiatura/inglés, días de atraso y si se les puede escribir.
     *
     * @param  array{sede_id?:?int, carrera_id?:?int, grupo?:?string, dias_min?:?int, saldo_min?:?float}  $filtros
     * @return Collection<int, array{alumno:Alumno, saldo:float, dias_atraso:int, conceptos:string, contacto:?string}>
     */
    public function destinatarios(string $segmento, array $filtros = []): Collection
    {
        abort_unless(isset(self::SEGMENTOS[$segmento]), 422, 'Segmento desconocido.');
        $hoy = today();
        $diasMin = max(1, (int) ($filtros['dias_min'] ?? 30));

        return Alumno::query()->activos()
            ->when($filtros['sede_id'] ?? null, fn ($q, $v) => $q->where('sede_id', $v))
            ->when($filtros['carrera_id'] ?? null, fn ($q, $v) => $q->where('carrera_id', $v))
            ->when(trim((string) ($filtros['grupo'] ?? '')) !== '', fn ($q) => $q->where('grupo', 'like', '%'.trim($filtros['grupo']).'%'))
            ->whereHas('adeudos', fn ($q) => $q->where('pagado', false)->whereIn('tipo', ['COLEGIATURA', 'INGLES'])
                ->whereDate('fecha_vencimiento', '<=', $hoy->copy()->endOfMonth()->toDateString()))
            ->with([
                'sede:id,clave', 'carrera:id,nombre',
                'adeudos' => fn ($q) => $q->where('pagado', false)->whereIn('tipo', ['COLEGIATURA', 'INGLES'])
                    ->whereDate('fecha_vencimiento', '<=', $hoy->copy()->endOfMonth()->toDateString())
                    ->with('pagos:id,adeudo_id,monto_pagado')->orderBy('fecha_vencimiento'),
            ])
            ->orderBy('nombre_completo')
            ->get()
            ->filter(function (Alumno $a) use ($segmento, $hoy, $diasMin) {
                $ad = $a->adeudos;
                $bloqueo = $ad->contains(fn (Adeudo $d) => $d->causa_bloqueo);

                return match ($segmento) {
                    'por_vencer' => $ad->contains(fn (Adeudo $d) => $d->fecha_vencimiento->isSameMonth($hoy) && $d->fecha_vencimiento->gte($hoy)),
                    'con_recargo' => ! $bloqueo && $ad->contains(fn (Adeudo $d) => (float) $d->recargo_aplicado > 0),
                    'con_bloqueo' => $bloqueo,
                    'atraso' => $this->diasAtraso($a) >= $diasMin,
                };
            })
            ->map(fn (Alumno $a) => [
                'alumno' => $a,
                'saldo' => round($a->adeudos->sum(fn (Adeudo $d) => $d->saldo_pendiente), 2),
                'dias_atraso' => $this->diasAtraso($a),
                'conceptos' => $a->adeudos->pluck('concepto')->implode(', '),
                'contacto' => $this->sinContacto($a),
            ])
            ->filter(fn ($f) => $f['saldo'] > 0 && $f['saldo'] >= (float) ($filtros['saldo_min'] ?? 0))
            ->values();
    }

    /** Días desde el vencimiento pendiente más antiguo (0 si nada está vencido). */
    private function diasAtraso(Alumno $a): int
    {
        $primero = $a->adeudos->first(fn (Adeudo $d) => $d->fecha_vencimiento->lt(today()));

        return $primero ? (int) $primero->fecha_vencimiento->diffInDays(today()) : 0;
    }

    /** Motivo por el que no se le puede escribir, o null si sí. */
    private function sinContacto(Alumno $a): ?string
    {
        if ($a->whatsapp_baja_at) {
            return 'Pidió BAJA';
        }

        return Telefono::clave($a->celular) || Telefono::clave($a->tel_tutor) ? null : 'Sin celular';
    }

    /** Crea la campaña y encola un mensaje por alumno que se pueda contactar. */
    public function lanzar(string $segmento, string $plantilla, array $filtros, string $nombre, User $usuario): CampanaCobranza
    {
        abort_unless(in_array($plantilla, self::PLANTILLAS, true), 422, 'Plantilla no permitida para cobranza.');
        $filas = $this->destinatarios($segmento, $filtros);

        $campana = CampanaCobranza::create([
            'nombre' => mb_substr(trim($nombre) ?: self::SEGMENTOS[$segmento][0].' '.now()->format('d/m/Y H:i'), 0, 120),
            'segmento' => $segmento, 'plantilla' => $plantilla, 'filtros' => array_filter($filtros, fn ($v) => $v !== null && $v !== ''),
            'destinatarios' => $filas->count(), 'saldo_objetivo' => round($filas->sum('saldo'), 2), 'user_id' => $usuario->id,
        ]);

        $enCola = 0;
        $omitidos = [];
        foreach ($filas as $f) {
            [$m, $motivo] = $f['contacto'] ? [null, $f['contacto']]
                : $this->mensajeria->plantilla($f['alumno'], $plantilla, $usuario, campanaId: $campana->id);
            $m ? $enCola++ : ($omitidos[$motivo] = ($omitidos[$motivo] ?? 0) + 1);
        }
        $campana->update(['en_cola' => $enCola, 'omitidos' => $omitidos]);

        Bitacora::registrar('proceso', 'campana_cobranza', $campana->id,
            "Campaña «{$campana->nombre}» ({$plantilla}): {$enCola} mensajes en cola de {$filas->count()} destinatarios");

        return $campana;
    }

    /**
     * Resultado de la campaña: estados de los mensajes y lo cobrado a esos alumnos desde el envío.
     *
     * @return array{estados: array<string,int>, alumnos_pagaron:int, recuperado:float, contactados:int, detalle: Collection}
     */
    public function resultados(CampanaCobranza $campana): array
    {
        $mensajes = $campana->mensajes()->with('alumno:matricula,nombre_completo,grupo')->get();
        $matriculas = $mensajes->pluck('matricula')->filter()->unique()->values();

        $pagos = Pago::query()->join('adeudos', 'adeudos.id', '=', 'pagos.adeudo_id')
            ->whereIn('adeudos.matricula', $matriculas)
            ->where('pagos.fecha_pago', '>=', $campana->created_at)
            ->groupBy('adeudos.matricula')
            ->select('adeudos.matricula', DB::raw('SUM(pagos.monto_pagado) as monto'), DB::raw('MIN(pagos.fecha_pago) as primero'))
            ->get()->keyBy('matricula');

        $contactados = $mensajes->where('estado', '!=', 'fallido')->count();

        return [
            'estados' => $mensajes->countBy('estado')->all(),
            'contactados' => $contactados,
            'alumnos_pagaron' => $pagos->count(),
            'recuperado' => round((float) $pagos->sum('monto'), 2),
            'detalle' => $mensajes->map(fn ($m) => [
                'mensaje' => $m,
                'pago' => isset($pagos[$m->matricula]) ? round((float) $pagos[$m->matricula]->monto, 2) : null,
            ]),
        ];
    }

    public static function plantillaPorDefecto(string $segmento): string
    {
        return self::SEGMENTOS[$segmento][1] ?? 'recordatorio_pago';
    }

    public static function etiquetaPlantilla(string $clave): string
    {
        return Plantillas::ETIQUETAS[$clave] ?? $clave;
    }
}
