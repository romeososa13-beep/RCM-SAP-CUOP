<?php

namespace App\Models;

use App\Enums\TipoAdeudo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Adeudo extends Model
{
    protected $fillable = [
        'matricula', 'concepto', 'tipo', 'fecha_vencimiento',
        'monto_original', 'recargo_aplicado', 'pagado', 'creado_por',
    ];

    protected $attributes = [
        'recargo_aplicado' => '0.00',
        'pagado' => false,
        'tipo' => 'OTRO',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoAdeudo::class,
            'fecha_vencimiento' => 'date',
            'monto_original' => 'decimal:2',
            'recargo_aplicado' => 'decimal:2',
            'pagado' => 'boolean',
        ];
    }

    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class, 'matricula', 'matricula');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    /** Pagos vigentes (los cancelados quedan en soft delete y no cuentan). */
    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class);
    }

    /* ----------------------------------------------------------------
     |  Accessors de saldo. monto_original NUNCA se modifica: el saldo
     |  siempre se deriva de cargo + recargo - pagos vigentes.
     | ---------------------------------------------------------------- */

    public function getTotalCargoAttribute(): float
    {
        return round((float) $this->monto_original + (float) $this->recargo_aplicado, 2);
    }

    public function getTotalPagadoAttribute(): float
    {
        return round((float) $this->pagos->sum('monto_pagado'), 2);
    }

    public function getSaldoPendienteAttribute(): float
    {
        return round(($this->monto_original + $this->recargo_aplicado) - $this->pagos->sum('monto_pagado'), 2);
    }

    public function getEstaVencidoAttribute(): bool
    {
        return ! $this->pagado && $this->fecha_vencimiento->lt(today());
    }

    /** Desde qué fecha este adeudo causa bloqueo de plataformas (null = nunca). */
    public function getBloqueoDesdeAttribute(): ?\Illuminate\Support\Carbon
    {
        return in_array($this->tipo->value, config('cobranza.tipos_con_bloqueo'), true)
            ? $this->fecha_vencimiento->copy()->addDays(config('cobranza.bloqueo_dias_despues_vencimiento'))
            : null;
    }

    public function getCausaBloqueoAttribute(): bool
    {
        return ! $this->pagado && $this->bloqueo_desde !== null && $this->bloqueo_desde->lte(today());
    }

    /** Adeudos que hoy causan bloqueo de plataformas. */
    public function scopeCausanBloqueo(Builder $query, ?\Carbon\CarbonInterface $fecha = null): Builder
    {
        $fecha ??= today();

        return $query->where('pagado', false)
            ->whereIn('tipo', config('cobranza.tipos_con_bloqueo'))
            ->whereDate('fecha_vencimiento', '<=', $fecha->copy()->subDays(config('cobranza.bloqueo_dias_despues_vencimiento'))->toDateString());
    }

    public function scopePendientes(Builder $query): Builder
    {
        return $query->where('pagado', false);
    }

    public function scopeVencidos(Builder $query): Builder
    {
        return $query->where('pagado', false)->whereDate('fecha_vencimiento', '<', today());
    }
}
