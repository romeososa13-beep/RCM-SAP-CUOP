<?php

use Illuminate\Support\Facades\Schedule;

/*
| Programación de cobranza. En el servidor basta un cron:
|   * * * * * cd /ruta/erp-cuop && php artisan schedule:run >> /dev/null 2>&1
*/

// Día 1 de cada mes: cargo de colegiatura para alumnos ACTIVOS.
Schedule::command('cobranza:generar-mensualidad')
    ->monthlyOn(1, '00:05')
    ->withoutOverlapping()
    ->onOneServer();

// Diario: recargos a adeudos vencidos.
Schedule::command('cobranza:aplicar-recargos')
    ->dailyAt('00:15')
    ->withoutOverlapping()
    ->onOneServer();

// Diario 9:00: recordatorio (día 8), aviso de recargo (día 11) o de bloqueo (día 16) por WhatsApp.
// No envía nada mientras WHATSAPP_RECORDATORIOS=false.
Schedule::command('whatsapp:recordatorios')
    ->dailyAt('09:00')
    ->withoutOverlapping()
    ->onOneServer();
