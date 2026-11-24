<?php

namespace App\Console\Commands;

use App\Services\Migracion\MigrarSaldosService;
use Illuminate\Console\Command;

class MigrarSaldos extends Command
{
    protected $signature = 'cobranza:migrar-saldos
                            {archivo : Excel/CSV con MATRICULA, CONCEPTO, TIPO, VENCIMIENTO, MONTO, RECARGO, PAGADO_PREVIO, EXENTO_RECARGO}
                            {--simular : Solo valida y muestra el resumen, sin guardar}';

    protected $description = 'Carga los saldos pendientes del sistema anterior como adeudos (una sola vez, idempotente)';

    public function handle(MigrarSaldosService $service): int
    {
        if (! is_file($this->argument('archivo'))) {
            $this->error('No existe el archivo '.$this->argument('archivo'));

            return self::FAILURE;
        }

        $a = $service->analizarArchivo($this->argument('archivo'));

        $this->table(['Filas', 'A migrar', 'de ellas ya liquidadas', 'Ya existían (se omiten)', 'Con error', 'Saldo a migrar'], [[
            $a['total'], count($a['validas']), $a['liquidadas'], count($a['omitidas']), count($a['errores']), '$'.number_format($a['saldo'], 2),
        ]]);

        if ($a['errores']) {
            $this->warn('Filas con error (no se migran):');
            $this->table(['Fila', 'Matrícula', 'Concepto', 'Motivo'], collect($a['errores'])->take(50)
                ->map(fn ($e) => [$e['fila'], $e['matricula'], $e['concepto'], implode(' ', $e['mensajes'])])->all());
            if (count($a['errores']) > 50) {
                $this->line('… y '.(count($a['errores']) - 50).' más.');
            }
        }

        if ($this->option('simular')) {
            $this->info('[SIMULACIÓN] No se guardó nada.');

            return self::SUCCESS;
        }

        if (! $a['validas']) {
            $this->info('Nada que migrar.');

            return self::SUCCESS;
        }

        if ($this->input->isInteractive() && ! $this->confirm('¿Migrar '.count($a['validas']).' saldos por $'.number_format($a['saldo'], 2).'?', true)) {
            return self::FAILURE;
        }

        $n = $service->migrar($a);
        $this->info("Migrados {$n} saldos (origen MIGRACION).");

        return self::SUCCESS;
    }
}
