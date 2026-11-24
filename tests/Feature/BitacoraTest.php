<?php

namespace Tests\Feature;

use App\Actions\Cobranza\CancelarPagoAction;
use App\Actions\Cobranza\CrearCargoAction;
use App\Livewire\Admin\BitacoraCambios;
use App\Livewire\Admin\Catalogos;
use App\Livewire\Admin\Usuarios;
use App\Livewire\Alumnos\PadronAlumnos;
use App\Models\Alumno;
use App\Models\Bitacora;
use App\Models\Pago;
use App\Models\PlanPago;
use App\Models\User;
use App\Services\Padron\ImportarAlumnosService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use LogicException;
use Tests\TestCase;

class BitacoraTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::where('email', 'admin@cuop.test')->first();
        $this->actingAs($this->admin);
    }

    private function ultimo(string $entidad, string $id): ?Bitacora
    {
        return Bitacora::where('entidad', $entidad)->where('entidad_id', $id)->orderByDesc('id')->first();
    }

    public function test_cambio_de_alumno_en_el_padron_registra_antes_y_despues(): void
    {
        Livewire::test(PadronAlumnos::class)->call('editar', 'CUOP26002')
            ->set('form.estatus', 'BAJA_TEMPORAL')->set('form.costo_ingles', '260')->call('guardar')->assertHasNoErrors();

        $b = $this->ultimo('alumno', 'CUOP26002');
        $this->assertSame('actualizado', $b->accion);
        $this->assertSame($this->admin->id, $b->user_id);
        $this->assertSame(['antes' => 'ACTIVO', 'despues' => 'BAJA_TEMPORAL'], $b->cambios['estatus']);
        $this->assertSame(['antes' => null, 'despues' => '260.00'], $b->cambios['costo_ingles']);
        $this->assertArrayNotHasKey('updated_at', $b->cambios);

        // Guardar sin cambios no genera registro
        $antes = Bitacora::count();
        Livewire::test(PadronAlumnos::class)->call('editar', 'CUOP26002')->call('guardar');
        $this->assertSame($antes, Bitacora::count());

        // El historial aparece en el formulario del alumno
        Livewire::test(PadronAlumnos::class)->call('editar', 'CUOP26002')
            ->assertSee('Historial de cambios')->assertSee('Baja temporal');
    }

    public function test_importacion_masiva_queda_registrada_por_alumno(): void
    {
        $s = app(ImportarAlumnosService::class);
        $s->importar($s->analizarFilas(collect([collect([
            'matricula' => 'CUOP26003', 'nombre_completo' => '', 'sede' => '', 'carrera' => '', 'grupo' => '27-2 NUEVO',
            'plan_pago' => '', 'estatus' => 'ACTIVO',
        ])])));

        $b = $this->ultimo('alumno', 'CUOP26003');
        $this->assertSame('actualizado', $b->accion);
        $this->assertSame(['antes' => '27-1 LIC-DER-ESC-MAT-3B', 'despues' => '27-2 NUEVO'], $b->cambios['grupo']);
        $this->assertStringContainsString('Importación de padrón', $b->descripcion);
    }

    public function test_catalogo_usuarios_y_contrasena_oculta(): void
    {
        $plan = PlanPago::where('nombre', 'SABATINO 2026')->first();
        Livewire::test(Catalogos::class, ['tab' => 'planes'])->set('tab', 'planes')
            ->call('editar', $plan->id)->set('form.monto_mensualidad', '1900')->call('guardar');
        $this->assertSame(['antes' => '1850.00', 'despues' => '1900.00'], $this->ultimo('plan_pago', (string) $plan->id)->cambios['monto_mensualidad']);

        $cajero = User::where('email', 'caja@cuop.test')->first();
        Livewire::test(Usuarios::class)->call('editar', $cajero->id)
            ->set('rol', 'Control Escolar')->set('password', 'NuevaClave2026')->call('guardar')->assertHasNoErrors();

        $registros = Bitacora::where('entidad', 'usuario')->where('entidad_id', (string) $cajero->id)->orderBy('id')->get();
        $pwd = $registros->firstWhere('accion', 'actualizado')->cambios['password'];
        $this->assertSame(['antes' => '••••', 'despues' => '••••'], $pwd, 'Nunca se guarda el hash de la contraseña');
        $this->assertSame(['antes' => 'Cajero', 'despues' => 'Control Escolar'], $registros->firstWhere('accion', 'rol')->cambios['rol']);
    }

    public function test_cancelacion_de_pago_y_cargos_manuales(): void
    {
        $pago = Pago::whereHas('adeudo', fn ($q) => $q->where('matricula', 'CUOP26002'))->first();
        app(CancelarPagoAction::class)->execute($pago->id, $this->admin, 'pago duplicado');

        $b = $this->ultimo('pago', (string) $pago->id);
        $this->assertSame('cancelado', $b->accion);
        $this->assertStringContainsString('CUOP26002 · Folio '.$pago->folio_pago, $b->descripcion);
        $this->assertStringContainsString('PAGO DUPLICADO', $b->descripcion);

        $cargo = app(CrearCargoAction::class)->execute('CUOP26002', 'constancia', 150, Carbon::parse('2026-12-01'), $this->admin);
        $this->assertSame('creado', $this->ultimo('adeudo', (string) $cargo->id)->accion);
        app(CrearCargoAction::class)->eliminar($cargo->id, $this->admin);
        $this->assertSame('eliminado', $this->ultimo('adeudo', (string) $cargo->id)->accion);

        // El alumno ve ambos en su historial
        $this->assertTrue(Livewire::test(PadronAlumnos::class)->call('editar', 'CUOP26002')
            ->instance()->historial->pluck('entidad')->contains('pago'));
    }

    public function test_procesos_automaticos_dejan_resumen(): void
    {
        $this->artisan('cobranza:aplicar-recargos', ['--fecha' => '2026-11-24'])->assertSuccessful();
        $b = $this->ultimo('proceso', 'cobranza:aplicar-recargos');
        $this->assertStringContainsString('Recargos aplicados: 5', $b->descripcion);

        $this->artisan('cobranza:aplicar-recargos', ['--fecha' => '2026-11-24', '--simular' => true])->assertSuccessful();
        $this->assertSame(1, Bitacora::where('entidad_id', 'cobranza:aplicar-recargos')->count(), 'La simulación no se registra');
    }

    public function test_es_inmutable_y_solo_la_ve_el_administrador(): void
    {
        $b = Bitacora::first();
        $this->expectException(LogicException::class);
        $b->update(['descripcion' => 'alterado']);
    }

    public function test_pantalla_y_filtros(): void
    {
        Alumno::find('CUOP26001')->update(['grupo' => 'GRUPO BITACORA']);

        Livewire::test(BitacoraCambios::class)
            ->set('buscar', 'CUOP26001')->assertSee('GRUPO BITACORA')
            ->set('entidad', 'usuario')->assertDontSee('GRUPO BITACORA');

        $this->get('/admin/bitacora')->assertOk();
        $this->actingAs(User::where('email', 'caja@cuop.test')->first())->get('/admin/bitacora')->assertForbidden();
    }
}
