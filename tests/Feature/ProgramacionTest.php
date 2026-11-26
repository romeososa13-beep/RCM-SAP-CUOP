<?php

namespace Tests\Feature;

use App\Models\Adeudo;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** La programación debe funcionar con el equipo encendido solo en horario laboral. */
class ProgramacionTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Laravel toma la hora al REGISTRAR las tareas (en producción, cada minuto un proceso nuevo),
     * así que se vuelven a registrar después de fijar la hora simulada.
     */
    private function tocaAhora(string $comando): bool
    {
        $this->app->instance(Schedule::class, $agenda = new Schedule(config('app.timezone')));
        \Illuminate\Support\Facades\Schedule::clearResolvedInstance(Schedule::class);
        require base_path('routes/console.php');

        $evento = collect($agenda->events())->first(fn ($e) => str_contains($e->command, $comando));

        return $evento->isDue($this->app) && $evento->filtersPass($this->app);
    }

    public function test_mensualidad_del_dia_1_al_10_en_horario_laboral_y_sin_duplicar(): void
    {
        Carbon::setTestNow('2026-12-03 11:00');   // el equipo no se prendió el 1 ni el 2
        $this->assertTrue($this->tocaAhora('cobranza:generar-mensualidad'));
        $this->artisan('cobranza:generar-mensualidad')->assertSuccessful();
        $antes = Adeudo::where('concepto', 'MENSUALIDAD DIC 2026')->count();
        $this->artisan('cobranza:generar-mensualidad')->assertSuccessful();   // la hora siguiente
        $this->assertSame($antes, Adeudo::where('concepto', 'MENSUALIDAD DIC 2026')->count());

        Carbon::setTestNow('2026-12-03 23:00');
        $this->assertFalse($this->tocaAhora('cobranza:generar-mensualidad'), 'Fuera de horario');

        Carbon::setTestNow('2026-11-26 11:00');   // a fin de mes no genera cargos atrasados
        $this->assertFalse($this->tocaAhora('cobranza:generar-mensualidad'));
    }

    public function test_recargos_cada_hora_del_dia(): void
    {
        Carbon::setTestNow('2026-12-14 08:00');
        $this->assertTrue($this->tocaAhora('cobranza:aplicar-recargos'));
        Carbon::setTestNow('2026-12-14 03:00');
        $this->assertFalse($this->tocaAhora('cobranza:aplicar-recargos'));
    }
}
