<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlanPago extends Model
{
    protected $table = 'planes_pago';

    protected $fillable = ['nombre', 'sede_id', 'carrera_id', 'monto_mensualidad', 'monto_recargo', 'dias_tolerancia'];

    protected function casts(): array
    {
        return [
            'monto_mensualidad' => 'decimal:2',
            'monto_recargo' => 'decimal:2',
            'dias_tolerancia' => 'integer',
        ];
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function carrera(): BelongsTo
    {
        return $this->belongsTo(Carrera::class);
    }

    public function alumnos(): HasMany
    {
        return $this->hasMany(Alumno::class, 'plan_pago_id');
    }

    /** Planes que puede tener un alumno de esa sede y carrera (NULL = válido para todas). */
    public function scopeCompatibleCon(Builder $query, ?int $sedeId, ?int $carreraId): Builder
    {
        return $query
            ->where(fn ($q) => $q->whereNull('sede_id')->when($sedeId, fn ($q) => $q->orWhere('sede_id', $sedeId)))
            ->where(fn ($q) => $q->whereNull('carrera_id')->when($carreraId, fn ($q) => $q->orWhere('carrera_id', $carreraId)));
    }

    public function esCompatibleCon(?int $sedeId, ?int $carreraId): bool
    {
        return ($this->sede_id === null || (int) $this->sede_id === (int) $sedeId)
            && ($this->carrera_id === null || (int) $this->carrera_id === (int) $carreraId);
    }

    /** Ej. "SABATINO 2026 · CUOP-LOR · EDUCACIÓN" para selects y reportes. */
    public function getEtiquetaAttribute(): string
    {
        return collect([$this->nombre, $this->sede?->clave ?? 'TODAS LAS SEDES', $this->carrera?->nombre])
            ->filter()->implode(' · ');
    }
}
