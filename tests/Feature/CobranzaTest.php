<?php

namespace Tests\Feature;

use App\Actions\Cobranza\CancelarPagoAction;
use App\Actions\Cobranza\RegistrarPagoAction;
use App\Enums\FormaPago;
use App\Exceptions\CobranzaException;
use App\Livewire\Caja\EstadoCuenta;
use App\Models\Adeudo;
use App\Models\Alumno;
use App\Models\Pago;
use App\Models\User;
use App\Services\Cobranza\MensualidadService;
use App\Services\Padron\ImportarAlumnosService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class CobranzaTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function cajero(): User
    {
        return User::where('email', 'caja@cuop.test')->first();
    }

    private function admin(): User
    {
        return User::where('email', 'admin@cuop.test')->first();
    }

    public function test_abono_parcial_y_liquidacion_con_folios_consecutivos(): void
    {
        $adeudo = Adeudo::where('matricula', 'CUOP26002')->where('concepto', 'MENSUALIDAD DIC 2026')->first();
        $accion = app(RegistrarPagoAction::class);

        $p1 = $accion->execute($adeudo->id, '1000.50', FormaPago::EFECTIVO, $this->cajero());
        $this->assertSame('02113', $p1->folio_pago);
        $this->assertFalse($adeudo->fresh()->pagado);
        $this->assertSame(849.50, $adeudo->fresh()->saldo_pendiente);

        $p2 = $accion->execute($adeudo->id, 849.50, FormaPago::TARJETA, $this->cajero(), 'aut 99');
        $this->assertSame('02114', $p2->folio_pago);
        $this->assertSame('AUT 99', $p2->referencia);
        $this->assertTrue($adeudo->fresh()->pagado);
        $this->assertSame('1850.00', $adeudo->fresh()->monto_original); // nunca se sobrescribe
    }

    public function test_no_permite_cobrar_de_mas_ni_adeudos_liquidados(): void
    {
        $adeudo = Adeudo::where('matricula', 'CUOP26001')->where('concepto', 'MENSUALIDAD NOV 2026')->first(); // saldo 850
        $accion = app(RegistrarPagoAction::class);

        try {
            $accion->execute($adeudo->id, 850.01, FormaPago::EFECTIVO, $this->cajero());
            $this->fail('Debió rechazar el sobrepago');
        } catch (CobranzaException $e) {
            $this->assertStringContainsString('excede', $e->getMessage());
        }

        $pagado = Adeudo::where('pagado', true)->first();
        $this->expectException(CobranzaException::class);
        $accion->execute($pagado->id, 1, FormaPago::EFECTIVO, $this->cajero());
    }

    public function test_transferencia_requiere_referencia_y_no_consume_folio_al_fallar(): void
    {
        $adeudo = Adeudo::where('pagado', false)->first();

        try {
            app(RegistrarPagoAction::class)->execute($adeudo->id, 10, FormaPago::TRANSFERENCIA, $this->cajero());
        } catch (CobranzaException) {
        }

        $p = app(RegistrarPagoAction::class)->execute($adeudo->id, 10, FormaPago::EFECTIVO, $this->cajero());
        $this->assertSame('02113', $p->folio_pago);
    }

    public function test_cancelar_pago_reabre_adeudo_solo_admin(): void
    {
        $pago = Pago::whereHas('adeudo', fn ($q) => $q->where('pagado', true))->first();

        try {
            app(CancelarPagoAction::class)->execute($pago->id, $this->cajero(), 'ERROR DE CAPTURA');
            $this->fail('Un cajero no puede cancelar');
        } catch (CobranzaException) {
        }

        app(CancelarPagoAction::class)->execute($pago->id, $this->admin(), 'ERROR DE CAPTURA');

        $this->assertSoftDeleted($pago);
        $this->assertFalse($pago->adeudo->fresh()->pagado);
        $this->assertGreaterThan(0, $pago->adeudo->fresh()->saldo_pendiente);
    }

    public function test_recargos_solo_una_vez_y_mensualidad_solo_activos(): void
    {
        $this->artisan('cobranza:aplicar-recargos')->assertSuccessful();
        $adeudo = Adeudo::where('matricula', 'CUOP26001')->where('concepto', 'MENSUALIDAD NOV 2026')->first();
        $this->assertSame('185.00', $adeudo->recargo_aplicado);

        $this->artisan('cobranza:aplicar-recargos')->assertSuccessful();
        $this->assertSame('185.00', $adeudo->fresh()->recargo_aplicado);

        $r = app(MensualidadService::class)->generar(Carbon::parse('2027-02-01'));
        $this->assertSame(4, $r['generados']); // 4 activos, la baja temporal se omite
        $this->assertFalse(Adeudo::where('matricula', 'CUOP26005')->where('concepto', 'MENSUALIDAD FEB 2027')->exists());
        $this->assertSame('2027-02-11', Adeudo::where('concepto', 'MENSUALIDAD FEB 2027')->first()->fecha_vencimiento->toDateString());
    }

    public function test_livewire_estado_cuenta_busca_cobra_y_abre_recibo(): void
    {
        $this->actingAs($this->cajero());
        $adeudo = Adeudo::where('matricula', 'CUOP26004')->where('concepto', 'INSCRIPCIÓN 2027-1')->first();
        $sepPagado = Adeudo::where('matricula', 'CUOP26004')->where('concepto', 'MENSUALIDAD SEP 2026')->value('id');

        Livewire::test(EstadoCuenta::class)
            ->set('search', 'c')
            ->assertDontSee('SÁNCHEZ CRUZ LUIS ÁNGEL') // mínimo 2 caracteres
            ->set('search', 'SÁNCHEZ')
            ->assertSee('SÁNCHEZ CRUZ LUIS ÁNGEL')
            ->set('search', 'CUOP2600')
            ->assertSee('RODRÍGUEZ PÉREZ MARÍA GUADALUPE')
            ->call('seleccionarAlumno', 'CUOP26004')
            ->assertSee('27-1 LIC-DER-ESC-MAT-3B')
            ->assertSee('$2,950.00')
            ->assertDontSeeHtml('wire:key="adeudo-'.$sepPagado.'"') // pagado: oculto por defecto
            ->set('mostrarPagados', true)
            ->assertSeeHtml('wire:key="adeudo-'.$sepPagado.'"')
            ->call('abrirModalCobro', $adeudo->id)
            ->assertSet('monto_pagar', '600.00')
            ->set('monto_pagar', '700')
            ->call('cobrar')
            ->assertHasErrors('monto_pagar')
            ->set('monto_pagar', '600')
            ->set('forma_pago', 'DEPOSITO')
            ->call('cobrar')
            ->assertHasErrors('referencia')
            ->set('referencia', 'ficha 7781')
            ->call('cobrar')
            ->assertHasNoErrors()
            ->assertSet('modalCobro', false)
            ->assertSet('ultimoFolio', '02113')
            ->assertDispatched('abrir-recibo');

        $this->assertTrue($adeudo->fresh()->pagado);

        $this->get(route('caja.recibo', Pago::where('folio_pago', '02113')->first()))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_livewire_no_permite_cobrar_adeudo_de_otro_alumno(): void
    {
        $this->actingAs($this->cajero());
        $ajeno = Adeudo::where('matricula', 'CUOP26003')->where('pagado', false)->first();

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class); // 404 en HTTP

        Livewire::test(EstadoCuenta::class)
            ->call('seleccionarAlumno', 'CUOP26001')
            ->call('abrirModalCobro', $ajeno->id);
    }

    public function test_importacion_previsualiza_crea_actualiza_y_errores(): void
    {
        $filas = collect([
            ['matricula' => 'CUOP27001', 'nombre_completo' => 'nuevo alumno uno', 'sede' => 'cuop-lor', 'carrera' => 'EDUCACIÓN NO ESCOLARIZADO', 'grupo' => '27-1 A', 'plan_pago' => '1', 'estatus' => 'ACTIVO'],
            ['matricula' => 'CUOP26001', 'nombre_completo' => '', 'sede' => '', 'carrera' => '', 'grupo' => '27-2 LIC-EDUN-NESC-SAB-7A', 'plan_pago' => '', 'estatus' => 'EGRESADO'],
            ['matricula' => 'CUOP26003', 'nombre_completo' => '', 'sede' => '', 'carrera' => '', 'grupo' => '27-1 LIC-DER-ESC-MAT-3B', 'plan_pago' => '', 'estatus' => 'ACTIVO'],
            ['matricula' => 'CUOP27002', 'nombre_completo' => 'X', 'sede' => 'NO-EXISTE', 'carrera' => 'EDUCACIÓN NO ESCOLARIZADO', 'grupo' => 'G', 'plan_pago' => '1', 'estatus' => 'ACTIVO'],
            ['matricula' => 'CUOP27001', 'nombre_completo' => 'DUP', 'sede' => 'CUOP-LOR', 'carrera' => 'EDUCACIÓN NO ESCOLARIZADO', 'grupo' => 'G', 'plan_pago' => '1', 'estatus' => 'ACTIVO'],
        ])->map(fn ($r) => collect($r));

        $service = app(ImportarAlumnosService::class);
        $a = $service->analizarFilas($filas);

        $this->assertCount(1, $a['crear']);
        $this->assertCount(1, $a['actualizar']);
        $this->assertSame(1, $a['sin_cambios']);
        $this->assertCount(2, $a['errores']);

        $service->importar($a);
        $this->assertSame('NUEVO ALUMNO UNO', Alumno::find('CUOP27001')->nombre_completo);
        $this->assertSame('EGRESADO', Alumno::find('CUOP26001')->estatus->value);
        $this->assertSame('27-2 LIC-EDUN-NESC-SAB-7A', Alumno::find('CUOP26001')->grupo);
    }

    public function test_paginas_y_permisos_por_rol(): void
    {
        $this->get('/caja/estado-cuenta')->assertRedirect('/login');

        $this->actingAs($this->admin());
        foreach (['/caja/estado-cuenta/CUOP26002', '/alumnos', '/reportes/corte-caja', '/reportes/cartera-vencida'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/reportes/corte-caja/exportar/pdf?desde=2026-09-01&hasta=2026-11-30')->assertOk();
        $this->get('/reportes/corte-caja/exportar/xlsx?desde=2026-09-01&hasta=2026-11-30')->assertOk();

        $this->actingAs(User::where('email', 'escolar@cuop.test')->first());
        $this->get('/alumnos')->assertOk();
        $this->get('/caja/estado-cuenta')->assertForbidden();

        $this->actingAs($this->cajero());
        $this->get('/alumnos')->assertForbidden();
        $this->get('/reportes/cartera-vencida')->assertForbidden();
    }
}
