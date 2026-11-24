<?php

namespace App\Models\Concerns;

use App\Models\Bitacora;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;

/**
 * Registra en la bitácora la creación, cambios y borrado del modelo.
 * Cada modelo define:
 *   - auditEntidad(): nombre corto (alumno, plan_pago…)
 *   - $auditIgnorar: campos que no se registran (además de timestamps)
 *   - $auditOcultar: campos sensibles; se registra que cambiaron, no su valor
 *   - $auditEventos: eventos a registrar (por defecto created, updated, deleted)
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (Model $m) {
            if ($m->auditaEvento('created')) {
                $m->auditar('creado', $m->auditCampos(array_keys($m->getAttributes()), true));
            }
        });
        static::updated(function (Model $m) {
            $cambios = $m->auditaEvento('updated') ? $m->auditCampos(array_keys($m->getChanges())) : [];
            if ($cambios) {
                $m->auditar('actualizado', $cambios);
            }
        });
        static::deleted(function (Model $m) {
            if ($m->auditaEvento('deleted')) {
                $m->auditar(method_exists($m, 'trashed') && $m->trashed() ? 'cancelado' : 'eliminado',
                    $m->auditCampos(array_keys($m->getAttributes()), false, true));
            }
        });
    }

    protected function auditaEvento(string $evento): bool
    {
        return in_array($evento, $this->auditEventos ?? ['created', 'updated', 'deleted'], true);
    }

    abstract public function auditEntidad(): string;

    public function auditDescripcion(): ?string
    {
        return null;
    }

    protected function auditar(string $accion, array $cambios): void
    {
        Bitacora::registrar($accion, $this->auditEntidad(), $this->getKey(), $this->auditDescripcion(), $cambios);
    }

    private function auditCampos(array $campos, bool $soloDespues = false, bool $soloAntes = false): array
    {
        $ignorar = array_merge(['created_at', 'updated_at', 'deleted_at', 'remember_token'], $this->auditIgnorar ?? []);
        $ocultar = $this->auditOcultar ?? [];
        $out = [];

        foreach ($campos as $campo) {
            if (in_array($campo, $ignorar, true)) {
                continue;
            }
            $antes = $soloDespues ? null : $this->valorAuditable($this->getOriginal($campo));
            $despues = $soloAntes ? null : $this->valorAuditable($this->getAttribute($campo));

            if (! $soloDespues && ! $soloAntes && $antes === $despues) {
                continue;
            }
            if (($soloDespues && $despues === null) || ($soloAntes && $antes === null)) {
                continue;
            }
            // En altas y bajas se omiten la llave y los valores por defecto (0, No, SISTEMA): solo ruido.
            if (($soloDespues || $soloAntes) && ($campo === $this->getKeyName()
                || in_array($soloDespues ? $despues : $antes, [false, 0, '0', '0.00', 'SISTEMA', ''], true))) {
                continue;
            }
            $out[$campo] = in_array($campo, $ocultar, true)
                ? ['antes' => $soloDespues ? null : '••••', 'despues' => $soloAntes ? null : '••••']
                : ['antes' => $antes, 'despues' => $despues];
        }

        return $out;
    }

    private function valorAuditable(mixed $v): mixed
    {
        return match (true) {
            $v instanceof BackedEnum => $v->value,
            $v instanceof \DateTimeInterface => $v->format('Y-m-d H:i:s'),
            is_bool($v) => $v,
            is_numeric($v) && ! is_string($v) => $v,
            default => $v === null ? null : (string) $v,
        };
    }
}
