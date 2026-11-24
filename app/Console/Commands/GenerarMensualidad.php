<?php

namespace App\Console\Commands;

use App\Services\Cobranza\MensualidadService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class GenerarMensualidad extends Command
{
    protected $signature = 'cobranza:generar-mensualidad
                            {--mes= : Mes a generar (Y-m). Por defecto el mes actual}
                            {--simular : Muestra lo que se generaría sin guardar}';

    protected $description = 'Genera el cargo de colegiatura del mes para todos los alumnos ACTIVOS';

    public function handle(MensualidadService $service): int
    {
        $mes = $this->option('mes')
            ? Carbon::createFromFormat('Y-m', $this->option('mes'))->startOfMonth()
            : today()->startOfMonth();

        $r = $service->generar($mes, (bool) $this->option('simular'));

        $this->info(sprintf(
            '%s%s → generados: %d | omitidos (ya existían / sin plan): %d',
            $this->option('simular') ? '[SIMULACIÓN] ' : '',
            $r['concepto'],
            $r['generados'],
            $r['omitidos'],
        ));

        return self::SUCCESS;
    }
}
