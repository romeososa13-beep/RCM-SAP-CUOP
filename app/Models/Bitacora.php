<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class Bitacora extends Model
{
    protected $table = 'bitacora';

    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'accion', 'entidad', 'entidad_id', 'descripcion', 'cambios', 'origen', 'ip'];

    protected function casts(): array
    {
        return ['cambios' => 'array', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        // La bitácora es de solo escritura: no se edita ni se borra desde la aplicación.
        static::updating(fn () => throw new LogicException('La bitácora no se puede modificar.'));
        static::deleting(fn () => throw new LogicException('La bitácora no se puede borrar.'));
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @param  array<string, array{antes:mixed, despues:mixed}>|null  $cambios
     */
    public static function registrar(string $accion, string $entidad, string|int|null $entidadId, ?string $descripcion = null, ?array $cambios = null): self
    {
        $consola = app()->runningInConsole() && ! app()->runningUnitTests();

        return static::create([
            'user_id' => auth()->id(),
            'accion' => $accion,
            'entidad' => $entidad,
            'entidad_id' => $entidadId === null ? null : (string) $entidadId,
            'descripcion' => $descripcion ? mb_substr($descripcion, 0, 255) : null,
            'cambios' => $cambios ?: null,
            'origen' => $consola ? 'consola' : 'web',
            'ip' => $consola ? null : request()?->ip(),
        ]);
    }

    /** Inserta muchos registros de una vez (importaciones masivas). */
    public static function registrarLote(string $accion, string $entidad, array $registros): void
    {
        $base = [
            'user_id' => auth()->id(), 'accion' => $accion, 'entidad' => $entidad,
            'origen' => app()->runningInConsole() && ! app()->runningUnitTests() ? 'consola' : 'web',
            'ip' => app()->runningInConsole() && ! app()->runningUnitTests() ? null : request()?->ip(),
            'created_at' => now(),
        ];

        foreach (array_chunk($registros, 500) as $lote) {
            static::query()->insert(array_map(fn ($r) => $base + [
                'entidad_id' => (string) $r['entidad_id'],
                'descripcion' => isset($r['descripcion']) ? mb_substr($r['descripcion'], 0, 255) : null,
                'cambios' => isset($r['cambios']) ? json_encode($r['cambios'], JSON_UNESCAPED_UNICODE) : null,
            ], $lote));
        }
    }
}
