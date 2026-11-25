<?php

namespace App\Models;

use App\Enums\EstatusAlumno;
use App\Models\Concerns\Auditable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Alumno extends Model
{
    use Auditable;

    public function auditEntidad(): string
    {
        return 'alumno';
    }

    public function auditDescripcion(): ?string
    {
        return $this->nombre_completo;
    }

    protected $primaryKey = 'matricula';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'matricula', 'nombre_completo', 'sede_id', 'carrera_id',
        'grupo', 'plan_pago_id', 'costo_ingles', 'beca_porcentaje', 'beca_ingles', 'beca_motivo', 'celular', 'tel_tutor', 'tel_alterno', 'email', 'estatus', 'comentarios',
    ];

    protected $attributes = [
        'estatus' => 'ACTIVO',
    ];

    protected function casts(): array
    {
        return [
            'estatus' => EstatusAlumno::class,
            'costo_ingles' => 'decimal:2',
            'beca_porcentaje' => 'decimal:2',
            'beca_ingles' => 'boolean',
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

    /** Aplica la beca del alumno a un monto. Devuelve [monto con descuento, descuento]. */
    public function aplicarBeca(float|string $monto, bool $esIngles = false): array
    {
        $pct = (float) $this->beca_porcentaje;
        if ($pct <= 0 || ($esIngles && ! $this->beca_ingles)) {
            return [round((float) $monto, 2), 0.0];
        }
        $descuento = round((float) $monto * $pct / 100, 2);

        return [round((float) $monto - $descuento, 2), $descuento];
    }

    /** Deja solo dígitos; "Sin teléfono" o textos sin número → null. */
    public static function normalizarTelefono(mixed $valor): ?string
    {
        $digitos = preg_replace('/\D+/', '', (string) $valor);

        return strlen($digitos) >= 7 ? substr($digitos, -15) : null;
    }

    public function getTelefonosAttribute(): array
    {
        return array_filter(['Celular' => $this->celular, 'Tutor' => $this->tel_tutor, 'Alterno' => $this->tel_alterno]);
    }

    /** Alumnos activos que hoy deben colegiatura o inglés con más de N días de vencido. */
    public function scopeConBloqueo(Builder $query, ?CarbonInterface $fecha = null): Builder
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
