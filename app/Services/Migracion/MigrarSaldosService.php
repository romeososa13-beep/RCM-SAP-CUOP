<?php

namespace App\Services\Migracion;

use App\Actions\Cobranza\RegistrarPagoAction;
use App\Enums\TipoAdeudo;
use App\Imports\SaldosImport;
use App\Models\Adeudo;
use App\Models\Alumno;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Carga, una sola vez, los saldos pendientes del sistema anterior como adeudos
 * (origen = MIGRACION). No crea pagos ni consume folios: lo abonado antes queda
 * en adeudos.pagado_previo. Un cargo ya liquidado en el sistema anterior
 * (PAGADO_PREVIO = total) se registra como pagado: así el generador mensual sabe
 * que ese concepto ya existe y no lo vuelve a cobrar. Es idempotente: si el alumno ya tiene un adeudo con
 * el mismo concepto, la fila se omite.
 */
class MigrarSaldosService
{
    public function analizarArchivo(string $ruta): array
    {
        $import = new SaldosImport();
        Excel::import($import, $ruta);

        return $this->analizar($import->filas);
    }

    /** @return array{validas: list<array>, omitidas: list<array>, errores: list<array>, total:int, saldo:float} */
    public function analizar(Collection $filas): array
    {
        $matriculas = $filas->map(fn ($f) => mb_strtoupper(trim((string) ($f['matricula'] ?? ''))))->filter()->unique();
        $alumnos = $matriculas->chunk(1000)->flatMap(fn ($c) => Alumno::whereIn('matricula', $c->all())->pluck('matricula'))->flip();
        $existentes = $matriculas->chunk(1000)
            ->flatMap(fn ($c) => Adeudo::whereIn('matricula', $c->all())->get(['matricula', 'concepto'])
                ->map(fn ($a) => $a->matricula.'|'.$a->concepto))
            ->flip();

        $r = ['validas' => [], 'omitidas' => [], 'errores' => [], 'total' => $filas->count(), 'saldo' => 0.0, 'liquidadas' => 0];
        $vistas = [];

        foreach ($filas as $i => $f) {
            $num = $i + 2;
            $d = [
                'matricula' => mb_strtoupper(trim((string) ($f['matricula'] ?? ''))),
                'concepto' => mb_strtoupper(trim(preg_replace('/\s+/', ' ', (string) ($f['concepto'] ?? '')))),
                'tipo' => str_replace(['É', ' '], ['E', ''], mb_strtoupper(trim((string) ($f['tipo'] ?? 'OTRO')))) ?: 'OTRO',
                'fecha_vencimiento' => $this->fecha($f['vencimiento'] ?? null),
                'monto_original' => $this->monto($f['monto'] ?? null),
                'recargo_aplicado' => $this->monto($f['recargo'] ?? 0) ?? 0.0,
                'pagado_previo' => $this->monto($f['pagado_previo'] ?? 0) ?? 0.0,
                'exento_recargo' => in_array(mb_strtoupper(trim((string) ($f['exento_recargo'] ?? ''))), ['SI', 'SÍ', '1', 'TRUE', 'X'], true),
            ];

            $v = Validator::make($d, [
                'matricula' => 'required',
                'concepto' => 'required|max:150',
                'tipo' => ['required', Rule::in(TipoAdeudo::valores())],
                'fecha_vencimiento' => 'required|date',
                'monto_original' => 'required|numeric|min:0.01',
                'recargo_aplicado' => 'numeric|min:0',
                'pagado_previo' => 'numeric|min:0',
            ], [
                'tipo.in' => 'TIPO debe ser COLEGIATURA, INGLES u OTRO.',
                'fecha_vencimiento.required' => 'VENCIMIENTO vacío o con formato inválido (use AAAA-MM-DD).',
                'monto_original.required' => 'MONTO vacío o inválido.',
            ]);
            $mensajes = $v->errors()->all();

            if (! $mensajes && ! isset($alumnos[$d['matricula']])) {
                $mensajes[] = 'La matrícula no existe en el padrón (importe primero al alumno).';
            }
            $saldoCent = RegistrarPagoAction::aCentavos($d['monto_original']) + RegistrarPagoAction::aCentavos($d['recargo_aplicado'])
                - RegistrarPagoAction::aCentavos($d['pagado_previo']);
            if (! $mensajes && $saldoCent < 0) {
                $mensajes[] = 'PAGADO_PREVIO es mayor que el cargo.';
            }
            $d['pagado'] = $saldoCent === 0;
            $clave = $d['matricula'].'|'.$d['concepto'];
            if (! $mensajes && isset($vistas[$clave])) {
                $mensajes[] = "Concepto repetido para el alumno (fila {$vistas[$clave]}).";
            }
            $vistas[$clave] = $num;

            if ($mensajes) {
                $r['errores'][] = ['fila' => $num, 'matricula' => $d['matricula'] ?: '—', 'concepto' => $d['concepto'], 'mensajes' => $mensajes];
            } elseif (isset($existentes[$clave])) {
                $r['omitidas'][] = ['fila' => $num, 'matricula' => $d['matricula'], 'concepto' => $d['concepto']];
            } else {
                $r['validas'][] = $d;
                $r['saldo'] += $saldoCent / 100;
                $r['liquidadas'] += $d['pagado'] ? 1 : 0;
            }
        }

        $r['saldo'] = round($r['saldo'], 2);

        return $r;
    }

    public function migrar(array $analisis): int
    {
        return DB::transaction(function () use ($analisis) {
            $ahora = now();
            foreach (array_chunk($analisis['validas'], 500) as $lote) {
                Adeudo::insert(array_map(fn ($d) => $d + [
                    'origen' => 'MIGRACION', 'created_at' => $ahora, 'updated_at' => $ahora,
                ], $lote));
            }

            return count($analisis['validas']);
        });
    }

    private function monto(mixed $v): ?float
    {
        if (is_numeric($v)) {
            return round((float) $v, 2);
        }
        $limpio = str_replace([',', '$', ' '], '', (string) $v);

        return is_numeric($limpio) ? round((float) $limpio, 2) : null;
    }

    private function fecha(mixed $v): ?string
    {
        if (blank($v)) {
            return null;
        }
        try {
            return is_numeric($v)
                ? Carbon::instance(ExcelDate::excelToDateTimeObject((float) $v))->toDateString()
                : Carbon::parse((string) $v)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
