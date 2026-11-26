<?php

namespace Tests\Feature;

use App\Models\Alumno;
use App\Models\Carrera;
use App\Services\Cobranza\MensualidadService;
use App\Services\Padron\ImportarAlumnosService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PreinscritoTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_preinscrito_no_genera_mensualidad_ni_bloqueo_y_no_exige_plan(): void
    {
        Alumno::find('CUOP26002')->update(['estatus' => 'PREINSCRITO']);

        app(MensualidadService::class)->generar(Carbon::parse('2027-01-01'));
        $this->assertFalse(Alumno::find('CUOP26002')->adeudos()->where('concepto', 'MENSUALIDAD ENE 2027')->exists());
        $this->assertFalse(Alumno::conBloqueo(Carbon::parse('2026-11-24'))->whereKey('CUOP26002')->exists());

        $fila = collect(['matricula' => 'CUOP-LOR09300', 'nombre_completo' => 'NUEVO PREINSCRITO', 'sede' => 'CUOP-LOR',
            'carrera' => Carrera::first()->nombre, 'grupo' => '27-2 1A', 'plan_pago' => '', 'estatus' => 'PREINSCRITO']);
        $s = app(ImportarAlumnosService::class);
        $a = $s->analizarFilas(collect([$fila]));
        $this->assertCount(1, $a['crear']);
        $s->importar($a);
        $this->assertSame('Preinscrito', Alumno::find('CUOP-LOR09300')->estatus->etiqueta());
    }
}
