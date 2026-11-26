<?php

namespace Tests\Feature;

use App\Livewire\Admin\Catalogos;
use App\Livewire\Alumnos\PadronAlumnos;
use App\Models\Alumno;
use App\Models\Carrera;
use App\Models\Sede;
use App\Models\User;
use App\Services\Padron\ImportarAlumnosService;
use Database\Seeders\CatalogosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/** Arranque en producción: solo el catálogo real (CatalogosSeeder), sin datos de prueba. */
class SoloLoretoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        // Base de producción: sin datos de prueba. Se borra dentro de la transacción de la
        // prueba (RefreshDatabase la revierte al terminar), en orden de dependencias.
        foreach (['whatsapp_mensajes', 'bitacora', 'cortes_caja', 'pagos', 'adeudos', 'alumnos', 'planes_pago',
            'carreras', 'sedes', 'folios_consecutivos', 'model_has_roles', 'users', 'ajustes'] as $tabla) {
            DB::table($tabla)->delete();
        }
        $this->seed(CatalogosSeeder::class);
        $this->admin = User::create(['name' => 'Admin', 'email' => 'a@cuop.test', 'password' => bcrypt('x'), 'email_verified_at' => now()]);
        $this->admin->assignRole('Administrador');
        $this->actingAs($this->admin);
    }

    private function fila(string $mat, string $sede, string $carrera = 'LICENCIATURA EN EDUCACIÓN', string $plan = 'PLAN 1370')
    {
        return collect(['matricula' => $mat, 'nombre_completo' => 'ALUMNO '.$mat, 'sede' => $sede, 'carrera' => $carrera,
            'grupo' => '27-1 LIC-EDUN-NESC-SAB-1A', 'plan_pago' => $plan, 'estatus' => 'ACTIVO']);
    }

    public function test_solo_loreto_y_sus_carreras_arrancan_activas(): void
    {
        $this->assertSame(['CUOP-LOR'], Sede::activas()->pluck('clave')->all());
        $this->assertSame(5, Sede::count(), 'Las demás sedes quedan registradas para activarlas después');
        $this->assertEqualsCanonicalizing(CatalogosSeeder::CARRERAS_ACTIVAS, Carrera::activas()->pluck('nombre')->all());
        $this->assertSame('NO ESCOLARIZADO', Carrera::activas()->distinct()->value('modalidad'));

        $c = Livewire::test(PadronAlumnos::class);
        $this->assertSame(['CUOP-LOR'], $c->instance()->catalogos['sedes']->pluck('clave')->all());
        $this->assertCount(5, $c->instance()->catalogos['carreras']);
        $this->get('/tablero')->assertOk()->assertDontSeeHtml('CUOP-AGS');
    }

    public function test_importacion_solo_acepta_loreto(): void
    {
        $s = app(ImportarAlumnosService::class);
        $a = $s->analizarFilas(collect([
            $this->fila('CUOP-LOR09001', 'CUOP-LOR'),
            $this->fila('CUOP-AGS09001', 'CUOP-AGS', plan: 'PLAN 1530'),
            $this->fila('CUOP-LOR09002', 'CUOP-LOR', 'BACHILLERATO'),
        ]));

        $this->assertSame(['CUOP-LOR09001'], collect($a['crear'])->pluck('matricula')->all());
        $errores = collect($a['errores'])->keyBy('matricula');
        $this->assertStringContainsString('no está activa', implode(' ', $errores['CUOP-AGS09001']['mensajes']));
        $this->assertStringContainsString('no está activa', implode(' ', $errores['CUOP-LOR09002']['mensajes']));
    }

    public function test_activar_otra_sede_despues_y_proteccion_al_desactivar(): void
    {
        $ags = Sede::where('clave', 'CUOP-AGS')->first();
        Livewire::test(Catalogos::class)->call('alternarActiva', $ags->id)->assertDispatched('toast', tipo: 'success');
        $this->assertTrue($ags->fresh()->activa);

        // Volver a correr el seeder no deshace la activación
        $this->seed(CatalogosSeeder::class);
        $this->assertTrue($ags->fresh()->activa);

        // Con alumnos activos no se puede desactivar
        $s = app(ImportarAlumnosService::class);
        $s->importar($s->analizarFilas(collect([$this->fila('CUOP-LOR09003', 'CUOP-LOR')])));
        $lor = Sede::where('clave', 'CUOP-LOR')->first();
        Livewire::test(Catalogos::class)->call('alternarActiva', $lor->id)->assertDispatched('toast', tipo: 'error');
        $this->assertTrue($lor->fresh()->activa);

        // Planes: en el catálogo solo se ven los de sedes activas
        $ags->fresh()->update(['activa' => false]);
        $planes = Livewire::test(Catalogos::class, ['tab' => 'planes'])->set('tab', 'planes')->viewData('registros');
        $this->assertSame(['CUOP-LOR'], $planes->pluck('sede.clave')->unique()->values()->all());
        $this->assertSame(7, $planes->count());
        $this->assertSame(1, Alumno::count());
    }
}
