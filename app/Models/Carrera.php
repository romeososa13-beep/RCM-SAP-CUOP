<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Carrera extends Model
{
    use Auditable;

    public function auditEntidad(): string
    {
        return 'carrera';
    }

    public function auditDescripcion(): ?string
    {
        return $this->nombre;
    }

    protected $fillable = ['nombre', 'modalidad', 'activa'];

    public function alumnos(): HasMany
    {
        return $this->hasMany(Alumno::class);
    }

    protected function casts(): array
    {
        return ['activa' => 'boolean'];
    }

    public function scopeActivas(Builder $q): Builder
    {
        return $q->where('activa', true);
    }
}
