<?php

namespace App\Support;

use App\Enums\EstatusAlumno;
use App\Enums\FormaPago;
use App\Enums\TipoAdeudo;
use App\Models\Carrera;
use App\Models\PlanPago;
use App\Models\Sede;
use App\Models\User;

/** Traduce los cambios guardados en la bitácora a texto legible. */
class BitacoraFormato
{
    public const ENTIDADES = [
        'alumno' => 'Alumno', 'adeudo' => 'Cargo', 'pago' => 'Pago', 'plan_pago' => 'Plan de pago',
        'sede' => 'Sede', 'carrera' => 'Carrera', 'usuario' => 'Usuario', 'proceso' => 'Proceso automático', 'corte_caja' => 'Corte de caja',
    ];

    public const ACCIONES = [
        'creado' => 'Alta', 'actualizado' => 'Cambio', 'eliminado' => 'Eliminado', 'cancelado' => 'Cancelado',
        'rol' => 'Cambio de rol', 'proceso' => 'Ejecución', 'condonado' => 'Recargo condonado', 'cierre' => 'Cierre de corte', 'reapertura' => 'Reapertura de corte', 'correo' => 'Correo enviado',
    ];

    private const CAMPOS = [
        'matricula' => 'Matrícula', 'nombre_completo' => 'Nombre', 'sede_id' => 'Sede', 'carrera_id' => 'Carrera',
        'grupo' => 'Grupo', 'plan_pago_id' => 'Plan de pago', 'costo_ingles' => 'Inglés mensual', 'estatus' => 'Estatus',
        'comentarios' => 'Comentarios', 'celular' => 'Celular', 'tel_tutor' => 'Tel. tutor', 'tel_alterno' => 'Tel. alterno',
        'concepto' => 'Concepto', 'tipo' => 'Tipo', 'fecha_vencimiento' => 'Vencimiento', 'monto_original' => 'Monto',
        'recargo_aplicado' => 'Recargo', 'pagado_previo' => 'Pagado en sistema anterior', 'exento_recargo' => 'Exento de recargo',
        'creado_por' => 'Capturó', 'origen' => 'Origen', 'folio_pago' => 'Folio', 'adeudo_id' => 'Cargo #',
        'fecha_pago' => 'Fecha de pago', 'forma_pago' => 'Forma de pago', 'monto_pagado' => 'Monto pagado',
        'referencia' => 'Referencia', 'user_id' => 'Cajero', 'motivo_cancelacion' => 'Motivo', 'nombre' => 'Nombre',
        'clave' => 'Clave', 'modalidad' => 'Modalidad', 'monto_mensualidad' => 'Mensualidad', 'monto_recargo' => 'Recargo',
        'dias_tolerancia' => 'Día límite de pago', 'name' => 'Nombre', 'email' => 'Correo', 'password' => 'Contraseña',
        'activo' => 'Activo', 'rol' => 'Rol', 'por_tipo' => 'Por tipo',
        'beca_porcentaje' => 'Beca %', 'beca_ingles' => 'Beca aplica a inglés', 'beca_motivo' => 'Motivo de beca', 'descuento' => 'Descuento (beca)',
    ];

    private array $cache = [];

    public function campo(string $campo): string
    {
        return self::CAMPOS[$campo] ?? str_replace('_', ' ', ucfirst($campo));
    }

    public function valor(string $campo, mixed $v): string
    {
        if ($v === null || $v === '') {
            return '—';
        }
        if (is_bool($v)) {
            return $v ? 'Sí' : 'No';
        }
        if (is_array($v)) {
            return collect($v)->map(fn ($x, $k) => "{$k}: {$x}")->implode(', ');
        }

        if (is_string($v) && preg_match('/^(\d{4})-(\d{2})-(\d{2})(?: (\d{2}:\d{2}):\d{2})?$/', $v, $m)) {
            return "{$m[3]}/{$m[2]}/{$m[1]}".(isset($m[4]) && $m[4] !== '00:00' ? " {$m[4]}" : '');
        }

        return match ($campo) {
            'estatus' => EstatusAlumno::tryFrom($v)?->etiqueta() ?? (string) $v,
            'tipo' => TipoAdeudo::tryFrom($v)?->etiqueta() ?? (string) $v,
            'forma_pago' => FormaPago::tryFrom($v)?->etiqueta() ?? (string) $v,
            'sede_id' => $this->nombre(Sede::class, $v, fn ($s) => $s->clave),
            'carrera_id' => $this->nombre(Carrera::class, $v, fn ($c) => $c->nombre),
            'plan_pago_id' => $this->nombre(PlanPago::class, $v, fn ($p) => $p->nombre.' ($'.number_format($p->monto_mensualidad, 2).')'),
            'creado_por', 'user_id' => $this->nombre(User::class, $v, fn ($u) => $u->name),
            'costo_ingles', 'descuento', 'monto_original', 'recargo_aplicado', 'pagado_previo', 'monto_pagado', 'monto_mensualidad', 'monto_recargo' => '$'.number_format((float) $v, 2),
            default => (string) $v,
        };
    }

    private function nombre(string $clase, mixed $id, callable $formato): string
    {
        $clave = $clase.'#'.$id;
        if (! array_key_exists($clave, $this->cache)) {
            $m = $clase::find($id);
            $this->cache[$clave] = $m ? $formato($m) : "#{$id} (ya no existe)";
        }

        return $this->cache[$clave];
    }
}
