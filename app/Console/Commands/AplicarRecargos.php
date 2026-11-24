<?php

namespace App\Console\Commands;

use App\Services\Cobranza\RecargoService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class AplicarRecargos extends Command
{
    protected $signature = 'cobranza:aplicar-recargos
                            {--fecha= : Fecha de corte (Y-m-d). Por defecto hoy}
                            {--simular : Muestra lo que se aplicaría sin guardar}';

    protected $description = 'Aplica el recargo del plan de pago a los adeudos vencidos sin recargo';

    public function handle(RecargoService $service): int
    {
        $fecha = $this->option('fecha') ? Carbon::parse($this->option('fecha')) : today();

        $r = $service->aplicar($fecha, (bool) $this->option('simular'));

        $this->info(sprintf(
            '%sRecargos aplicados: %d | Monto total: $%s | Corte: %s',
            $this->option('simular') ? '[SIMULACIÓN] ' : '',
            $r['aplicados'],
            number_format($r['monto'], 2),
            $fecha->toDateString(),
        ));

        return self::SUCCESS;
    }
}
