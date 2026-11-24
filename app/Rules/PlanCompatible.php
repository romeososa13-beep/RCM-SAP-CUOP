<?php

namespace App\Rules;

use App\Models\PlanPago;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** El plan de pago debe pertenecer a la sede (y carrera, si aplica) del alumno. */
class PlanCompatible implements ValidationRule
{
    public function __construct(private readonly ?int $sedeId, private readonly ?int $carreraId) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $plan = PlanPago::with('sede:id,clave')->find($value);

        if ($plan && ! $plan->esCompatibleCon($this->sedeId, $this->carreraId)) {
            $fail("El plan «{$plan->nombre}» es de ".($plan->sede?->clave ?? 'otra carrera').' y no corresponde a la sede/carrera del alumno.');
        }
    }
}
