<?php

/*
| Reglas de cobranza de la institución (confirmadas por Administración, nov 2026):
|
|  - Del día 1 al día de tolerancia del plan (10): colegiatura según su plan.
|  - Desde el día siguiente (11): recargo. Colegiatura: el monto fijo del plan ($180).
|    Inglés: un porcentaje del costo mensual de inglés.
|  - Desde el día 16: bloqueo de plataformas a quien deba colegiatura o inglés.
*/

return [
    // Recargo de inglés como porcentaje de su costo mensual.
    'recargo_ingles_porcentaje' => (float) env('COBRANZA_RECARGO_INGLES_PCT', 10),

    // Días después del vencimiento a partir de los cuales hay bloqueo:
    // vence el 10 → 10 + 6 = día 16.
    'bloqueo_dias_despues_vencimiento' => (int) env('COBRANZA_BLOQUEO_DIAS', 6),

    // Tipos de cargo que generan recargo y bloqueo.
    'tipos_con_recargo' => ['COLEGIATURA', 'INGLES'],
    'tipos_con_bloqueo' => ['COLEGIATURA', 'INGLES'],
];
