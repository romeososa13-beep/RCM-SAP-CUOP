<?php

namespace Tests\Feature;

use App\Livewire\Admin\Catalogos;
use App\Livewire\Alumnos\PadronAlumnos;
use App\Models\Alumno;
use App\Models\Carrera;
use App\Models\PlanPago;
use App\Models\Sede;
use App\Models\User;
use App\Services\Padron\ImportarAlumnosService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PlanesPorSedeTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private Sede $lor;
    private Sede $cal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lor = Sede::where('clave', 'CUOP-LOR')->first();
        $this->cal = Sede::where('clave', 'CUOP-CAL')->first();
        $this->actingAs(User::where('email', 'admin@cuop.test')->first());
    }

    public function test_dos_sedes_pueden_tener_plan_con_el_mismo_nombre_pero_no_la_misma_sede(): void
    {
        Livewire::test(Catalogos::class, ['tab' => 'planes'])->set('tab', 'planes')
            ->call('nuevo')->set('form.nombre', 'SABATINO 2026')->set('form.sede_id', $this->cal->id)
            ->set('form.monto_mensualidad', '1650')->set('form.monto_recargo', '150')
            ->call('guardar')->assertHasNoErrors();

        Livewire::test(Catalogos::class, ['tab' => 'planes'])->set('tab', 'planes')
            ->call('nuevo')->set('form.nombre', 'SABATINO 2026')->set('form.sede_id', $this->cal->id)
            ->set('form.monto_mensualidad', '1')->call('guardar')->assertHasErrors('form.nombre');

        Livewire::test(Catalogos::class, ['tab' => 'planes'])->set('tab', 'planes')
            ->call('nuevo')->set('form.nombre', 'SIN SEDE')->set('form.monto_mensualidad', '1')
            ->call('guardar')->assertHasErrors('form.sede_id');

        $this->assertSame(2, PlanPago::where('nombre', 'SABATINO 2026')->count());
    }

    public function test_no_se_mueve_un_plan_de_sede_si_deja_alumnos_fuera(): void
    {
        $plan = PlanPago::where('sede_id', $this->lor->id)->where('nombre', 'SABATINO 2026')->first(); // 3 alumnos de Loreto

        Livewire::test(Catalogos::class, ['tab' => 'planes'])->set('tab', 'planes')
            ->call('editar', $plan->id)->set('form.sede_id', $this->cal->id)
            ->call('guardar')->assertHasErrors('form.sede_id');

        $this->assertSame($this->lor->id, $plan->fresh()->sede_id);
    }

    public function test_formulario_de_alumno_solo_ofrece_y_acepta_planes_de_su_sede(): void
    {
        $planLor = PlanPago::where('sede_id', $this->lor->id)->where('nombre', 'SABATINO 2026')->first();
        $sedeUnica = Sede::create(['clave' => 'CUOP-TST', 'nombre' => 'SEDE CON UN SOLO PLAN']);
        $planCal = PlanPago::create(['nombre' => 'UNICO', 'sede_id' => $sedeUnica->id, 'monto_mensualidad' => 1000]);
        $planAgs = PlanPago::whereHas('sede', fn ($q) => $q->where('clave', 'CUOP-AGS'))->first();
        $carrera = Carrera::first();

        $c = Livewire::test(PadronAlumnos::class)->call('nuevo')
            ->set('form.matricula', 'CUOP27100')->set('form.nombre_completo', 'PRUEBA SEDE')
            ->set('form.carrera_id', $carrera->id)->set('form.grupo', 'G1')
            ->set('form.sede_id', $this->lor->id);

        $ofrecidos = $c->instance()->planesFormulario->pluck('id');
        $this->assertTrue($ofrecidos->contains($planLor->id));
        $this->assertFalse($ofrecidos->contains($planAgs->id));
        $this->assertFalse($ofrecidos->contains($planCal->id));
        $this->assertTrue($ofrecidos->contains(PlanPago::where('sede_id', $this->lor->id)->where('nombre', 'PLAN 1370')->value('id')));

        // Forzar un plan de otra sede desde el navegador es rechazado por el servidor.
        $c->set('form.plan_pago_id', $planAgs->id)->call('guardar')->assertHasErrors('form.plan_pago_id');
        $this->assertNull(Alumno::find('CUOP27100'));

        $c->set('form.plan_pago_id', $planLor->id)->call('guardar')->assertHasNoErrors();
        $this->assertSame($planLor->id, Alumno::find('CUOP27100')->plan_pago_id);

        // Cambiar de sede: el plan de Loreto ya no aplica. Con un solo plan, se preselecciona;
        // con varios, se deja vacío para que el usuario elija.
        $c->call('editar', 'CUOP27100')->set('form.sede_id', $sedeUnica->id)->assertSet('form.plan_pago_id', $planCal->id);
        $c->set('form.sede_id', $this->cal->id)->assertSet('form.plan_pago_id', null);
    }

    public function test_importacion_resuelve_el_plan_por_nombre_dentro_de_la_sede(): void
    {
        $carrera = Carrera::first();
        $planCal = PlanPago::create(['nombre' => 'SABATINO 2026', 'sede_id' => $this->cal->id, 'monto_mensualidad' => 1650, 'monto_recargo' => 150]);
        $planLor = PlanPago::where('sede_id', $this->lor->id)->where('nombre', 'SABATINO 2026')->first();

        $fila = fn ($mat, $sede, $plan) => collect([
            'matricula' => $mat, 'nombre_completo' => 'ALUMNO '.$mat, 'sede' => $sede, 'carrera' => $carrera->nombre,
            'grupo' => 'G1', 'plan_pago' => $plan, 'estatus' => 'ACTIVO',
        ]);

        $a = app(ImportarAlumnosService::class)->analizarFilas(collect([
            $fila('CUOP27201', 'CUOP-CAL', 'sabatino 2026'),     // mismo nombre → plan de Calvillo
            $fila('CUOP27202', 'CUOP-LOR', 'SABATINO 2026'),     // mismo nombre → plan de Loreto
            $fila('CUOP27203', 'CUOP-SJG', 'SABATINO 2026'),     // San José no tiene ese plan
            $fila('CUOP27204', 'CUOP-CAL', (string) $planLor->id), // ID de un plan de otra sede
        ]));

        $this->assertSame($planCal->id, collect($a['crear'])->firstWhere('matricula', 'CUOP27201')['plan_pago_id']);
        $this->assertSame($planLor->id, collect($a['crear'])->firstWhere('matricula', 'CUOP27202')['plan_pago_id']);

        $errores = collect($a['errores'])->keyBy('matricula');
        $this->assertStringContainsString('no tiene un plan', implode(' ', $errores['CUOP27203']['mensajes']));
        $this->assertStringContainsString('no corresponde', implode(' ', $errores['CUOP27204']['mensajes']));
    }

    public function test_no_se_elimina_sede_con_planes_aunque_no_tenga_alumnos(): void
    {
        PlanPago::create(['nombre' => 'PLAN CALVILLO', 'sede_id' => $this->cal->id, 'monto_mensualidad' => 1000]);

        Livewire::test(Catalogos::class)->call('eliminar', $this->cal->id)->assertDispatched('toast', tipo: 'error');
        $this->assertModelExists($this->cal);
    }

    public function test_plan_obligatorio_solo_para_alumnos_activos(): void
    {
        $carrera = Carrera::first();
        $fila = fn ($mat, $estatus) => collect([
            'matricula' => $mat, 'nombre_completo' => 'ALUMNO '.$mat, 'sede' => 'CUOP-LOR', 'carrera' => $carrera->nombre,
            'grupo' => 'G1', 'plan_pago' => '', 'estatus' => $estatus,
        ]);

        $a = app(ImportarAlumnosService::class)->analizarFilas(collect([
            $fila('CUOP-LOR09101', 'EGRESADO'), $fila('CUOP-LOR09102', 'BAJA_DEFINITIVA'), $fila('CUOP-LOR09103', 'ACTIVO'),
        ]));
        $this->assertEqualsCanonicalizing(['CUOP-LOR09101', 'CUOP-LOR09102'], collect($a['crear'])->pluck('matricula')->all());
        $this->assertSame('CUOP-LOR09103', $a['errores'][0]['matricula']);
        $this->assertStringContainsString('obligatorio para alumnos ACTIVOS', $a['errores'][0]['mensajes'][0]);

        app(ImportarAlumnosService::class)->importar($a);
        $this->assertNull(Alumno::find('CUOP-LOR09101')->plan_pago_id);

        // Reactivar a un egresado sin plan obliga a asignarle uno.
        Livewire::test(PadronAlumnos::class)->call('editar', 'CUOP-LOR09101')
            ->set('form.estatus', 'ACTIVO')->call('guardar')->assertHasErrors('form.plan_pago_id')
            ->set('form.estatus', 'EGRESADO')->call('guardar')->assertHasNoErrors();

        // Su estado de cuenta se puede abrir aunque no tenga plan.
        $this->get('/caja/estado-cuenta/CUOP-LOR09101')->assertOk()->assertSee('Sin plan');
        $this->get('/alumnos')->assertOk();
    }
}
