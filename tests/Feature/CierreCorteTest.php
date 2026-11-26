<?php

namespace Tests\Feature;

use App\Actions\Cobranza\CancelarPagoAction;
use App\Actions\Cobranza\CorteCajaAction;
use App\Actions\Cobranza\RegistrarPagoAction;
use App\Enums\FormaPago;
use App\Exceptions\CobranzaException;
use App\Livewire\Reportes\CorteCaja;
use App\Models\Adeudo;
use App\Models\Bitacora;
use App\Models\CorteCaja as Corte;
use App\Models\Pago;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class CierreCorteTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private User $cajera;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-23 18:00');  // el seeder registra un pago de la cajera ese día (inscripción $600)
        $this->cajera = User::where('email', 'caja@cuop.test')->first();
        $this->admin = User::where('email', 'admin@cuop.test')->first();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_cerrar_guarda_totales_y_bloquea_cobrar_y_cancelar_ese_dia(): void
    {
        $corte = app(CorteCajaAction::class)->cerrar($this->cajera, today(), $this->cajera);
        $this->assertSame(1, $corte->num_pagos);
        $this->assertSame('600.00', $corte->total);
        $this->assertSame(600, (int) $corte->por_forma['EFECTIVO']['monto']);
        $this->assertSame('cierre', Bitacora::latest('id')->first()->accion);

        $adeudo = Adeudo::where('pagado', false)->first();
        try {
            app(RegistrarPagoAction::class)->execute($adeudo->id, 10, FormaPago::EFECTIVO, $this->cajera);
            $this->fail('No debe cobrar con el corte cerrado');
        } catch (CobranzaException $e) {
            $this->assertStringContainsString('corte de caja de ese día ya está cerrado', $e->getMessage());
        }

        // Otro cajero (con su corte abierto) sí puede cobrar
        app(RegistrarPagoAction::class)->execute($adeudo->id, 10, FormaPago::EFECTIVO, $this->admin);

        $pagoDelDia = Pago::where('user_id', $this->cajera->id)->whereDate('fecha_pago', today())->first();
        try {
            app(CancelarPagoAction::class)->execute($pagoDelDia->id, $this->admin, 'ERROR DE CAPTURA');
            $this->fail('No debe cancelar un pago de un corte cerrado');
        } catch (CobranzaException $e) {
            $this->assertStringContainsString('corte de caja cerrado', $e->getMessage());
        }

        // Pagos de otros días no se ven afectados
        $otro = Pago::where('user_id', $this->cajera->id)->whereDate('fecha_pago', '<', today())->first();
        app(CancelarPagoAction::class)->execute($otro->id, $this->admin, 'ERROR DE CAPTURA');
        $this->assertSoftDeleted($otro);
    }

    public function test_reabrir_solo_administrador_con_motivo_y_se_puede_volver_a_cerrar(): void
    {
        $accion = app(CorteCajaAction::class);
        $corte = $accion->cerrar($this->cajera, today(), $this->cajera);

        try {
            $accion->reabrir($corte->id, $this->cajera, 'QUIERO CANCELAR');
            $this->fail('El cajero no puede reabrir');
        } catch (CobranzaException) {
        }

        $accion->reabrir($corte->id, $this->admin, 'faltó registrar un depósito');
        $this->assertFalse(Corte::cerrado($this->cajera->id, today()));
        app(RegistrarPagoAction::class)->execute(Adeudo::where('pagado', false)->first()->id, 50, FormaPago::EFECTIVO, $this->cajera);

        $otra = $accion->cerrar($this->cajera, today(), $this->cajera);
        $this->assertSame($corte->id, $otra->id, 'Se reutiliza el mismo registro del día');
        $this->assertSame('650.00', $otra->total);
        $this->assertNull($otra->reabierto_at);
    }

    public function test_un_cajero_no_cierra_el_corte_de_otro(): void
    {
        $this->expectException(CobranzaException::class);
        app(CorteCajaAction::class)->cerrar($this->admin, today(), $this->cajera);
    }

    public function test_pantalla_de_corte(): void
    {
        $this->actingAs($this->cajera);
        Livewire::test(CorteCaja::class)->assertSee('Corte abierto')->call('cerrarCorte')
            ->assertDispatched('toast', tipo: 'success')->assertSee('Corte cerrado el')->assertSee('CERRADO');

        $this->actingAs($this->admin);
        Livewire::test(CorteCaja::class)->set('cajeroId', (string) $this->cajera->id)->assertSee('Reabrir corte')
            ->set('motivoReapertura', 'x')->call('reabrirCorte')->assertHasErrors('motivoReapertura')
            ->set('motivoReapertura', 'depósito pendiente')->call('reabrirCorte')->assertHasNoErrors()->assertSee('Reabierto el');

        $this->get('/reportes/corte-caja/exportar/pdf?desde=2026-11-23&hasta=2026-11-23&cajero='.$this->cajera->id)->assertOk();
    }
}
