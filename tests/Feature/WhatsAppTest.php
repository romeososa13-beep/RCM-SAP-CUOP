<?php

namespace Tests\Feature;

use App\Livewire\Caja\EstadoCuenta;
use App\Livewire\Reportes\BloqueoPlataformas;
use App\Livewire\WhatsApp\Bandeja;
use App\Models\Adeudo;
use App\Models\Alumno;
use App\Models\User;
use App\Models\WhatsappMensaje;
use App\Services\Cobranza\RecargoService;
use App\Services\WhatsApp\CloudApiClient;
use App\Services\WhatsApp\Mensajeria;
use App\Services\WhatsApp\Telefono;
use App\Services\WhatsApp\WhatsAppClient;
use App\Services\WhatsApp\WhatsAppException;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class WhatsAppTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-24 10:00');
        app(RecargoService::class)->aplicar(today());
        Alumno::find('CUOP26002')->update(['celular' => '4961112233']);
        Alumno::find('CUOP26001')->update(['celular' => '4962223344']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Mensaje entrante tal como lo manda Meta. */
    private function webhook(string $de, string $texto, ?string $id = null, array $headers = [])
    {
        return $this->postJson('/webhooks/whatsapp', ['object' => 'whatsapp_business_account', 'entry' => [['changes' => [['value' => [
            'messages' => [['from' => $de, 'id' => $id ?? 'wamid.'.uniqid(), 'type' => 'text', 'text' => ['body' => $texto]]],
        ]]]]]], $headers);
    }

    private function ultimaRespuesta(string $tel): ?WhatsappMensaje
    {
        return WhatsappMensaje::where('telefono', $tel)->where('direccion', 'saliente')->latest('id')->first();
    }

    public function test_telefonos_de_mexico(): void
    {
        $this->assertSame('4961112233', Telefono::clave('5214961112233'));
        $this->assertSame('4961112233', Telefono::clave('+52 (496) 111-2233'));
        $this->assertSame('524961112233', Telefono::internacional('496 111 2233'));
        $this->assertNull(Telefono::clave('12345'));
    }

    public function test_plantilla_con_reglas_de_envio(): void
    {
        $m = app(Mensajeria::class);
        $jose = Alumno::find('CUOP26002');

        [$msg, $motivo] = $m->plantilla($jose, 'aviso_bloqueo');
        $this->assertNull($motivo);
        $this->assertSame('enviado', $msg->fresh()->estado);           // cola sync + driver log
        $this->assertStringStartsWith('log.', $msg->fresh()->wa_id);
        $this->assertStringContainsString('José Luis', $msg->cuerpo);
        $this->assertStringContainsString('3,570.00', $msg->cuerpo);    // OCT $1,535 + NOV $2,035 (causas del bloqueo)

        $this->assertSame('Ya se le envió este aviso hoy.', $m->plantilla($jose, 'aviso_bloqueo')[1]);
        $this->assertSame('Sin celular registrado.', $m->plantilla(Alumno::find('CUOP26003'), 'aviso_bloqueo')[1]);
        $this->assertStringContainsString('No aplica', $m->plantilla(Alumno::find('CUOP26001'), 'recordatorio_pago')[1]);

        $jose->update(['whatsapp_baja_at' => now()]);
        $this->assertStringContainsString('BAJA', $m->plantilla($jose, 'aviso_recargo')[1]);
    }

    public function test_cliente_cloud_api_arma_la_peticion_y_reporta_errores(): void
    {
        Http::fake([
            'graph.facebook.com/v21.0/PHONE1/messages' => Http::sequence()
                ->push(['messages' => [['id' => 'wamid.OK']]])
                ->push(['error' => ['message' => '(#132001) Template name does not exist in the translation']], 404),
        ]);
        $c = new CloudApiClient('TOKEN', 'PHONE1', 'v21.0');

        $this->assertSame('wamid.OK', $c->enviarPlantilla('524961112233', 'aviso_bloqueo', 'es_MX', ['Ana', '100.00']));
        Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Bearer TOKEN')
            && $r['to'] === '524961112233' && $r['template']['name'] === 'aviso_bloqueo'
            && $r['template']['language']['code'] === 'es_MX'
            && $r['template']['components'][0]['parameters'][1] === ['type' => 'text', 'text' => '100.00']);

        $this->expectException(WhatsAppException::class);
        $this->expectExceptionMessage('Template name does not exist');
        $c->enviarTexto('524961112233', 'hola');
    }

    public function test_envio_fallido_queda_marcado(): void
    {
        $this->app->instance(WhatsAppClient::class, new class implements WhatsAppClient
        {
            public function enviarPlantilla(string $t, string $p, string $i, array $v): string
            {
                throw new WhatsAppException('Número no tiene WhatsApp');
            }

            public function enviarTexto(string $t, string $x): string
            {
                throw new WhatsAppException('x');
            }
        });

        try {
            app(Mensajeria::class)->plantilla(Alumno::find('CUOP26002'), 'aviso_bloqueo');
        } catch (WhatsAppException) {
        } // con cola «sync» la excepción sube; en producción la procesa el worker

        $m = WhatsappMensaje::latest('id')->first();
        $this->assertSame('fallido', $m->estado);
        $this->assertStringContainsString('no tiene WhatsApp', $m->error);
    }

    public function test_webhook_verificacion_y_firma(): void
    {
        config(['services.whatsapp.verify_token' => 'secreto-verificacion', 'services.whatsapp.app_secret' => 'app-secret']);

        $this->get('/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=secreto-verificacion&hub.challenge=12345')
            ->assertOk()->assertSee('12345');
        $this->get('/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=otro&hub.challenge=1')->assertForbidden();

        $this->webhook('5214961112233', 'hola', 'wamid.X', ['X-Hub-Signature-256' => 'sha256=falsa'])->assertForbidden();
        $this->assertSame(0, WhatsappMensaje::where('direccion', 'entrante')->count());

        $cuerpo = json_encode(['entry' => [['changes' => [['value' => ['messages' => [
            ['from' => '5214961112233', 'id' => 'wamid.F', 'type' => 'text', 'text' => ['body' => 'hola']]]]]]]]]);
        $this->call('POST', '/webhooks/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $cuerpo, 'app-secret'),
        ], $cuerpo)->assertOk();
        $this->assertSame(1, WhatsappMensaje::where('wa_id', 'wamid.F')->count());

        // Con la API real, sin app_secret configurado se rechaza todo
        config(['services.whatsapp.app_secret' => null, 'services.whatsapp.driver' => 'cloud']);
        $this->webhook('5214961112233', 'hola')->assertForbidden();
    }

    public function test_el_webhook_no_exige_token_csrf_pero_el_resto_si(): void
    {
        // En pruebas Laravel omite la verificación CSRF; aquí se ejecuta el middleware real.
        $csrf = new class($this->app, $this->app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests()
            {
                return false;
            }
        };
        $siguiente = fn () => response('ok');
        $peticion = function (string $uri) {
            $r = \Illuminate\Http\Request::create($uri, 'POST');
            $r->setLaravelSession($this->app['session.store']);

            return $r;
        };

        $this->assertSame('ok', $csrf->handle($peticion('/webhooks/whatsapp'), $siguiente)->getContent());

        $this->expectException(TokenMismatchException::class);
        $csrf->handle($peticion('/logout'), $siguiente);
    }

    public function test_estados_de_entrega_no_retroceden(): void
    {
        [$m] = app(Mensajeria::class)->plantilla(Alumno::find('CUOP26002'), 'aviso_bloqueo');
        $estado = fn ($s) => $this->postJson('/webhooks/whatsapp', ['entry' => [['changes' => [['value' => [
            'statuses' => [['id' => $m->fresh()->wa_id, 'status' => $s]]]]]]]]);

        $estado('read');
        $estado('delivered');  // llega tarde
        $this->assertSame('leido', $m->fresh()->estado);
    }

    public function test_chatbot_saldo_fechas_asesor_y_numero_desconocido(): void
    {
        $this->webhook('5214961112233', 'Hola');
        $this->assertStringContainsString('Hola José Luis', $this->ultimaRespuesta('4961112233')->cuerpo);
        $this->assertSame('bot', $this->ultimaRespuesta('4961112233')->tipo);

        $this->webhook('5214961112233', '1');
        $saldo = $this->ultimaRespuesta('4961112233')->cuerpo;
        $this->assertStringContainsString('CUOP26002', $saldo);
        $this->assertStringContainsString('MENSUALIDAD OCT 2026', $saldo);
        $this->assertStringContainsString('bloqueo de plataformas', $saldo);

        $this->webhook('5214961112233', '2');
        $this->assertStringContainsString('Desde el día 16', $this->ultimaRespuesta('4961112233')->cuerpo);

        $this->webhook('5214961112233', 'tengo una duda con mi beca');
        $this->assertTrue(WhatsappMensaje::where('telefono', '4961112233')->where('direccion', 'entrante')->latest('id')->first()->requiere_atencion);

        // Un número que no está en el padrón no recibe datos de nadie
        $this->webhook('5219999999999', '1');
        $r = $this->ultimaRespuesta('9999999999')->cuerpo;
        $this->assertStringContainsString('no está registrado', $r);
        $this->assertStringNotContainsString('$', $r);

        // Duplicados de Meta se ignoran
        $this->webhook('5214961112233', 'hola', 'wamid.DUP');
        $this->webhook('5214961112233', 'hola', 'wamid.DUP');
        $this->assertSame(1, WhatsappMensaje::where('wa_id', 'wamid.DUP')->count());
    }

    public function test_baja_y_alta_de_avisos(): void
    {
        $this->webhook('5214961112233', 'BAJA');
        $this->assertNotNull(Alumno::find('CUOP26002')->whatsapp_baja_at);
        $this->assertStringContainsString('ya no te enviaremos', $this->ultimaRespuesta('4961112233')->cuerpo);
        $this->assertNotNull(app(Mensajeria::class)->plantilla(Alumno::find('CUOP26002'), 'aviso_bloqueo')[1]);

        $this->webhook('5214961112233', 'alta');
        $this->assertNull(Alumno::find('CUOP26002')->whatsapp_baja_at);
    }

    public function test_bandeja_respuesta_humana_silencia_al_bot_y_respeta_ventana_de_24h(): void
    {
        $this->actingAs(User::where('email', 'caja@cuop.test')->first());
        $this->webhook('5214961112233', 'necesito hablar con alguien');

        Livewire::test(Bandeja::class)->assertSee('MARTÍNEZ LÓPEZ JOSÉ LUIS')
            ->call('abrir', '4961112233')->set('respuesta', 'Hola José, te ayudo')->call('responder')->assertHasNoErrors();
        $this->assertSame('texto', $this->ultimaRespuesta('4961112233')->tipo);
        $this->assertFalse(WhatsappMensaje::where('telefono', '4961112233')->where('requiere_atencion', true)->exists());

        $antes = WhatsappMensaje::count();
        $this->webhook('5214961112233', 'gracias');      // el bot no contesta: está atendiendo una persona
        $this->assertSame($antes + 1, WhatsappMensaje::count());

        Carbon::setTestNow(now()->addHours(25));
        Livewire::test(Bandeja::class)->call('abrir', '4961112233')->set('respuesta', 'seguimiento')
            ->call('responder')->assertHasErrors('respuesta');

        $this->actingAs(User::where('email', 'escolar@cuop.test')->first())->get('/whatsapp')->assertForbidden();
    }

    public function test_recordatorios_automaticos_y_aviso_masivo(): void
    {
        $this->artisan('whatsapp:recordatorios', ['--fecha' => '2026-12-08'])->expectsOutputToContain('apagados')->assertSuccessful();
        $this->assertSame(0, WhatsappMensaje::count());

        $this->artisan('whatsapp:recordatorios', ['--fecha' => '2026-12-08', '--forzar' => true])
            ->expectsOutputToContain('recordatorio_pago: 2 mensajes')->assertSuccessful();
        $this->assertStringContainsString('vence el 10/12/2026', WhatsappMensaje::where('matricula', 'CUOP26002')->first()->cuerpo);

        $this->artisan('whatsapp:recordatorios', ['--fecha' => '2026-12-09', '--forzar' => true])->expectsOutputToContain('no toca')->assertSuccessful();

        Carbon::setTestNow('2026-11-24 10:00');
        $this->actingAs(User::where('email', 'caja@cuop.test')->first());
        Livewire::test(BloqueoPlataformas::class)->call('avisarWhatsApp')->assertDispatched('toast', tipo: 'success');
        $this->assertSame(2, WhatsappMensaje::where('plantilla', 'aviso_bloqueo')->count()); // 26003/26004 sin celular se omiten
    }

    public function test_envio_desde_estado_de_cuenta_y_confirmacion_de_pago(): void
    {
        $this->actingAs(User::where('email', 'caja@cuop.test')->first());
        Livewire::test(EstadoCuenta::class)->call('seleccionarAlumno', 'CUOP26002')
            ->call('abrirModalWhatsApp')->assertSet('plantillaWa', 'aviso_bloqueo')->assertSee('tu acceso a plataformas está bloqueado')
            ->call('enviarWa')->assertDispatched('toast', tipo: 'success');

        $adeudo = Adeudo::where('matricula', 'CUOP26002')->where('concepto', 'MENSUALIDAD DIC 2026')->first();
        Livewire::test(EstadoCuenta::class)->call('seleccionarAlumno', 'CUOP26002')
            ->call('abrirModalCobro', $adeudo->id)->set('enviarWhatsApp', true)->call('cobrar');
        $this->assertStringContainsString('recibimos tu pago de $1,850.00', WhatsappMensaje::where('plantilla', 'pago_recibido')->first()->cuerpo);
    }
}
