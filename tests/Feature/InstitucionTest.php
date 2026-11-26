<?php

namespace Tests\Feature;

use App\Livewire\Admin\Institucion;
use App\Mail\ReciboPagoMail;
use App\Models\Ajuste;
use App\Models\Pago;
use App\Models\User;
use App\Services\Cobranza\ReciboPdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class InstitucionTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    /** Texto de un PDF generado por DomPDF (sin compresión de flujos no se puede leer directo). */
    private function textoPdf(string $pdf): string
    {
        $archivo = tempnam(sys_get_temp_dir(), 'pdf');
        file_put_contents($archivo, $pdf);
        $texto = (string) shell_exec('pdftotext '.escapeshellarg($archivo).' - 2>/dev/null');
        @unlink($archivo);

        return $texto;
    }

    public function test_datos_por_defecto_y_guardado_con_validacion_de_rfc(): void
    {
        Storage::fake('local');
        $this->assertSame('CENTRO UNIVERSITARIO', Ajuste::institucion()['institucion_nombre']);

        $this->actingAs(User::where('email', 'admin@cuop.test')->first());
        Livewire::test(Institucion::class)
            ->set('datos.institucion_nombre', 'CENTRO UNIVERSITARIO DE PRUEBA')
            ->set('datos.institucion_rfc', 'no-valido')->call('guardar')->assertHasErrors('datos.institucion_rfc')
            ->set('datos.institucion_rfc', 'cup010101ab1')
            ->set('datos.institucion_direccion', 'Av. Siempre Viva 123, Aguascalientes')
            ->set('logo', UploadedFile::fake()->image('logo.png', 200, 80))
            ->call('guardar')->assertHasNoErrors();

        $i = Ajuste::institucion();
        $this->assertSame('CUP010101AB1', $i['institucion_rfc']);
        Storage::disk('local')->assertExists('institucion/logo.png');
        $this->assertStringStartsWith('data:image/png;base64,', Ajuste::logoDataUri());

        $pago = Pago::first();
        $texto = $this->textoPdf(app(ReciboPdf::class)->generar($pago, 'media-carta')->output());
        $this->assertStringContainsString('CENTRO UNIVERSITARIO DE PRUEBA', $texto);
        $this->assertStringContainsString('RFC: CUP010101AB1', $texto);
        $this->assertStringContainsString('Av. Siempre Viva 123', $texto);

        $this->assertStringContainsString('CENTRO UNIVERSITARIO DE PRUEBA', (new ReciboPagoMail($pago))->envelope()->subject);
        $this->get('/admin/institucion/muestra')->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_solo_administrador(): void
    {
        $this->actingAs(User::where('email', 'caja@cuop.test')->first())->get('/admin/institucion')->assertForbidden();
    }
}
