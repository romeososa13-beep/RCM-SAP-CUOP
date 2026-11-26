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

    /*
    | WhatsApp: recordatorios automáticos (apagados hasta configurar la cuenta de Meta).
    |   día 8  → recordatorio de pago (antes del día límite)
    |   día 11 → aviso de recargo
    |   día 16 → aviso de bloqueo
    */
    // La mensualidad se genera del día 1 hasta este día (si el equipo estuvo apagado el día 1).
    'mensualidad_hasta_dia' => (int) env('COBRANZA_MENSUALIDAD_HASTA_DIA', 10),

    'whatsapp_recordatorios' => (bool) env('WHATSAPP_RECORDATORIOS', false),
    'whatsapp_dias' => ['recordatorio_pago' => 8, 'aviso_recargo' => 11, 'aviso_bloqueo' => 16],

    // Nombres de las plantillas aprobadas en Meta (se pueden cambiar sin tocar código).
    'whatsapp_plantillas' => [
        'recordatorio_pago' => env('WA_TPL_RECORDATORIO', 'recordatorio_pago'),
        'aviso_recargo' => env('WA_TPL_RECARGO', 'aviso_recargo'),
        'aviso_bloqueo' => env('WA_TPL_BLOQUEO', 'aviso_bloqueo'),
        'pago_recibido' => env('WA_TPL_PAGO', 'pago_recibido'),
    ],

    // Lo que el chatbot responde en «Cómo pagar». Cámbielo por los datos reales de la institución.
    'datos_pago' => env('COBRANZA_DATOS_PAGO', 'Paga en caja de tu sede (efectivo o tarjeta) o por transferencia. Pide en caja los datos bancarios y envía tu comprobante con tu matrícula.'),
    'horario_atencion' => env('COBRANZA_HORARIO', 'lunes a viernes de 9:00 a 18:00 y sábados de 8:00 a 14:00'),
];
