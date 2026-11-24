<?php

namespace Tests\Feature;

use App\Actions\Cobranza\RegistrarPagoAction;
use App\Enums\FormaPago;
use App\Enums\TipoAdeudo;
use App\Livewire\Reportes\BloqueoPlataformas;
use App\Models\Adeudo;
use App\Models\Alumno;
use App\Models\Carrera;
use App\Models\PlanPago;
use App\Models\Sede;
use App\Models\User;
use App\Services\Cobranza\MensualidadService;
use App\Services\Cobranza\RecargoService;
use App\Services\Padron\ImportarAlumnosService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Reglas de la institución: del 1 al 10 colegiatura según plan; desde el 11
 * recargo ($180 colegiatura, 10% inglés); desde el 16 bloqueo de plataformas.
 */
class ReglasCobranzaTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private Alumno $alumno;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2027-03-01 08:00');

        $sede = Sede::where('clave', 'CUOP-CAL')->first();
        $plan = PlanPago::where('sede_id', $sede->id)->where('nombre', 'PLAN 1370')->first();
        $this->alumno = Alumno::create([
            'matricula' => 'CUOP-CAL09999', 'nombre_completo' => 'ALUMNO REGLAS', 'sede_id' => $sede->id,
            'carrera_id' => Carrera::first()->id, 'grupo' => 'G', 'plan_pago_id' => $plan->id, 'costo_ingles' => 350,
        ]);
        app(MensualidadService::class)->generar(Carbon::parse('2027-03-01'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function cargo(TipoAdeudo $tipo): Adeudo
    {
        return $this->alumno->adeudos()->where('tipo', $tipo)->firstOrFail();
    }

    public function test_genera_colegiatura_e_ingles_con_vencimiento_el_dia_10(): void
    {
        $col = $this->cargo(TipoAdeudo::COLEGIATURA);
        $ing = $this->cargo(TipoAdeudo::INGLES);

        $this->assertSame('MENSUALIDAD MAR 2027', $col->concepto);
        $this->assertSame('1370.00', $col->monto_original);
        $this->assertSame('INGLÉS MAR 2027', $ing->concepto);
        $this->assertSame('350.00', $ing->monto_original);
        $this->assertSame('2027-03-10', $col->fecha_vencimiento->toDateString());

        // Idempotente
        $r = app(MensualidadService::class)->generar(Carbon::parse('2027-03-01'));
        $this->assertSame(0, $this->alumno->adeudos()->where('concepto', 'like', '%MAR 2027')->count() - 2);
        $this->assertSame(0, $r['ingles']);
    }

    public function test_recargo_desde_el_dia_11_fijo_en_colegiatura_y_10_por_ciento_en_ingles(): void
    {
        $otro = $this->alumno->adeudos()->create(['concepto' => 'CONSTANCIA', 'tipo' => 'OTRO',
            'fecha_vencimiento' => '2027-03-01', 'monto_original' => 150]);

        // Día 10: todavía sin recargo
        $r = app(RecargoService::class)->aplicar(Carbon::parse('2027-03-10'));
        $this->assertSame('0.00', $this->cargo(TipoAdeudo::COLEGIATURA)->recargo_aplicado);

        // Día 11: recargo
        app(RecargoService::class)->aplicar(Carbon::parse('2027-03-11'));
        $this->assertSame('180.00', $this->cargo(TipoAdeudo::COLEGIATURA)->recargo_aplicado);
        $this->assertSame('35.00', $this->cargo(TipoAdeudo::INGLES)->recargo_aplicado);
        $this->assertSame('0.00', $otro->fresh()->recargo_aplicado, 'Los cargos «Otro» no llevan recargo');
        $this->assertSame(1550.00, $this->cargo(TipoAdeudo::COLEGIATURA)->load('pagos')->saldo_pendiente);

        // Una sola vez
        app(RecargoService::class)->aplicar(Carbon::parse('2027-03-20'));
        $this->assertSame('180.00', $this->cargo(TipoAdeudo::COLEGIATURA)->recargo_aplicado);
    }

    public function test_bloqueo_desde_el_dia_16_y_se_libera_al_pagar(): void
    {
        $this->alumno->adeudos()->create(['concepto' => 'CONSTANCIA', 'tipo' => 'OTRO',
            'fecha_vencimiento' => '2027-01-01', 'monto_original' => 150]); // vencida hace meses, pero no bloquea

        $bloqueado = fn ($fecha) => Alumno::conBloqueo(Carbon::parse($fecha))->whereKey($this->alumno->matricula)->exists();

        $this->assertFalse($bloqueado('2027-03-15'));
        $this->assertTrue($bloqueado('2027-03-16'));
        $this->assertSame('2027-03-16', $this->cargo(TipoAdeudo::COLEGIATURA)->bloqueo_desde->toDateString());

        // Debe solo inglés: también bloquea
        $cajero = User::where('email', 'caja@cuop.test')->first();
        app(RegistrarPagoAction::class)->execute($this->cargo(TipoAdeudo::COLEGIATURA)->id, 1370, FormaPago::EFECTIVO, $cajero);
        $this->assertTrue($bloqueado('2027-03-16'));

        // Abonar no libera; liquidar sí
        app(RegistrarPagoAction::class)->execute($this->cargo(TipoAdeudo::INGLES)->id, 100, FormaPago::EFECTIVO, $cajero);
        $this->assertTrue($bloqueado('2027-03-16'));
        app(RegistrarPagoAction::class)->execute($this->cargo(TipoAdeudo::INGLES)->id, 250, FormaPago::EFECTIVO, $cajero);
        $this->assertFalse($bloqueado('2027-03-16'));
    }

    public function test_solo_se_bloquea_a_alumnos_activos(): void
    {
        $this->alumno->update(['estatus' => 'BAJA_TEMPORAL']);
        $this->assertFalse(Alumno::conBloqueo(Carbon::parse('2027-03-20'))->whereKey($this->alumno->matricula)->exists());
    }

    public function test_reporte_de_bloqueo_y_aviso_en_estado_de_cuenta(): void
    {
        Carbon::setTestNow('2027-03-16 09:00');
        $this->actingAs(User::where('email', 'escolar@cuop.test')->first());

        Livewire::test(BloqueoPlataformas::class)
            ->assertSee('CUOP-CAL09999')->assertSee('16/03/2027')
            ->call('exportar')->assertFileDownloaded('bloqueo-plataformas-20270316.xlsx');

        $this->actingAs(User::where('email', 'caja@cuop.test')->first());
        $this->get('/reportes/bloqueo-plataformas')->assertOk();
        $this->get('/caja/estado-cuenta/CUOP-CAL09999')->assertOk()
            ->assertSee('BLOQUEO DE PLATAFORMAS')->assertSee('Cursa inglés');

        Carbon::setTestNow('2027-03-15 09:00');
        $this->get('/caja/estado-cuenta/CUOP-CAL09999')->assertOk()->assertDontSee('BLOQUEO DE PLATAFORMAS');
    }

    public function test_importacion_columna_ingles(): void
    {
        $fila = fn ($mat, $ingles) => collect(['matricula' => $mat, 'nombre_completo' => '', 'sede' => '', 'carrera' => '',
            'grupo' => '', 'plan_pago' => '', 'estatus' => 'ACTIVO', 'ingles' => $ingles]);

        $s = app(ImportarAlumnosService::class);
        $a = $s->analizarFilas(collect([$fila('CUOP-CAL09999', '350'), $fila('CUOP26002', '420'), $fila('CUOP26003', 'mucho')]));

        $this->assertSame(1, $a['sin_cambios']); // 350 = 350.00, sin cambio
        $this->assertSame(['costo_ingles' => '420.00'], $a['actualizar'][0]['cambios']);
        $this->assertStringContainsString('INGLES debe ser un monto', $a['errores'][0]['mensajes'][0]);

        $s->importar($a);
        $this->assertSame('420.00', Alumno::find('CUOP26002')->costo_ingles);
    }
}
