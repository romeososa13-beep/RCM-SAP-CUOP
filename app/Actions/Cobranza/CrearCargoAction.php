<?php

namespace App\Actions\Cobranza;

use App\Exceptions\CobranzaException;
use App\Models\Adeudo;
use App\Models\Alumno;
use App\Models\User;
use Carbon\CarbonInterface;

class CrearCargoAction
{
    /** Conceptos sugeridos en caja (el usuario puede escribir otro). */
    public const CONCEPTOS_SUGERIDOS = [
        'INSCRIPCIÓN', 'REINSCRIPCIÓN', 'CREDENCIAL', 'REPOSICIÓN DE CREDENCIAL',
        'CONSTANCIA DE ESTUDIOS', 'KARDEX / HISTORIAL ACADÉMICO', 'EXAMEN EXTRAORDINARIO',
        'EXAMEN A TÍTULO DE SUFICIENCIA', 'TITULACIÓN', 'CERTIFICADO DE ESTUDIOS',
    ];

    public function execute(string $matricula, string $concepto, float|string $monto, CarbonInterface $vencimiento, User $usuario): Adeudo
    {
        $concepto = mb_strtoupper(trim(preg_replace('/\s+/', ' ', $concepto)));
        $centavos = RegistrarPagoAction::aCentavos($monto);

        if ($concepto === '' || mb_strlen($concepto) > 150) {
            throw new CobranzaException('Capture un concepto válido (máximo 150 caracteres).');
        }
        if ($centavos <= 0) {
            throw new CobranzaException('El monto del cargo debe ser mayor a cero.');
        }

        $alumno = Alumno::query()->find($matricula) ?? throw new CobranzaException('El alumno no existe.');

        return $alumno->adeudos()->create([
            'concepto' => $concepto,
            'fecha_vencimiento' => $vencimiento->toDateString(),
            'monto_original' => number_format($centavos / 100, 2, '.', ''),
            'creado_por' => $usuario->getKey(),
        ]);
    }

    /** Elimina un cargo capturado por error. Solo Administrador y solo si nunca tuvo pagos. */
    public function eliminar(int $adeudoId, User $usuario): void
    {
        if (! $usuario->hasRole('Administrador')) {
            throw new CobranzaException('Solo un Administrador puede eliminar cargos.');
        }

        $adeudo = Adeudo::query()->find($adeudoId) ?? throw new CobranzaException('El cargo no existe.');

        if ($adeudo->pagos()->withTrashed()->exists()) {
            throw new CobranzaException('No se puede eliminar: el cargo tiene pagos registrados (vigentes o cancelados).');
        }

        $adeudo->delete();
    }
}
