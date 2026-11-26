<?php

use Illuminate\Support\Facades\Schedule;

/*
| Programación de cobranza.
|
| Pensada para que funcione aunque el equipo solo esté encendido en horario laboral:
| las tareas se intentan cada hora de 7:00 a 21:00 y no duplican nada si corren varias
| veces (cada adeudo recibe un solo recargo; cada concepto del mes se genera una vez).
|
| En un servidor basta un cron:  * * * * * php artisan schedule:run
| En Docker lo hace el contenedor (schedule:work).
*/

// Mensualidad del mes para alumnos ACTIVOS. Solo del día 1 al límite de pago (día 10):
// así, si el equipo no se prendió el día 1, se genera el primer día que se prenda, pero
// nunca crea cargos de un mes ya avanzado (p. ej. al instalar el sistema a fin de mes).
Schedule::command('cobranza:generar-mensualidad')
    ->hourly()->between('7:00', '21:00')
    ->when(fn () => today()->day <= (int) config('cobranza.mensualidad_hasta_dia', 10))
    ->withoutOverlapping()
    ->onOneServer();

// Recargos a lo vencido (desde el día 11). Idempotente: se puede correr cada hora.
Schedule::command('cobranza:aplicar-recargos')
    ->hourly()->between('7:00', '21:00')
    ->withoutOverlapping()
    ->onOneServer();

// WhatsApp: recordatorio (día 8), recargo (día 11) o bloqueo (día 16); una vez al día por alumno.
// No envía nada mientras WHATSAPP_RECORDATORIOS=false.
Schedule::command('whatsapp:recordatorios')
    ->hourly()->between('9:00', '19:00')
    ->withoutOverlapping()
    ->onOneServer();
