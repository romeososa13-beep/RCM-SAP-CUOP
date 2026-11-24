<?php

namespace App\Models;

use App\Enums\EstatusAlumno;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Alumno extends Model
{
    protected $primaryKey = 'matricula';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'matricula', 'nombre_completo', 'sede_id', 'carrera_id',
        'grupo', 'plan_pago_id', 'costo_ingles', 'estatus', 'comentarios',
    ];

    protected $attributes = [
        'estatus' => 'ACTIVO',
    ];

    protected function casts(): array
    {
        return [
            'estatus' => EstatusAlumno::class,
            'costo_ingles' => 'decimal:2',
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

    public function planPago(): BelongsTo
    {
        return $this->belongsTo(PlanPago::class, 'plan_pago_id');
    }

    public function adeudos(): HasMany
    {
        return $this->hasMany(Adeudo::class, 'matricula', 'matricula');
    }

    public function pagos(): HasManyThrough
    {
        return $this->hasManyThrough(Pago::class, Adeudo::class, 'matricula', 'adeudo_id', 'matricula', 'id');
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('estatus', EstatusAlumno::ACTIVO);
    }

    /** Alumnos activos que hoy deben colegiatura o inglés con más de N días de vencido. */
    public function scopeConBloqueo(Builder $query, ?\Carbon\CarbonInterface $fecha = null): Builder
    {
        return $query->activos()->whereHas('adeudos', fn ($q) => $q->causanBloqueo($fecha));
    }

    /** Búsqueda por matrícula (prefijo) o nombre (contiene). */
    public function scopeBuscar(Builder $query, ?string $termino): Builder
    {
        $termino = trim((string) $termino);

        if ($termino === '') {
            return $query;
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $termino).'%';

        return $query->where(fn (Builder $q) => $q
            ->where('matricula', 'like', str_replace(['%', '_'], ['\%', '\_'], $termino).'%')
            ->orWhere('nombre_completo', 'like', $like));
    }
}
