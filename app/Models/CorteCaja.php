<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CorteCaja extends Model
{
    protected $table = 'cortes_caja';

    protected $fillable = ['user_id', 'fecha', 'num_pagos', 'total', 'por_forma', 'cerrado_at', 'cerrado_por',
        'reabierto_at', 'reabierto_por', 'motivo_reapertura'];

    protected function casts(): array
    {
        return ['fecha' => 'date', 'total' => 'decimal:2', 'por_forma' => 'array',
            'cerrado_at' => 'datetime', 'reabierto_at' => 'datetime'];
    }

    public function cajero(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function cerradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cerrado_por');
    }

    public function reabiertoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reabierto_por');
    }

    public function getEstaCerradoAttribute(): bool
    {
        return $this->reabierto_at === null;
    }

    /** ¿El corte de ese cajero en ese día está cerrado? */
    public static function cerrado(int $cajeroId, CarbonInterface|string $fecha): bool
    {
        $dia = $fecha instanceof CarbonInterface ? $fecha->toDateString() : substr($fecha, 0, 10);

        return static::where('user_id', $cajeroId)->whereDate('fecha', $dia)->whereNull('reabierto_at')->exists();
    }
}
