<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappMensaje extends Model
{
    protected $table = 'whatsapp_mensajes';

    protected $fillable = ['telefono', 'matricula', 'direccion', 'tipo', 'plantilla', 'cuerpo', 'variables',
        'estado', 'wa_id', 'error', 'requiere_atencion', 'user_id', 'campana_id'];

    protected function casts(): array
    {
        return ['variables' => 'array', 'requiere_atencion' => 'boolean'];
    }

    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class, 'matricula', 'matricula');
    }

    public function campana(): BelongsTo
    {
        return $this->belongsTo(CampanaCobranza::class, 'campana_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeDeConversacion(Builder $q, string $telefono): Builder
    {
        return $q->where('telefono', $telefono);
    }

    /** ¿Se puede mandar texto libre? Solo dentro de las 24 h desde el último mensaje del alumno. */
    public static function ventanaAbierta(string $telefono): bool
    {
        return static::where('telefono', $telefono)->where('direccion', 'entrante')
            ->where('created_at', '>=', now()->subHours(24))->exists();
    }
}
