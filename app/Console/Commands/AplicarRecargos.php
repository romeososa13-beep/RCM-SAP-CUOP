<?php

namespace App\Console\Commands;

use App\Models\Bitacora;
use App\Services\Cobranza\RecargoService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class AplicarRecargos extends Command
{
    protected $signature = 'cobranza:aplicar-recargos
                            {--fecha= : Fecha de corte (Y-m-d). Por defecto hoy}
                            {--simular : Muestra lo que se aplicaría sin guardar}';

    protected $description = 'Aplica recargo a colegiaturas ($ fijo del plan) e inglés (% de su costo) vencidos';

    public function handle(RecargoService $service): int
    {
        $fecha = $this->option('fecha') ? Carbon::parse($this->option('fecha')) : today();

        $r = $service->aplicar($fecha, (bool) $this->option('simular'));

        if (! $this->option('simular') && $r['aplicados'] > 0) {
            Bitacora::registrar('proceso', 'proceso', 'cobranza:aplicar-recargos',
                "Recargos aplicados: {$r['aplicados']} por $".number_format($r['monto'], 2)." (corte {$fecha->toDateString()})",
                ['por_tipo' => ['antes' => null, 'despues' => $r['por_tipo']]]);
        }

        $this->info(sprintf(
            '%sRecargos aplicados: %d (%s) | Monto total: $%s | Corte: %s',
            $this->option('simular') ? '[SIMULACIÓN] ' : '',
            $r['aplicados'],
            collect($r['por_tipo'])->map(fn ($n, $t) => "{$t}: {$n}")->implode(', ') ?: 'ninguno',
            number_format($r['monto'], 2),
            $fecha->toDateString(),
        ));

        return self::SUCCESS;
    }
}
