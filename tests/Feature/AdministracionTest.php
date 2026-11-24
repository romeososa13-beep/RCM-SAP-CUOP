<?php

namespace Tests\Feature;

use App\Actions\Cobranza\RegistrarPagoAction;
use App\Enums\FormaPago;
use App\Livewire\Admin\Catalogos;
use App\Livewire\Admin\Usuarios;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\MiCuenta;
use App\Livewire\Caja\EstadoCuenta;
use App\Models\Adeudo;
use App\Models\PlanPago;
use App\Models\Sede;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class AdministracionTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function u(string $email): User
    {
        return User::where('email', $email)->first();
    }

    public function test_admin_crea_usuario_con_rol_y_puede_entrar(): void
    {
        $this->actingAs($this->u('admin@cuop.test'));

        Livewire::test(Usuarios::class)
            ->call('nuevo')
            ->set('name', 'Pedro Caja Dos')->set('email', 'CAJA2@cuop.test')->set('rol', 'Cajero')
            ->set('password', 'corta')
            ->call('guardar')->assertHasErrors('password')
            ->set('password', 'Segura2026')
            ->call('guardar')->assertHasNoErrors()->assertSet('modal', false);

        $nuevo = $this->u('caja2@cuop.test');
        $this->assertTrue($nuevo->hasRole('Cajero'));
        $this->assertTrue(Hash::check('Segura2026', $nuevo->password));
    }

    public function test_usuario_desactivado_no_entra_y_pierde_su_sesion(): void
    {
        $cajero = $this->u('caja@cuop.test');
        $this->actingAs($this->u('admin@cuop.test'));
        Livewire::test(Usuarios::class)->call('alternarActivo', $cajero->id);
        $this->assertFalse($cajero->fresh()->activo);

        auth()->logout();
        Livewire::test(Login::class)
            ->set('email', 'caja@cuop.test')->set('password', 'password')
            ->call('login')->assertHasErrors('email');
        $this->assertGuest();

        // Si ya tenía sesión abierta, el middleware lo saca.
        $this->actingAs($cajero->fresh())->get('/caja/estado-cuenta')->assertRedirect('/login');
    }

    public function test_admin_no_puede_quitarse_el_rol_ni_desactivarse(): void
    {
        $admin = $this->u('admin@cuop.test');
        $this->actingAs($admin);

        Livewire::test(Usuarios::class)
            ->call('editar', $admin->id)->set('rol', 'Cajero')
            ->call('guardar')->assertHasErrors('rol');

        Livewire::test(Usuarios::class)->call('alternarActivo', $admin->id);

        $this->assertTrue($admin->fresh()->hasRole('Administrador'));
        $this->assertTrue($admin->fresh()->activo);
    }

    public function test_catalogos_crea_edita_y_protege_registros_en_uso(): void
    {
        $this->actingAs($this->u('admin@cuop.test'));

        Livewire::test(Catalogos::class)
            ->call('nuevo')->set('form.clave', 'cuop-nor')->set('form.nombre', 'plantel norte')
            ->call('guardar')->assertHasNoErrors();
        $this->assertSame('PLANTEL NORTE', Sede::where('clave', 'CUOP-NOR')->value('nombre'));

        Livewire::test(Catalogos::class)
            ->call('nuevo')->set('form.clave', 'CUOP-LOR')->set('form.nombre', 'X')
            ->call('guardar')->assertHasErrors('form.clave'); // clave duplicada

        $enUso = Sede::where('clave', 'CUOP-LOR')->first();
        Livewire::test(Catalogos::class)->call('eliminar', $enUso->id)->assertDispatched('toast', tipo: 'error');
        $this->assertModelExists($enUso);

        $plan = PlanPago::first();
        Livewire::test(Catalogos::class, ['tab' => 'planes'])
            ->set('tab', 'planes')
            ->call('editar', $plan->id)->set('form.monto_mensualidad', '1999.50')
            ->call('guardar')->assertHasNoErrors();
        $this->assertSame('1999.50', $plan->fresh()->monto_mensualidad);
        // Los cargos ya emitidos conservan su monto original
        $this->assertSame('1850.00', Adeudo::where('matricula', 'CUOP26001')->where('concepto', 'MENSUALIDAD DIC 2026')->value('monto_original'));
    }

    public function test_cajero_agrega_cargo_manual_y_solo_admin_lo_elimina_si_no_tiene_pagos(): void
    {
        $cajero = $this->u('caja@cuop.test');
        $this->actingAs($cajero);

        Livewire::test(EstadoCuenta::class)
            ->call('seleccionarAlumno', 'CUOP26003')
            ->call('abrirModalCargo')
            ->set('cargoConcepto', 'constancia de estudios')->set('cargoMonto', '150')->set('cargoVencimiento', '2026-11-30')
            ->call('guardarCargo')->assertHasNoErrors()
            ->assertSee('CONSTANCIA DE ESTUDIOS');

        $cargo = Adeudo::where('concepto', 'CONSTANCIA DE ESTUDIOS')->first();
        $this->assertSame($cajero->id, $cargo->creado_por);
        $this->assertSame('150.00', $cargo->monto_original);

        // Un cajero no puede eliminar
        $this->expectsDatabaseHasCargo($cargo, fn () => Livewire::test(EstadoCuenta::class)
            ->call('seleccionarAlumno', 'CUOP26003')->call('eliminarCargo', $cargo->id)->assertForbidden());

        // Con pago registrado, ni el admin puede eliminar
        app(RegistrarPagoAction::class)->execute($cargo->id, 50, FormaPago::EFECTIVO, $cajero);
        $this->actingAs($this->u('admin@cuop.test'));
        Livewire::test(EstadoCuenta::class)
            ->call('seleccionarAlumno', 'CUOP26003')->call('eliminarCargo', $cargo->id)
            ->assertDispatched('toast', tipo: 'error');
        $this->assertModelExists($cargo);

        // Sin pagos, el admin sí puede
        $otro = Adeudo::create(['matricula' => 'CUOP26003', 'concepto' => 'ERROR DE CAPTURA', 'fecha_vencimiento' => '2026-12-01', 'monto_original' => 10]);
        Livewire::test(EstadoCuenta::class)
            ->call('seleccionarAlumno', 'CUOP26003')->call('eliminarCargo', $otro->id)
            ->assertDispatched('toast', tipo: 'success');
        $this->assertModelMissing($otro);
    }

    public function test_mi_cuenta_cambia_password(): void
    {
        $this->actingAs($this->u('escolar@cuop.test'));

        Livewire::test(MiCuenta::class)
            ->set('actual', 'incorrecta')->set('nueva', 'NuevaClave9')->set('nueva_confirmation', 'NuevaClave9')
            ->call('cambiarPassword')->assertHasErrors('actual')
            ->set('actual', 'password')
            ->call('cambiarPassword')->assertHasNoErrors();

        $this->assertTrue(Hash::check('NuevaClave9', $this->u('escolar@cuop.test')->password));
    }

    public function test_solo_admin_accede_a_administracion(): void
    {
        foreach (['caja@cuop.test', 'escolar@cuop.test'] as $email) {
            $this->actingAs($this->u($email));
            $this->get('/admin/usuarios')->assertForbidden();
            $this->get('/admin/catalogos')->assertForbidden();
            $this->get('/mi-cuenta')->assertOk();
        }

        $this->actingAs($this->u('admin@cuop.test'));
        $this->get('/admin/usuarios')->assertOk()->assertSee('caja@cuop.test');
        $this->get('/admin/catalogos?tab=planes')->assertOk()->assertSee('SABATINO 2026');
    }

    private function expectsDatabaseHasCargo(Adeudo $cargo, callable $accion): void
    {
        $accion();
        $this->assertModelExists($cargo);
    }
}
