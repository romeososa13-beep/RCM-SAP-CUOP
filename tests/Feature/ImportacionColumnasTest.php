<?php

namespace Tests\Feature;

use App\Livewire\Alumnos\PadronAlumnos;
use App\Models\Alumno;
use App\Models\User;
use App\Services\Padron\ArchivoPadronInvalido;
use App\Services\Padron\ImportarAlumnosService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class ImportacionColumnasTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_rechaza_el_archivo_de_saldos_y_columnas_faltantes(): void
    {
        $s = app(ImportarAlumnosService::class);
        $saldos = collect([collect(['matricula' => 'CUOP26003', 'concepto' => 'MENSUALIDAD NOV 2026', 'tipo' => 'COLEGIATURA',
            'vencimiento' => '2026-11-10', 'monto' => 1850, 'recargo' => 0, 'pagado_previo' => 0, 'exento_recargo' => 'NO'])]);
        try {
            $s->analizarFilas($saldos);
            $this->fail('Debió rechazar el archivo de saldos');
        } catch (ArchivoPadronInvalido $e) {
            $this->assertStringContainsString('archivo de SALDOS', $e->getMessage());
        }

        $this->expectException(ArchivoPadronInvalido::class);
        $this->expectExceptionMessage('PLAN_PAGO, ESTATUS');
        $s->analizarFilas(collect([collect(['matricula' => 'CUOP26003', 'nombre_completo' => 'X', 'sede' => 'CUOP-LOR', 'carrera' => 'X', 'grupo' => 'X'])]));
    }

    public function test_estatus_vacio_no_reactiva_a_un_alumno_de_baja(): void
    {
        $fila = fn ($estatus) => collect(['matricula' => 'CUOP26005', 'nombre_completo' => '', 'sede' => '', 'carrera' => '',
            'grupo' => '27-1 NUEVO-1A', 'plan_pago' => '', 'estatus' => $estatus]);
        $a = app(ImportarAlumnosService::class)->analizarFilas(collect([$fila('')]));

        $this->assertSame([], $a['errores']);
        $this->assertSame(['grupo' => '27-1 NUEVO-1A'], $a['actualizar'][0]['cambios'], 'solo cambia el grupo; sigue de baja');
    }

    public function test_la_pantalla_muestra_el_motivo(): void
    {
        $this->actingAs(User::where('email', 'escolar@cuop.test')->first());
        $csv = UploadedFile::fake()->createWithContent('saldos.csv',
            "MATRICULA,CONCEPTO,TIPO,VENCIMIENTO,MONTO,RECARGO,PAGADO_PREVIO,EXENTO_RECARGO\nCUOP26003,MENSUALIDAD NOV 2026,COLEGIATURA,2026-11-10,1850,0,0,NO\n");

        Livewire::test(PadronAlumnos::class)->set('archivo', $csv)
            ->assertHasErrors('archivo')->assertSee('archivo de SALDOS')->assertSet('preview', null);
        $this->assertSame('BAJA_TEMPORAL', Alumno::find('CUOP26005')->estatus->value);
    }
}
