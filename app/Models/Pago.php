<?php

namespace App\Models;

use App\Enums\FormaPago;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pago extends Model
{
    use Auditable;

    /** Solo se registra la cancelación (crear un pago ya deja folio, cajero y fecha). */
    protected array $auditEventos = ['deleted'];

    protected array $auditIgnorar = ['cancelado_por'];

    public function auditEntidad(): string
    {
        return 'pago';
    }

    public function auditDescripcion(): ?string
    {
        return ($this->adeudo?->matricula ? $this->adeudo->matricula.' · ' : '').'Folio '.$this->folio_pago.' · $'.number_format((float) $this->monto_pagado, 2).($this->motivo_cancelacion ? ' · '.$this->motivo_cancelacion : '');
    }

    use SoftDeletes;

    protected $fillable = [
        'folio_pago', 'adeudo_id', 'fecha_pago', 'forma_pago',
        'monto_pagado', 'referencia', 'user_id',
        'cancelado_por', 'motivo_cancelacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha_pago' => 'datetime',
            'forma_pago' => FormaPago::class,
            'monto_pagado' => 'decimal:2',
        ];
    }

    public function adeudo(): BelongsTo
    {
        return $this->belongsTo(Adeudo::class);
    }

    public function cajero(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function canceladoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelado_por');
    }
}
