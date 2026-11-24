<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Adeudo extends Model
{
    protected $fillable = [
        'matricula', 'concepto', 'fecha_vencimiento',
        'monto_original', 'recargo_aplicado', 'pagado',
    ];

    protected $attributes = [
        'recargo_aplicado' => '0.00',
        'pagado' => false,
    ];

    protected function casts(): array
    {
        return [
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

    public function scopePendientes(Builder $query): Builder
    {
        return $query->where('pagado', false);
    }

    public function scopeVencidos(Builder $query): Builder
    {
        return $query->where('pagado', false)->whereDate('fecha_vencimiento', '<', today());
    }
}
