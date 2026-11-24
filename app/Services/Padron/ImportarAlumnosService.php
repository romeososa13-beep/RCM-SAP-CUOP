<?php

namespace App\Services\Padron;

use App\Enums\EstatusAlumno;
use App\Imports\AlumnosImport;
use App\Models\Alumno;
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
    public const CAMPOS_ACTUALIZABLES = ['grupo', 'carrera_id', 'plan_pago_id', 'estatus'];

    /**
     * Lee y valida el archivo SIN escribir en la base de datos.
     *
     * @return array{crear: list<array>, actualizar: list<array>, sin_cambios: int, errores: list<array>, total: int}
     */
    public function analizar(string $rutaArchivo): array
    {
        $import = new AlumnosImport();
        Excel::import($import, $rutaArchivo);

        return $this->analizarFilas($import->filas);
    }

    public function analizarFilas(Collection $filas): array
    {
        $sedes = Sede::pluck('id', 'clave')->mapWithKeys(fn ($id, $k) => [mb_strtoupper($k) => $id]);
        $carreras = Carrera::pluck('id', 'nombre')->mapWithKeys(fn ($id, $k) => [mb_strtoupper($k) => $id]);
        $planes = PlanPago::get(['id', 'nombre']);
        $planPorNombre = $planes->mapWithKeys(fn ($p) => [mb_strtoupper($p->nombre) => $p->id]);
        $planIds = $planes->pluck('id')->all();

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
                'plan_pago_id' => ctype_digit($plan) && in_array((int) $plan, $planIds, true)
                    ? (int) $plan
                    : ($planPorNombre[mb_strtoupper($plan)] ?? null),
                'estatus' => mb_strtoupper(str_replace(' ', '_', (string) ($f['estatus'] ?? ''))) ?: 'ACTIVO',
            ];

            $req = $existe ? 'nullable' : 'required';
            $v = Validator::make(
                $datos,
                [
                    'matricula' => ['required', 'max:20', 'regex:/^[A-Z0-9\-]+$/'],
                    'nombre_completo' => [$req, 'max:200'],
                    'sede_id' => [$req],
                    'carrera_id' => [Rule::requiredIf(! $existe || filled($f['carrera'] ?? null))],
                    'grupo' => [$req, 'max:80'],
                    'plan_pago_id' => [Rule::requiredIf(! $existe || filled($plan))],
                    'estatus' => [Rule::in(EstatusAlumno::valores())],
                ],
                [
                    'matricula.regex' => 'Matrícula con caracteres inválidos.',
                    'sede_id.required' => 'Sede no existe en el catálogo (use la clave, ej. CUOP-LOR).',
                    'carrera_id.required' => 'Carrera no existe en el catálogo.',
                    'plan_pago_id.required' => 'Plan de pago no existe en el catálogo.',
                    'estatus.in' => 'Estatus inválido (ACTIVO, BAJA_TEMPORAL, BAJA_DEFINITIVA, EGRESADO).',
                ],
            );

            $mensajes = $v->errors()->all();

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
                $resultado['actualizar'][] = ['matricula' => $mat, 'nombre_completo' => $existe->nombre_completo, 'cambios' => $cambios];
            } else {
                $resultado['sin_cambios']++;
            }
        }

        return $resultado;
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

            return ['creados' => count($analisis['crear']), 'actualizados' => count($analisis['actualizar'])];
        });
    }
}
