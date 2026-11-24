<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sede extends Model
{
    use Auditable;

    public function auditEntidad(): string
    {
        return 'sede';
    }

    public function auditDescripcion(): ?string
    {
        return $this->clave.' · '.$this->nombre;
    }

    protected $fillable = ['clave', 'nombre'];

    public function alumnos(): HasMany
    {
        return $this->hasMany(Alumno::class);
    }
}
