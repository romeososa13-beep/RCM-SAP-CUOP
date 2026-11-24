<?php

namespace Tests\Feature;

use App\Actions\Cobranza\CancelarPagoAction;
use App\Actions\Cobranza\RegistrarPagoAction;
use App\Enums\FormaPago;
use App\Livewire\Reportes\Tablero;
use App\Models\Alumno;
use App\Models\Carrera;
use App\Models\PlanPago;
use App\Models\Sede;
use App\Models\User;
use App\Services\Reportes\TableroService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class TableroTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_periodos_cuatrimestrales(): void
    {
        [$d, $h] = TableroService::periodoDe(Carbon::parse('2026-11-24'));
        $this->assertSame(['2026-09-01', '2026-12-31'], [$d->toDateString(), $h->toDateString()]);
        [$d, $h] = TableroService::periodoDe(Carbon::parse('2027-04-30'));
        $this->assertSame(['2027-01-01', '2027-04-30'], [$d->toDateString(), $h->toDateString()]);
    }

    public function test_cifras_cuadran_por_tramo_e_incluyen_pagado_previo_sin_pagos_cancelados(): void
    {
        Carbon::setTestNow('2027-02-20 10:00');
        $sede = Sede::where('clave', 'CUOP-SJG')->first();
        $al = Alumno::create(['matricula' => 'CUOP-SJG09001', 'nombre_completo' => 'TABLERO', 'sede_id' => $sede->id,
            'carrera_id' => Carrera::first()->id, 'grupo' => 'G', 'plan_pago_id' => PlanPago::where('sede_id', $sede->id)->value('id')]);

        $ene = $al->adeudos()->create(['concepto' => 'MENSUALIDAD ENE 2027', 'tipo' => 'COLEGIATURA', 'fecha_vencimiento' => '2027-01-10',
            'monto_original' => 1000, 'recargo_aplicado' => 180]);                                    // vencido: 1180
        $feb = $al->adeudos()->create(['concepto' => 'MENSUALIDAD FEB 2027', 'tipo' => 'COLEGIATURA', 'fecha_vencimiento' => '2027-02-10',
            'monto_original' => 1000, 'pagado_previo' => 300, 'origen' => 'MIGRACION']);              // del mes: 1000, 300 previo
        $al->adeudos()->create(['concepto' => 'MENSUALIDAD MAR 2027', 'tipo' => 'COLEGIATURA', 'fecha_vencimiento' => '2027-03-10',
            'monto_original' => 1000]);                                                                // por vencer
        $al->adeudos()->create(['concepto' => 'INGLÉS ABR 2027', 'tipo' => 'INGLES', 'fecha_vencimiento' => '2027-04-10',
            'monto_original' => 350]);                                                                 // por vencer
        $al->adeudos()->create(['concepto' => 'FUERA DEL PERIODO', 'tipo' => 'OTRO', 'fecha_vencimiento' => '2027-05-10',
            'monto_original' => 9999]);

        $cajero = User::where('email', 'caja@cuop.test')->first();
        app(RegistrarPagoAction::class)->execute($ene->id, 500, FormaPago::EFECTIVO, $cajero);
        $cancelado = app(RegistrarPagoAction::class)->execute($feb->id, 200, FormaPago::EFECTIVO, $cajero);
        app(CancelarPagoAction::class)->execute($cancelado->id, User::where('email', 'admin@cuop.test')->first(), 'PRUEBA CANCELADO');

        [$d, $h] = TableroService::periodoDe(today());
        $t = app(TableroService::class)->generar($d, $h, $sede->id);
        $s = $t['total'];

        $this->assertSame(1180.0 + 1000 + 1000 + 350, $s['esperado']);
        $this->assertSame(500.0 + 300, $s['recuperado']);                 // el pago cancelado no cuenta
        $this->assertSame(680.0, $s['anteriores']);                       // enero
        $this->assertSame(700.0, $s['mes']);                              // febrero
        $this->assertSame(1350.0, $s['por_vencer']);                      // marzo + abril
        $this->assertEqualsWithDelta(800 / 2180, $s['avance'], 0.0001);   // de lo que ya tocaba (ene + feb)
        $this->assertSame(1, $s['deben']);
        $this->assertSame(1, $s['bloqueos']);                             // enero vencido > 6 días
        $this->assertSame($s['esperado'], $t['por_mes']->sum('esperado'));
        $this->assertSame($s['esperado'], $t['por_tipo']->sum('esperado'));
        $this->assertSame(350.0, $t['por_tipo']->firstWhere('tipo', 'INGLES')['esperado']);
    }

    public function test_solo_administrador_y_es_su_pagina_de_inicio(): void
    {
        $this->actingAs(User::where('email', 'caja@cuop.test')->first())->get('/tablero')->assertForbidden();
        $this->actingAs(User::where('email', 'escolar@cuop.test')->first())->get('/tablero')->assertForbidden();

        $this->actingAs(User::where('email', 'admin@cuop.test')->first());
        $this->get('/')->assertRedirect('/tablero');
        $this->get('/tablero')->assertOk()->assertSee('Tablero ejecutivo')->assertSee('CUOP-AGS');

        Livewire::test(Tablero::class)->set('periodo', '2026-09')->assertSee('Sep–dic 2026')
            ->set('sedeId', (string) Sede::where('clave', 'CUOP-LOR')->value('id'))
            ->assertSeeHtml('wire:key="ts-'.Sede::where('clave', 'CUOP-LOR')->value('id').'"')
            ->assertDontSeeHtml('wire:key="ts-'.Sede::where('clave', 'CUOP-AGS')->value('id').'"');
    }
}
