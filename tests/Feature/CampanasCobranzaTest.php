<?php

namespace Tests\Feature;

use App\Actions\Cobranza\RegistrarPagoAction;
use App\Enums\FormaPago;
use App\Livewire\Cobranza\Campanas;
use App\Models\Adeudo;
use App\Models\Alumno;
use App\Models\CampanaCobranza;
use App\Models\Sede;
use App\Models\User;
use App\Models\WhatsappMensaje;
use App\Services\Cobranza\CampanaService;
use App\Services\Cobranza\RecargoService;
use App\Services\WhatsApp\Plantillas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class CampanasCobranzaTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private int $lor;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-24 10:00');
        app(RecargoService::class)->aplicar(today());
        Alumno::query()->update(['celular' => null, 'tel_tutor' => null, 'tel_alterno' => null]);
        Alumno::find('CUOP26001')->update(['celular' => '4962223344']);
        Alumno::find('CUOP26002')->update(['celular' => '4961112233']);
        $this->lor = Sede::where('clave', 'CUOP-LOR')->value('id');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function matriculas(string $segmento, array $filtros = []): array
    {
        return app(CampanaService::class)->destinatarios($segmento, $filtros)
            ->map(fn ($f) => $f['alumno']->matricula)->sort()->values()->all();
    }

    public function test_segmentos_y_filtros(): void
    {
        // 24/nov: 26001 debe parte de noviembre (bloqueo desde el 16), 26002 debe octubre, 26004 octubre (otra sede).
        // 26005 está de baja temporal: nunca entra.
        $this->assertSame(['CUOP26001', 'CUOP26002', 'CUOP26004'], $this->matriculas('con_bloqueo'));
        $this->assertSame(['CUOP26001', 'CUOP26002'], $this->matriculas('con_bloqueo', ['sede_id' => $this->lor]));
        $this->assertSame([], $this->matriculas('con_recargo'), 'quien ya tiene bloqueo no recibe además el aviso de recargo');

        // Días de atraso: octubre lleva 45 días; noviembre, 14.
        $this->assertSame(['CUOP26002', 'CUOP26004'], $this->matriculas('atraso', ['dias_min' => 30]));
        $this->assertContains('CUOP26001', $this->matriculas('atraso', ['dias_min' => 7]));

        $fila = app(CampanaService::class)->destinatarios('atraso', ['dias_min' => 30, 'sede_id' => $this->lor])->first();
        $this->assertSame(45, $fila['dias_atraso']);
        $this->assertNull($fila['contacto']);

        // Un cargo futuro (enero) no se cobra en la campaña ni aparece en el mensaje.
        $antes = $fila['saldo'];
        Adeudo::create(['matricula' => 'CUOP26002', 'concepto' => 'COLEGIATURA ENE 2027', 'tipo' => 'COLEGIATURA',
            'fecha_vencimiento' => '2027-01-10', 'monto_original' => 1850]);
        $this->assertSame($antes, app(CampanaService::class)->destinatarios('atraso', ['dias_min' => 30, 'sede_id' => $this->lor])->first()['saldo']);
        $this->assertStringNotContainsString('ENE 2027', implode(' ', Plantillas::variables('aviso_bloqueo', Alumno::find('CUOP26002'))));

        // Saldo mínimo
        $this->assertSame([], $this->matriculas('con_bloqueo', ['sede_id' => $this->lor, 'saldo_min' => 999999]));
    }

    public function test_por_vencer_y_con_recargo_en_diciembre(): void
    {
        // Liquidamos todo lo anterior de 26001 para que solo deba diciembre.
        Carbon::setTestNow('2026-12-03 10:00');
        $this->artisan('cobranza:generar-mensualidad')->assertSuccessful();
        $cajera = User::where('email', 'caja@cuop.test')->first();
        foreach (Adeudo::where('matricula', 'CUOP26001')->where('pagado', false)->whereDate('fecha_vencimiento', '<', today())->with('pagos')->get() as $d) {
            app(RegistrarPagoAction::class)->execute($d->id, $d->saldo_pendiente, FormaPago::EFECTIVO, $cajera);
        }

        $this->assertContains('CUOP26001', $this->matriculas('por_vencer'));
        $this->assertNotContains('CUOP26001', $this->matriculas('con_bloqueo'));

        // 12/dic: ya tiene recargo de diciembre, todavía sin bloqueo.
        Carbon::setTestNow('2026-12-12 10:00');
        app(RecargoService::class)->aplicar(today());
        $this->assertContains('CUOP26001', $this->matriculas('con_recargo'));
        $this->assertNotContains('CUOP26001', $this->matriculas('por_vencer'));
    }

    public function test_lanzar_campana_y_medir_quien_pago(): void
    {
        $cajera = User::where('email', 'caja@cuop.test')->first();
        Alumno::find('CUOP26002')->update(['whatsapp_baja_at' => now()]);
        // Pago anterior a la campaña: no cuenta como resultado.
        $nov = Adeudo::where('matricula', 'CUOP26001')->where('concepto', 'like', 'COLEGIATURA NOV%')->first()
            ?? Adeudo::where('matricula', 'CUOP26001')->where('pagado', false)->where('tipo', 'COLEGIATURA')->orderBy('fecha_vencimiento')->first();
        app(RegistrarPagoAction::class)->execute($nov->id, 100, FormaPago::EFECTIVO, $cajera);

        Carbon::setTestNow('2026-11-24 11:00');
        $campana = app(CampanaService::class)->lanzar('con_bloqueo', 'aviso_bloqueo', ['sede_id' => $this->lor], '', $cajera);

        $this->assertSame(2, $campana->destinatarios);
        $this->assertSame(1, $campana->en_cola);
        $this->assertSame(['Pidió BAJA' => 1], $campana->omitidos);
        $this->assertGreaterThan(0, (float) $campana->saldo_objetivo);
        $m = WhatsappMensaje::where('campana_id', $campana->id)->sole();
        $this->assertSame('CUOP26001', $m->matricula);
        $this->assertSame('aviso_bloqueo', $m->plantilla);
        $this->assertStringContainsString('Con bloqueo', $campana->nombre);

        $r = app(CampanaService::class)->resultados($campana);
        $this->assertSame(0, $r['alumnos_pagaron']);
        $this->assertSame(1, $r['contactados']);

        // Después del mensaje paga 500.
        Carbon::setTestNow('2026-11-25 09:30');
        app(RegistrarPagoAction::class)->execute($nov->id, 500, FormaPago::EFECTIVO, $cajera);
        $r = app(CampanaService::class)->resultados($campana->fresh());
        $this->assertSame(1, $r['alumnos_pagaron']);
        $this->assertSame(500.0, $r['recuperado']);
        $this->assertSame(500.0, $r['detalle']->first()['pago']);

        // El mismo día no se repite el aviso aunque se lance otra campaña.
        Carbon::setTestNow('2026-11-25 10:00');
        app(CampanaService::class)->lanzar('con_bloqueo', 'aviso_bloqueo', ['sede_id' => $this->lor], 'Repetida', $cajera);
        Carbon::setTestNow('2026-11-25 12:00');
        $otra = app(CampanaService::class)->lanzar('con_bloqueo', 'aviso_bloqueo', ['sede_id' => $this->lor], 'Repetida 2', $cajera);
        $this->assertSame(0, $otra->en_cola);
        $this->assertSame(1, $otra->omitidos['Ya se le envió este aviso hoy.']);
    }

    public function test_pantalla_de_campanas_y_permisos(): void
    {
        $this->actingAs(User::where('email', 'escolar@cuop.test')->first())->get('/cobranza/campanas')->assertForbidden();

        $this->actingAs(User::where('email', 'caja@cuop.test')->first());
        $this->get('/cobranza/campanas')->assertOk()->assertSee('Campañas de cobranza');

        Livewire::test(Campanas::class)
            ->set('segmento', 'con_bloqueo')
            ->assertSet('plantilla', 'aviso_bloqueo')
            ->set('sedeId', (string) $this->lor)
            ->assertSee('CUOP26001')->assertSee('Enviar a 2 alumnos')
            ->assertSee('por un adeudo de')
            ->set('nombre', 'Bloqueo noviembre')
            ->call('enviar')
            ->assertHasNoErrors()
            ->assertSee('Bloqueo noviembre')->assertSee('Contactados');

        $this->assertSame(2, CampanaCobranza::sole()->en_cola);
        $this->assertDatabaseHas('bitacora', ['entidad' => 'campana_cobranza']);

        Livewire::test(Campanas::class)->set('plantilla', 'pago_recibido')->call('enviar')->assertHasErrors('plantilla');
    }
}
