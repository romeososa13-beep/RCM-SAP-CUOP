<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlanPago extends Model
{
    protected $table = 'planes_pago';

    protected $fillable = ['nombre', 'monto_mensualidad', 'monto_recargo', 'dias_tolerancia'];

    protected function casts(): array
    {
        return [
            'monto_mensualidad' => 'decimal:2',
            'monto_recargo' => 'decimal:2',
            'dias_tolerancia' => 'integer',
        ];
    }

    public function alumnos(): HasMany
    {
        return $this->hasMany(Alumno::class, 'plan_pago_id');
    }
}
