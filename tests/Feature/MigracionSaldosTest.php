<?php

namespace Tests\Feature;

use App\Actions\Cobranza\RegistrarPagoAction;
use App\Enums\FormaPago;
use App\Models\Adeudo;
use App\Models\Alumno;
use App\Models\Pago;
use App\Models\User;
use App\Services\Cobranza\RecargoService;
use App\Services\Migracion\MigrarSaldosService;
use App\Services\Padron\ImportarAlumnosService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MigracionSaldosTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function fila(array $d): \Illuminate\Support\Collection
    {
        return collect($d + ['tipo' => 'COLEGIATURA', 'recargo' => 0, 'pagado_previo' => 0, 'exento_recargo' => '']);
    }

    public function test_migra_saldos_con_recargo_separado_abono_previo_y_es_idempotente(): void
    {
        $pagosAntes = Pago::count();
        $s = app(MigrarSaldosService::class);
        $filas = collect([
            // Noviembre ya con el recargo de INTELL y un abono de $450
            $this->fila(['matricula' => 'cuop26003', 'concepto' => 'MENSUALIDAD NOV 2026 (INTELL)', 'vencimiento' => '2026-11-10',
                'monto' => 2950, 'recargo' => 180, 'pagado_previo' => 450]),
            // Monto que no se puede separar: exento de recargo
            $this->fila(['matricula' => 'CUOP26003', 'concepto' => 'MENSUALIDAD OCT 2026 (INTELL)', 'vencimiento' => '2026-10-10',
                'monto' => '1,610', 'exento_recargo' => 'SI']),
            $this->fila(['matricula' => 'CUOP26003', 'concepto' => 'INGLÉS NOV 2026', 'tipo' => 'Inglés', 'vencimiento' => '2026-11-10', 'monto' => 385]),
            $this->fila(['matricula' => 'NO-EXISTE', 'concepto' => 'X', 'vencimiento' => '2026-11-10', 'monto' => 10]),
            $this->fila(['matricula' => 'CUOP26003', 'concepto' => 'PAGADO', 'vencimiento' => '2026-11-10', 'monto' => 100, 'pagado_previo' => 100]),
            $this->fila(['matricula' => 'CUOP26003', 'concepto' => 'MENSUALIDAD DIC 2026', 'vencimiento' => '2026-12-10', 'monto' => 2950]), // ya existe
        ]);

        $a = $s->analizar($filas);
        $this->assertCount(4, $a['validas']);
        $this->assertSame(1, $a['liquidadas']);   // «PAGADO»: liquidado en el sistema anterior
        $this->assertCount(1, $a['omitidas']);
        $this->assertCount(1, $a['errores']);
        $this->assertSame(2680.0 + 1610.0 + 385.0, $a['saldo']);

        $this->assertSame(4, $s->migrar($a));
        $this->assertTrue(Adeudo::where('concepto', 'PAGADO')->value('pagado'));
        $this->assertSame($pagosAntes, Pago::count(), 'La migración no crea pagos ni consume folios');

        $nov = Adeudo::where('concepto', 'MENSUALIDAD NOV 2026 (INTELL)')->with('pagos')->first();
        $this->assertSame('MIGRACION', $nov->origen);
        $this->assertSame(2680.0, $nov->saldo_pendiente);
        $this->assertSame(450.0, $nov->total_pagado);
        $this->assertSame('INGLES', Adeudo::where('matricula', 'CUOP26003')->where('concepto', 'INGLÉS NOV 2026')->first()->tipo->value);

        // Segunda corrida: todo se omite
        $b = $s->analizar($filas);
        $this->assertCount(0, $b['validas']);
        $this->assertCount(5, $b['omitidas']);

        // El comando de recargos no duplica: NOV ya trae recargo, OCT está exento; INGLÉS sí recibe el 10%
        app(RecargoService::class)->aplicar(Carbon::parse('2026-11-24'));
        $this->assertSame('180.00', $nov->fresh()->recargo_aplicado);
        $this->assertSame('0.00', Adeudo::where('concepto', 'MENSUALIDAD OCT 2026 (INTELL)')->value('recargo_aplicado'));
        $this->assertSame('38.50', Adeudo::where('matricula', 'CUOP26003')->where('concepto', 'INGLÉS NOV 2026')->value('recargo_aplicado'));

        // Cobrar el saldo migrado liquida el adeudo y el recibo muestra saldo 0
        $cajero = User::where('email', 'caja@cuop.test')->first();
        $pago = app(RegistrarPagoAction::class)->execute($nov->id, 2680, FormaPago::EFECTIVO, $cajero);
        $this->assertTrue($nov->fresh()->pagado);
        $this->actingAs($cajero)->get(route('caja.recibo', $pago))->assertOk();
    }

    public function test_mensualidad_ya_pagada_en_el_sistema_anterior_no_se_vuelve_a_generar(): void
    {
        $s = app(MigrarSaldosService::class);
        $s->migrar($s->analizar(collect([$this->fila(['matricula' => 'CUOP26003', 'concepto' => 'MENSUALIDAD ENE 2027',
            'vencimiento' => '2027-01-10', 'monto' => 2950, 'pagado_previo' => 2950])])));

        app(\App\Services\Cobranza\MensualidadService::class)->generar(Carbon::parse('2027-01-01'));

        $this->assertSame(1, Adeudo::where('matricula', 'CUOP26003')->where('concepto', 'MENSUALIDAD ENE 2027')->count());
        $this->assertSame(0.0, Adeudo::where('matricula', 'CUOP26003')->where('concepto', 'MENSUALIDAD ENE 2027')->with('pagos')->first()->saldo_pendiente);
    }

    public function test_comando_simular_no_guarda(): void
    {
        $ruta = tempnam(sys_get_temp_dir(), 'saldos').'.xlsx';
        $libro = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $libro->getActiveSheet()->fromArray([
            ['MATRICULA', 'CONCEPTO', 'TIPO', 'VENCIMIENTO', 'MONTO', 'RECARGO', 'PAGADO_PREVIO', 'EXENTO_RECARGO'],
            ['CUOP26002', 'TÍTULO 2026', 'OTRO', '2026-11-30', 8000, 0, 2000, 'NO'],
        ]);
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($libro))->save($ruta);

        $this->artisan('cobranza:migrar-saldos', ['archivo' => $ruta, '--simular' => true])->assertSuccessful();
        $this->assertFalse(Adeudo::where('concepto', 'TÍTULO 2026')->exists());

        $this->artisan('cobranza:migrar-saldos', ['archivo' => $ruta, '--no-interaction' => true])->assertSuccessful();
        $this->assertSame(6000.0, Adeudo::where('concepto', 'TÍTULO 2026')->with('pagos')->first()->saldo_pendiente);
        @unlink($ruta);
    }

    public function test_telefonos_se_normalizan_al_importar_y_se_muestran_en_caja(): void
    {
        $f = collect(['matricula' => 'CUOP26002', 'nombre_completo' => '', 'sede' => '', 'carrera' => '', 'grupo' => '', 'plan_pago' => '',
            'estatus' => 'ACTIVO', 'celular' => '496-123 4567', 'tel_tutor' => 'Sin teléfono', 'tel_alterno' => '']);
        $s = app(ImportarAlumnosService::class);
        $s->importar($s->analizarFilas(collect([$f])));

        $a = Alumno::find('CUOP26002');
        $this->assertSame('4961234567', $a->celular);
        $this->assertNull($a->tel_tutor);

        $this->actingAs(User::where('email', 'caja@cuop.test')->first())
            ->get('/caja/estado-cuenta/CUOP26002')->assertOk()->assertSee('4961234567');
    }
}
