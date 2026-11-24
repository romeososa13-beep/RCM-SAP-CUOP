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
