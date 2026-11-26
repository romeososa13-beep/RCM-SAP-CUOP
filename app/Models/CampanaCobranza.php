<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CampanaCobranza extends Model
{
    protected $table = 'campanas_cobranza';

    protected $fillable = ['nombre', 'segmento', 'plantilla', 'filtros', 'destinatarios', 'en_cola', 'omitidos', 'saldo_objetivo', 'user_id'];

    protected function casts(): array
    {
        return ['filtros' => 'array', 'omitidos' => 'array', 'saldo_objetivo' => 'decimal:2'];
    }

    public function mensajes(): HasMany
    {
        return $this->hasMany(WhatsappMensaje::class, 'campana_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
