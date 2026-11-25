<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    | WhatsApp Business Cloud API (Meta). Con driver "log" no se envía nada: los
    | mensajes quedan en storage/logs y en la tabla whatsapp_mensajes (desarrollo/pruebas).
    */
    'whatsapp' => [
        'driver' => env('WHATSAPP_DRIVER', 'log'),             // log | cloud
        'token' => env('WHATSAPP_TOKEN'),                      // token permanente de usuario del sistema
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'app_secret' => env('WHATSAPP_APP_SECRET'),            // valida la firma del webhook
        'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),        // el que se captura al registrar el webhook en Meta
        'api_version' => env('WHATSAPP_API_VERSION', 'v21.0'),
        'idioma' => env('WHATSAPP_IDIOMA', 'es_MX'),
        'lada' => env('WHATSAPP_LADA', '52'),
    ],

];
