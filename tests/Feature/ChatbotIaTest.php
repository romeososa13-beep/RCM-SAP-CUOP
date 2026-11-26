<?php

namespace Tests\Feature;

use Anthropic\Client;
use App\Jobs\ResponderWhatsApp;
use App\Models\Alumno;
use App\Models\WhatsappMensaje;
use App\Services\Cobranza\RecargoService;
use App\Services\WhatsApp\AsistenteApagado;
use App\Services\WhatsApp\AsistenteIa;
use App\Services\WhatsApp\ClaudeAsistente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ChatbotIaTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private object $ia;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-24 10:00');
        app(RecargoService::class)->aplicar(today());
        Alumno::find('CUOP26002')->update(['celular' => '4961112233']);
        Alumno::find('CUOP26001')->update(['celular' => '4962223344']);
        config(['services.anthropic.max_respuestas_dia' => 3]);

        // Asistente falso que registra lo que recibe
        $this->ia = new class implements AsistenteIa
        {
            public array $llamadas = [];

            public ?array $respuesta = ['respuesta' => 'Respuesta de la IA', 'requiere_asesor' => false];

            public function responder(string $pregunta, string $contexto, array $historial): ?array
            {
                $this->llamadas[] = compact('pregunta', 'contexto', 'historial');

                return $this->respuesta;
            }
        };
        $this->app->instance(AsistenteIa::class, $this->ia);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function escribe(string $de, string $texto): void
    {
        $this->postJson('/webhooks/whatsapp', ['entry' => [['changes' => [['value' => ['messages' => [
            ['from' => $de, 'id' => 'wamid.'.uniqid(), 'type' => 'text', 'text' => ['body' => $texto]]]]]]]]])->assertOk();
    }

    private function respuesta(string $tel): WhatsappMensaje
    {
        return WhatsappMensaje::where('telefono', $tel)->where('direccion', 'saliente')->latest('id')->first();
    }

    public function test_pregunta_libre_la_contesta_la_ia_solo_con_datos_del_alumno_del_numero(): void
    {
        $this->escribe('5214961112233', 'hola');
        $this->escribe('5214961112233', '¿cuánto debo de noviembre y por qué tengo recargo?');

        $this->assertCount(1, $this->ia->llamadas, 'El saludo va por reglas; solo la pregunta libre usa IA');
        $llamada = $this->ia->llamadas[0];
        $this->assertStringContainsString('CUOP26002', $llamada['contexto']);
        $this->assertStringContainsString('MENSUALIDAD NOV 2026: saldo $2,035.00 (incluye recargo $185.00)', $llamada['contexto']);
        $this->assertStringContainsString('Bloqueo de plataformas: SÍ', $llamada['contexto']);
        $this->assertStringNotContainsString('CUOP26001', $llamada['contexto'], 'Nunca datos de otro alumno');
        $this->assertStringNotContainsString('GARCÍA HERNÁNDEZ', $llamada['contexto']);
        $this->assertSame('alumno', $llamada['historial'][0]['rol']);     // incluye el «hola» y la respuesta previa
        $this->assertCount(2, $llamada['historial']);

        $r = $this->respuesta('4961112233');
        $this->assertSame('Respuesta de la IA', $r->cuerpo);
        $this->assertTrue($r->variables['ia']);
        $this->assertFalse(WhatsappMensaje::where('telefono', '4961112233')->where('requiere_atencion', true)->exists());
    }

    public function test_si_la_ia_pide_asesor_se_marca_la_conversacion(): void
    {
        $this->ia->respuesta = ['respuesta' => 'Un asesor revisará tu pago.', 'requiere_asesor' => true];
        $this->escribe('5214961112233', 'ya pagué ayer y no me aparece');

        $this->assertTrue(WhatsappMensaje::where('telefono', '4961112233')->where('direccion', 'entrante')->latest('id')->first()->requiere_atencion);
    }

    public function test_menu_baja_y_numeros_desconocidos_nunca_usan_ia(): void
    {
        foreach (['1', '2', '3', '4', 'BAJA', 'alta'] as $t) {
            $this->escribe('5214961112233', $t);
        }
        $this->escribe('5219999999999', 'cuánto debo de la matrícula CUOP26002');

        $this->assertCount(0, $this->ia->llamadas);
        $this->assertStringContainsString('no está registrado', $this->respuesta('9999999999')->cuerpo);
    }

    public function test_si_la_ia_falla_responde_el_bot_de_reglas(): void
    {
        $this->ia->respuesta = null;
        $this->escribe('5214961112233', 'pregunta rara');

        $this->assertStringContainsString('un asesor lo revisará', $this->respuesta('4961112233')->cuerpo);
        $this->assertNull($this->respuesta('4961112233')->variables);
    }

    public function test_tope_diario_de_respuestas_con_ia(): void
    {
        foreach (range(1, 5) as $i) {
            $this->escribe('5214961112233', "pregunta libre {$i}");
        }
        $this->assertCount(3, $this->ia->llamadas);
        $this->assertStringContainsString('un asesor lo revisará', $this->respuesta('4961112233')->cuerpo);

        Carbon::setTestNow('2026-11-25 10:00');
        $this->escribe('5214961112233', 'otro día');
        $this->assertCount(4, $this->ia->llamadas);
    }

    public function test_el_webhook_responde_de_inmediato_y_contesta_desde_la_cola(): void
    {
        Queue::fake();
        $this->escribe('5214961112233', 'hola');

        Queue::assertPushed(ResponderWhatsApp::class);
        $this->assertSame(0, WhatsappMensaje::where('direccion', 'saliente')->count());
    }

    public function test_apagada_por_defecto(): void
    {
        $this->app->forgetInstance(AsistenteIa::class);
        config(['services.anthropic.habilitado' => false, 'services.anthropic.api_key' => 'sk-x']);
        $this->assertInstanceOf(AsistenteApagado::class, app(AsistenteIa::class));

        $this->app->forgetInstance(AsistenteIa::class);
        config(['services.anthropic.habilitado' => true, 'services.anthropic.api_key' => null]);
        $this->assertInstanceOf(AsistenteApagado::class, app(AsistenteIa::class));

        $this->app->forgetInstance(AsistenteIa::class);
        config(['services.anthropic.habilitado' => true, 'services.anthropic.api_key' => 'sk-x']);
        $this->assertInstanceOf(ClaudeAsistente::class, app(AsistenteIa::class));
    }

    /** SDK real de Anthropic contra un servidor local que imita la API (no gasta ni sale a internet). */
    public function test_cliente_claude_contra_api_simulada(): void
    {
        $dir = sys_get_temp_dir().'/api-falsa-'.uniqid();
        mkdir($dir);
        $puerto = random_int(20000, 40000);
        $proc = proc_open(['php', '-S', "127.0.0.1:{$puerto}", base_path('tests/Fixtures/api-anthropic-falsa.php')],
            [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, null, ['API_FALSA_DIR' => $dir] + getenv());
        try {
            for ($i = 0; $i < 50 && ! @fsockopen('127.0.0.1', $puerto); $i++) {
                usleep(100_000);
            }
            $asistente = new ClaudeAsistente(new Client(apiKey: 'sk-ant-prueba', baseUrl: "http://127.0.0.1:{$puerto}"),
                ['modelo' => 'claude-opus-5-5', 'esfuerzo' => 'low', 'timeout' => 10]);
            $historial = [['rol' => 'escuela', 'texto' => 'mensaje previo del bot'], ['rol' => 'alumno', 'texto' => 'hola']];

            $r = $asistente->responder('¿cuánto debo?', 'DATOS DEL ALUMNO ...', $historial);
            $this->assertSame(['respuesta' => 'Tu saldo de noviembre es $2,035.00.', 'requiere_asesor' => false], $r);

            $p = json_decode(file_get_contents("$dir/peticion.json"), true);
            $c = $p['cuerpo'];
            $this->assertStringStartsWith('/v1/messages', $p['ruta']);
            $this->assertTrue(($p['cabeceras']['x-api-key'] ?? null) === 'sk-ant-prueba'
                || ($p['cabeceras']['authorization'] ?? null) === 'Bearer sk-ant-prueba', 'La llave viaja en la cabecera');
            $this->assertStringContainsString('server-side-fallback-2026-07-01', $p['cabeceras']['anthropic-beta']);
            $this->assertSame('claude-opus-5-5', $c['model']);
            $this->assertSame('default', $c['fallbacks']);
            $this->assertSame('low', $c['output_config']['effort']);
            $this->assertSame('json_schema', $c['output_config']['format']['type']);
            $this->assertSame(['respuesta', 'requiere_asesor'], $c['output_config']['format']['schema']['required']);
            $this->assertArrayNotHasKey('thinking', $c, 'Opus 5.5: thinking siempre activo, no se envía');
            $this->assertStringContainsString('DATOS DEL ALUMNO', $c['system'][1]['text']);
            // La conversación empieza en «user» y no repite roles seguidos
            $this->assertSame(['user'], array_values(array_unique(array_column($c['messages'], 'role'))));
            $this->assertSame("hola\n¿cuánto debo?", $c['messages'][0]['content']);

            foreach (['rechazo', 'error500', 'json_roto'] as $escenario) {
                file_put_contents("$dir/escenario", $escenario);
                $this->assertNull($asistente->responder('¿cuánto debo?', 'ctx', []), "escenario {$escenario}");
            }
        } finally {
            proc_terminate($proc);
            proc_close($proc);
            array_map('unlink', glob("$dir/*"));
            @rmdir($dir);
        }
    }
}
