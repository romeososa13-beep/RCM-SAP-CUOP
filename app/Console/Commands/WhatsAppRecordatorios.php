<?php

namespace App\Console\Commands;

use App\Models\Alumno;
use App\Models\Bitacora;
use App\Services\WhatsApp\Mensajeria;
use App\Services\WhatsApp\Plantillas;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class WhatsAppRecordatorios extends Command
{
    protected $signature = 'whatsapp:recordatorios
                            {--fecha= : Simula otro día (Y-m-d)}
                            {--forzar : Envía aunque WHATSAPP_RECORDATORIOS esté apagado}
                            {--simular : Solo cuenta a quién se enviaría}';

    protected $description = 'Envía por WhatsApp el recordatorio (día 8), aviso de recargo (día 11) o de bloqueo (día 16)';

    public function handle(Mensajeria $mensajeria): int
    {
        if (! config('cobranza.whatsapp_recordatorios') && ! $this->option('forzar') && ! $this->option('simular')) {
            $this->info('Recordatorios por WhatsApp apagados (WHATSAPP_RECORDATORIOS=false).');

            return self::SUCCESS;
        }

        $hoy = $this->option('fecha') ? Carbon::parse($this->option('fecha')) : today();
        $clave = array_search($hoy->day, config('cobranza.whatsapp_dias'), true);
        if ($clave === false) {
            $this->info("Hoy ({$hoy->format('d/m')}) no toca ningún aviso.");

            return self::SUCCESS;
        }
        if ($this->option('fecha')) {
            Carbon::setTestNow($hoy->copy()->setTime(9, 0));
        }

        $enviados = 0;
        $omitidos = [];
        Alumno::activos()->whereNull('whatsapp_baja_at')
            ->where(fn ($q) => $q->whereNotNull('celular')->orWhereNotNull('tel_tutor'))
            ->chunkById(200, function ($alumnos) use ($mensajeria, $clave, &$enviados, &$omitidos) {
                foreach ($alumnos as $a) {
                    if ($this->option('simular')) {
                        Plantillas::variables($clave, $a) !== null ? $enviados++ : null;

                        continue;
                    }
                    [$m, $motivo] = $mensajeria->plantilla($a, $clave);
                    $m ? $enviados++ : ($omitidos[$motivo] = ($omitidos[$motivo] ?? 0) + 1);
                }
            }, 'matricula');

        $this->info(($this->option('simular') ? '[SIMULACIÓN] ' : '')."{$clave}: {$enviados} mensajes en cola.");
        foreach ($omitidos as $motivo => $n) {
            $this->line("  omitidos ({$motivo}): {$n}");
        }
        if (! $this->option('simular') && $enviados > 0) {
            Bitacora::registrar('proceso', 'proceso', 'whatsapp:recordatorios', "WhatsApp {$clave}: {$enviados} mensajes en cola");
        }

        return self::SUCCESS;
    }
}
