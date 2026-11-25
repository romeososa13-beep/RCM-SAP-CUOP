<?php

namespace Tests\Feature;

use App\Livewire\Caja\EstadoCuenta;
use App\Mail\ReciboPagoMail;
use App\Models\Adeudo;
use App\Models\Alumno;
use App\Models\Bitacora;
use App\Models\Pago;
use App\Models\User;
use App\Services\Padron\ImportarAlumnosService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class ReciboCorreoTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_al_cobrar_se_envia_el_recibo_si_el_alumno_tiene_correo(): void
    {
        Mail::fake();
        Alumno::find('CUOP26004')->update(['email' => 'luis@correo.test']);
        $this->actingAs(User::where('email', 'caja@cuop.test')->first());
        $adeudo = Adeudo::where('matricula', 'CUOP26004')->where('concepto', 'INSCRIPCIÓN 2027-1')->first();

        Livewire::test(EstadoCuenta::class)->call('seleccionarAlumno', 'CUOP26004')
            ->call('abrirModalCobro', $adeudo->id)->assertSet('enviarRecibo', true)->assertSee('luis@correo.test')
            ->call('cobrar')->assertHasNoErrors();

        $pago = Pago::latest('id')->first();
        Mail::assertQueued(ReciboPagoMail::class, fn ($m) => $m->hasTo('luis@correo.test') && $m->pago->is($pago));
        $this->assertSame('correo', Bitacora::latest('id')->first()->accion);

        // Reenviar desde el historial
        Livewire::test(EstadoCuenta::class)->call('seleccionarAlumno', 'CUOP26004')->call('reenviarRecibo', $pago->id);
        Mail::assertQueuedCount(2);
    }

    public function test_sin_correo_no_se_envia_y_se_puede_desmarcar(): void
    {
        Mail::fake();
        $this->actingAs(User::where('email', 'caja@cuop.test')->first());
        $adeudo = Adeudo::where('matricula', 'CUOP26002')->where('pagado', false)->first();

        Livewire::test(EstadoCuenta::class)->call('seleccionarAlumno', 'CUOP26002')
            ->call('abrirModalCobro', $adeudo->id)->assertSet('enviarRecibo', false)->call('cobrar');
        Mail::assertNothingQueued();

        Alumno::find('CUOP26002')->update(['email' => 'jl@correo.test']);
        Livewire::test(EstadoCuenta::class)->call('seleccionarAlumno', 'CUOP26002')
            ->call('abrirModalCobro', $adeudo->id)->set('enviarRecibo', false)->call('cobrar');
        Mail::assertNothingQueued();
    }

    public function test_el_correo_se_genera_con_el_pdf_adjunto(): void
    {
        $pago = Pago::first();
        $mail = new ReciboPagoMail($pago);

        $this->assertStringContainsString($pago->folio_pago, $mail->render());
        $adjunto = $mail->attachments()[0];
        $this->assertSame("recibo-{$pago->folio_pago}.pdf", $adjunto->as);
        $this->assertStringStartsWith('%PDF', $adjunto->attachWith(fn ($ruta) => null, fn ($datos) => $datos()));
    }

    public function test_correo_en_importacion_y_validacion(): void
    {
        $f = fn ($mat, $email) => collect(['matricula' => $mat, 'nombre_completo' => '', 'sede' => '', 'carrera' => '', 'grupo' => '',
            'plan_pago' => '', 'estatus' => 'ACTIVO', 'email' => $email]);
        $s = app(ImportarAlumnosService::class);
        $a = $s->analizarFilas(collect([$f('CUOP26003', ' Maria@Correo.TEST '), $f('CUOP26004', 'no-es-correo')]));

        $this->assertSame(['email' => 'maria@correo.test'], $a['actualizar'][0]['cambios']);
        $this->assertStringContainsString('EMAIL no es un correo válido', $a['errores'][0]['mensajes'][0]);
    }
}
