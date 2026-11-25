<?php

namespace Tests\Feature;

use App\Actions\Cobranza\CancelarPagoAction;
use App\Livewire\Reportes\Ingresos;
use App\Models\Pago;
use App\Models\User;
use App\Services\Reportes\IngresosService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class IngresosTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_concepto_base(): void
    {
        $this->assertSame('MENSUALIDAD', IngresosService::conceptoBase('MENSUALIDAD NOV 2026'));
        $this->assertSame('INGLÉS', IngresosService::conceptoBase('INGLÉS DIC 2026'));
        $this->assertSame('INSCRIPCIÓN', IngresosService::conceptoBase('INSCRIPCIÓN 2027-1'));
        $this->assertSame('MENSUALIDAD', IngresosService::conceptoBase('MENSUALIDAD OCT 2026 (INTELL)'));
        $this->assertSame('CONSTANCIA DE ESTUDIOS', IngresosService::conceptoBase('CONSTANCIA DE ESTUDIOS'));
    }

    public function test_totales_por_concepto_forma_y_sede_sin_cancelados(): void
    {
        $r = app(IngresosService::class)->generar(Carbon::parse('2026-09-01'), Carbon::parse('2026-11-30'));
        $total = (float) Pago::whereBetween('fecha_pago', ['2026-09-01', '2026-11-30 23:59:59'])->sum('monto_pagado');

        $this->assertSame(round($total, 2), $r['total']);
        $this->assertSame($r['total'], round($r['por_concepto']->sum('total'), 2));
        $this->assertSame($r['total'], round(array_sum($r['por_forma']), 2));
        $this->assertSame($r['total'], round($r['por_sede']->sum('total'), 2));
        $this->assertSame(600.0, $r['por_concepto']->firstWhere('clave', 'INSCRIPCIÓN')['total']);
        $this->assertSame(350.0, $r['por_concepto']->firstWhere('clave', 'INGLÉS')['total']);

        $pago = Pago::where('monto_pagado', 600)->first();
        app(CancelarPagoAction::class)->execute($pago->id, User::where('email', 'admin@cuop.test')->first(), 'PRUEBA REPORTE');
        $r2 = app(IngresosService::class)->generar(Carbon::parse('2026-09-01'), Carbon::parse('2026-11-30'));
        $this->assertSame(round($total - 600, 2), $r2['total']);
        $this->assertNull($r2['por_concepto']->firstWhere('clave', 'INSCRIPCIÓN'));
    }

    public function test_pantalla_exporta_y_es_solo_de_administrador(): void
    {
        $this->actingAs(User::where('email', 'admin@cuop.test')->first());
        Livewire::test(Ingresos::class)->set('desde', '2026-09-01')->set('hasta', '2026-11-30')
            ->assertSee('MENSUALIDAD')->call('exportar')->assertFileDownloaded('ingresos-2026-09-01-al-2026-11-30.xlsx');

        $this->actingAs(User::where('email', 'caja@cuop.test')->first())->get('/reportes/ingresos')->assertForbidden();
    }
}
