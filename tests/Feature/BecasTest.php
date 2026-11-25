<?php

namespace Tests\Feature;

use App\Actions\Cobranza\CondonarRecargoAction;
use App\Actions\Cobranza\RegistrarPagoAction;
use App\Enums\FormaPago;
use App\Exceptions\CobranzaException;
use App\Livewire\Alumnos\PadronAlumnos;
use App\Livewire\Caja\EstadoCuenta;
use App\Models\Adeudo;
use App\Models\Alumno;
use App\Models\Bitacora;
use App\Models\User;
use App\Services\Cobranza\MensualidadService;
use App\Services\Cobranza\RecargoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class BecasTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function u(string $email): User
    {
        return User::where('email', $email)->first();
    }

    public function test_beca_se_descuenta_al_generar_colegiatura_y_opcionalmente_ingles(): void
    {
        Alumno::find('CUOP26001')->update(['beca_porcentaje' => 10, 'beca_ingles' => false, 'beca_motivo' => 'ACADÉMICA']); // plan $1,850, inglés $350
        Alumno::find('CUOP26002')->update(['beca_porcentaje' => 25, 'beca_ingles' => true, 'beca_motivo' => 'HERMANOS', 'costo_ingles' => 350]);

        app(MensualidadService::class)->generar(Carbon::parse('2027-01-01'));

        $c = fn ($m, $concepto) => Adeudo::where('matricula', $m)->where('concepto', $concepto)->first();
        $this->assertSame(['1665.00', '185.00'], [$c('CUOP26001', 'MENSUALIDAD ENE 2027')->monto_original, $c('CUOP26001', 'MENSUALIDAD ENE 2027')->descuento]);
        $this->assertSame(['350.00', '0.00'], [$c('CUOP26001', 'INGLÉS ENE 2027')->monto_original, $c('CUOP26001', 'INGLÉS ENE 2027')->descuento]);
        $this->assertSame(['1387.50', '462.50'], [$c('CUOP26002', 'MENSUALIDAD ENE 2027')->monto_original, $c('CUOP26002', 'MENSUALIDAD ENE 2027')->descuento]);
        $this->assertSame('262.50', $c('CUOP26002', 'INGLÉS ENE 2027')->monto_original);

        // El recargo de colegiatura sigue siendo el fijo del plan; el de inglés, 10% del monto ya con beca.
        app(RecargoService::class)->aplicar(Carbon::parse('2027-01-11'));
        $this->assertSame('185.00', $c('CUOP26002', 'MENSUALIDAD ENE 2027')->recargo_aplicado);
        $this->assertSame('26.25', $c('CUOP26002', 'INGLÉS ENE 2027')->recargo_aplicado);
    }

    public function test_solo_el_administrador_asigna_beca(): void
    {
        $this->actingAs($this->u('escolar@cuop.test'));
        Livewire::test(PadronAlumnos::class)->call('editar', 'CUOP26003')
            ->set('form.beca_porcentaje', '50')->set('form.beca_motivo', 'X')->call('guardar')->assertHasNoErrors();
        $this->assertSame('0.00', Alumno::find('CUOP26003')->beca_porcentaje, 'Control Escolar no puede asignar beca');

        $this->actingAs($this->u('admin@cuop.test'));
        Livewire::test(PadronAlumnos::class)->call('editar', 'CUOP26003')
            ->set('form.beca_porcentaje', '15')->call('guardar')->assertHasErrors('form.beca_motivo')
            ->set('form.beca_motivo', 'deportiva')->call('guardar')->assertHasNoErrors();

        $a = Alumno::find('CUOP26003');
        $this->assertSame(['15.00', 'DEPORTIVA'], [$a->beca_porcentaje, $a->beca_motivo]);
        $this->assertSame(['antes' => '0.00', 'despues' => '15.00'],
            Bitacora::where('entidad', 'alumno')->where('entidad_id', 'CUOP26003')->latest('id')->first()->cambios['beca_porcentaje']);

        $this->get('/caja/estado-cuenta/CUOP26003')->assertSee('Beca de')->assertSee('15%');
    }

    public function test_condonar_recargo(): void
    {
        $admin = $this->u('admin@cuop.test');
        app(RecargoService::class)->aplicar(Carbon::parse('2026-11-24'));
        $nov = Adeudo::where('matricula', 'CUOP26002')->where('concepto', 'MENSUALIDAD NOV 2026')->first();
        $this->assertSame('185.00', $nov->recargo_aplicado);

        try {
            app(CondonarRecargoAction::class)->execute($nov->id, $this->u('caja@cuop.test'), 'NO AUTORIZADO');
            $this->fail('Un cajero no puede condonar');
        } catch (CobranzaException) {
        }

        $this->actingAs($admin);
        Livewire::test(EstadoCuenta::class)->call('seleccionarAlumno', 'CUOP26002')
            ->call('abrirModalCondonar', $nov->id)->set('motivoCondonacion', 'convenio de pago')->call('condonarRecargo')
            ->assertHasNoErrors()->assertSet('modalCondonar', false);

        $nov->refresh()->load('pagos');
        $this->assertSame('0.00', $nov->recargo_aplicado);
        $this->assertTrue($nov->exento_recargo);
        $this->assertSame(1850.0, $nov->saldo_pendiente);
        $this->assertStringContainsString('CONVENIO DE PAGO', Bitacora::where('accion', 'condonado')->latest('id')->first()->descripcion);

        // No se vuelve a aplicar
        app(RecargoService::class)->aplicar(Carbon::parse('2026-12-01'));
        $this->assertSame('0.00', $nov->fresh()->recargo_aplicado);
    }

    public function test_no_se_condona_un_recargo_ya_pagado(): void
    {
        app(RecargoService::class)->aplicar(Carbon::parse('2026-11-24'));
        $nov = Adeudo::where('matricula', 'CUOP26002')->where('concepto', 'MENSUALIDAD NOV 2026')->first();
        app(RegistrarPagoAction::class)->execute($nov->id, 1900, FormaPago::EFECTIVO, $this->u('caja@cuop.test')); // cubre parte del recargo

        $this->expectException(CobranzaException::class);
        app(CondonarRecargoAction::class)->execute($nov->id, $this->u('admin@cuop.test'), 'TARDE');
    }
}
