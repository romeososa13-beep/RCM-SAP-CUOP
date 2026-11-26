<?php

namespace App\Services\Padron;

use App\Enums\EstatusAlumno;
use App\Imports\AlumnosImport;
use App\Models\Alumno;
use App\Models\Bitacora;
use App\Models\Carrera;
use App\Models\PlanPago;
use App\Models\Sede;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class ImportarAlumnosService
{
    /** Campos que se actualizan cuando la matrícula ya existe. */
    public const CAMPOS_ACTUALIZABLES = ['grupo', 'carrera_id', 'plan_pago_id', 'estatus', 'costo_ingles', 'celular', 'tel_tutor', 'tel_alterno', 'email'];

    /**
     * Lee y valida el archivo SIN escribir en la base de datos.
     *
     * @return array{crear: list<array>, actualizar: list<array>, sin_cambios: int, errores: list<array>, total: int}
     */
    public function analizar(string $rutaArchivo): array
    {
        $import = new AlumnosImport;
        Excel::import($import, $rutaArchivo);

        return $this->analizarFilas($import->filas);
    }

    public function analizarFilas(Collection $filas): array
    {
        $sedes = Sede::activas()->pluck('id', 'clave')->mapWithKeys(fn ($id, $k) => [mb_strtoupper($k) => $id]);
        $carreras = Carrera::activas()->pluck('id', 'nombre')->mapWithKeys(fn ($id, $k) => [mb_strtoupper($k) => $id]);
        $planes = PlanPago::with('sede:id,clave')->get(['id', 'nombre', 'sede_id', 'carrera_id'])->keyBy('id');

        $matriculas = $filas->map(fn ($f) => mb_strtoupper((string) ($f['matricula'] ?? '')))->filter()->unique();
        $existentes = $matriculas->chunk(1000)
            ->flatMap(fn ($chunk) => Alumno::whereIn('matricula', $chunk->all())->get())
            ->keyBy('matricula');

        $resultado = ['crear' => [], 'actualizar' => [], 'sin_cambios' => 0, 'errores' => [], 'total' => $filas->count()];
        $vistas = [];

        foreach ($filas as $i => $f) {
            $numFila = $i + 2; // +1 por encabezado, +1 por base 1
            $mat = mb_strtoupper((string) ($f['matricula'] ?? ''));
            $existe = $existentes->get($mat);

            $plan = (string) ($f['plan_pago'] ?? '');
            $datos = [
                'matricula' => $mat,
                'nombre_completo' => mb_strtoupper((string) ($f['nombre_completo'] ?? '')),
                'sede_id' => $sedes[mb_strtoupper((string) ($f['sede'] ?? ''))] ?? null,
                'carrera_id' => $carreras[mb_strtoupper((string) ($f['carrera'] ?? ''))] ?? null,
                'grupo' => mb_strtoupper((string) ($f['grupo'] ?? '')),
                'plan_pago_id' => null, // se resuelve abajo según la sede/carrera del alumno
                // INGLES (opcional): costo mensual; vacío = sin cambio; 0 = deja de cursar inglés.
                // Teléfonos (opcionales): solo dígitos; vacío = sin cambio.
                'celular' => Alumno::normalizarTelefono($f['celular'] ?? null),
                'tel_tutor' => Alumno::normalizarTelefono($f['tel_tutor'] ?? null),
                'tel_alterno' => Alumno::normalizarTelefono($f['tel_alterno'] ?? null),
                'email' => filled($f['email'] ?? null) ? mb_strtolower(trim((string) $f['email'])) : null,
                'costo_ingles' => ! filled($f['ingles'] ?? null) ? null
                    : (is_numeric($f['ingles']) ? number_format((float) $f['ingles'], 2, '.', '') : (string) $f['ingles']),
                'estatus' => mb_strtoupper(str_replace(' ', '_', (string) ($f['estatus'] ?? ''))) ?: 'ACTIVO',
            ];

            // Sede y carrera con las que quedará el alumno (la sede no cambia en una actualización).
            $sedeFinal = $existe ? $existe->sede_id : $datos['sede_id'];
            $carreraFinal = $datos['carrera_id'] ?? $existe?->carrera_id;
            $avisoPlan = null;

            if ($plan !== '') {
                [$datos['plan_pago_id'], $avisoPlan] = $this->resolverPlan($plan, $planes, $sedeFinal, $carreraFinal);
            }

            $req = $existe ? 'nullable' : 'required';
            $v = Validator::make(
                $datos,
                [
                    'matricula' => ['required', 'max:20', 'regex:/^[A-Z0-9\-]+$/'],
                    'nombre_completo' => [$req, 'max:200'],
                    'sede_id' => [$req],
                    'carrera_id' => [Rule::requiredIf(! $existe || filled($f['carrera'] ?? null))],
                    'grupo' => [$req, 'max:80'],
                    // Obligatorio si viene en el archivo (debe resolverse) o si el alumno quedará ACTIVO sin plan.
                    'plan_pago_id' => [Rule::requiredIf(filled($plan) || ($datos['estatus'] === 'ACTIVO' && ! $existe?->plan_pago_id))],
                    'estatus' => [Rule::in(EstatusAlumno::valores())],
                    'costo_ingles' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
                    'email' => ['nullable', 'email', 'max:150'],
                ],
                [
                    'matricula.regex' => 'Matrícula con caracteres inválidos.',
                    'sede_id.required' => 'Sede no existe o no está activa (use la clave, ej. CUOP-LOR; las sedes se activan en Catálogos).',
                    'carrera_id.required' => 'Carrera no existe o no está activa en el catálogo.',
                    'plan_pago_id.required' => $plan === '' ? 'Falta el plan de pago: es obligatorio para alumnos ACTIVOS.' : 'Plan de pago no existe en el catálogo.',
                    'estatus.in' => 'Estatus inválido (ACTIVO, BAJA_TEMPORAL, BAJA_DEFINITIVA, EGRESADO).',
                    'costo_ingles.numeric' => 'INGLES debe ser un monto (ej. 350).',
                    'email.email' => 'EMAIL no es un correo válido.',
                ],
            );

            $mensajes = $v->errors()->all();

            if ($avisoPlan) {
                $mensajes = array_values(array_diff($mensajes, ['Plan de pago no existe en el catálogo.']));
                $mensajes[] = $avisoPlan;
            }

            // El plan final (nuevo o el que ya tenía) debe corresponder a la sede y carrera finales.
            $planFinal = $planes->get($datos['plan_pago_id'] ?? $existe?->plan_pago_id);
            if (! $mensajes && $planFinal && $sedeFinal && ! $planFinal->esCompatibleCon($sedeFinal, $carreraFinal)) {
                $mensajes[] = "El plan «{$planFinal->nombre}» (".($planFinal->sede?->clave ?? 'otra carrera').') no corresponde a la sede/carrera del alumno.';
            }

            if ($mat !== '' && isset($vistas[$mat])) {
                $mensajes[] = "Matrícula duplicada en el archivo (fila {$vistas[$mat]}).";
            }
            $vistas[$mat] = $numFila;

            if ($mensajes) {
                $resultado['errores'][] = ['fila' => $numFila, 'matricula' => $mat ?: '—', 'mensajes' => $mensajes];

                continue;
            }

            if (! $existe) {
                $resultado['crear'][] = $datos;

                continue;
            }

            // Solo los campos que vienen en el archivo y cambian.
            $cambios = collect($datos)
                ->only(self::CAMPOS_ACTUALIZABLES)
                ->filter(fn ($v) => filled($v))
                ->filter(fn ($v, $k) => (string) ($existe->{$k} instanceof \BackedEnum ? $existe->{$k}->value : $existe->{$k}) !== (string) $v)
                ->all();

            if ($cambios) {
                $resultado['actualizar'][] = ['matricula' => $mat, 'nombre_completo' => $existe->nombre_completo, 'cambios' => $cambios,
                    'antes' => collect($cambios)->map(fn ($v, $k) => $existe->{$k} instanceof \BackedEnum ? $existe->{$k}->value : $existe->{$k})->all()];
            } else {
                $resultado['sin_cambios']++;
            }
        }

        return $resultado;
    }

    /**
     * PLAN_PAGO puede venir como ID o como nombre. Por nombre solo se buscan los
     * planes de la sede (y carrera) del alumno, porque cada sede tiene los suyos
     * y pueden repetir nombre. Devuelve [plan_id|null, mensaje de error|null].
     */
    private function resolverPlan(string $plan, Collection $planes, ?int $sedeId, ?int $carreraId): array
    {
        if (ctype_digit($plan)) {
            return $planes->has((int) $plan) ? [(int) $plan, null] : [null, "Plan #{$plan} no existe."];
        }

        if (! $sedeId) {
            return [null, null]; // el error de sede ya se reporta
        }

        $candidatos = $planes->filter(fn ($p) => mb_strtoupper($p->nombre) === mb_strtoupper($plan)
            && $p->esCompatibleCon($sedeId, $carreraId));

        // Preferir el plan específico de la carrera sobre el general de la sede.
        $especificos = $candidatos->whereNotNull('carrera_id');
        $elegibles = $especificos->isNotEmpty() ? $especificos : $candidatos;

        return match ($elegibles->count()) {
            1 => [$elegibles->first()->id, null],
            0 => [null, "La sede del alumno no tiene un plan llamado «{$plan}»."],
            default => [null, "Hay varios planes «{$plan}» para esa sede; use el ID del plan."],
        };
    }

    /** @return array{creados:int, actualizados:int} */
    public function importar(array $analisis): array
    {
        return DB::transaction(function () use ($analisis) {
            $ahora = now();

            foreach (array_chunk($analisis['crear'], 500) as $lote) {
                Alumno::insert(array_map(fn ($d) => $d + ['created_at' => $ahora, 'updated_at' => $ahora], $lote));
            }

            foreach ($analisis['actualizar'] as $u) {
                Alumno::whereKey($u['matricula'])->update($u['cambios'] + ['updated_at' => $ahora]);
            }

            // Las operaciones masivas no disparan eventos de Eloquent: se registran aquí.
            Bitacora::registrarLote('creado', 'alumno', array_map(fn ($d) => [
                'entidad_id' => $d['matricula'], 'descripcion' => 'Importación de padrón · '.$d['nombre_completo'],
                'cambios' => collect($d)->filter(fn ($v) => $v !== null)->map(fn ($v) => ['antes' => null, 'despues' => $v])->all(),
            ], $analisis['crear']));
            Bitacora::registrarLote('actualizado', 'alumno', array_map(fn ($u) => [
                'entidad_id' => $u['matricula'], 'descripcion' => 'Importación de padrón · '.$u['nombre_completo'],
                'cambios' => collect($u['cambios'])->map(fn ($v, $campo) => ['antes' => $u['antes'][$campo] ?? null, 'despues' => $v])->all(),
            ], $analisis['actualizar']));

            return ['creados' => count($analisis['crear']), 'actualizados' => count($analisis['actualizar'])];
        });
    }
}
