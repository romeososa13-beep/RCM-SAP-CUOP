<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Carrera extends Model
{
    protected $fillable = ['nombre', 'modalidad'];

    public function alumnos(): HasMany
    {
        return $this->hasMany(Alumno::class);
    }
}
