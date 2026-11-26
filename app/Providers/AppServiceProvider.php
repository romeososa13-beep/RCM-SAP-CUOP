<?php

namespace App\Providers;

use Anthropic\Client;
use App\Services\WhatsApp\AsistenteApagado;
use App\Services\WhatsApp\AsistenteIa;
use App\Services\WhatsApp\ClaudeAsistente;
use App\Services\WhatsApp\CloudApiClient;
use App\Services\WhatsApp\LogClient;
use App\Services\WhatsApp\WhatsAppClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(WhatsAppClient::class, function () {
            $c = config('services.whatsapp');

            if (($c['driver'] ?? 'log') !== 'cloud') {
                return new LogClient;
            }
            if (blank($c['token']) || blank($c['phone_number_id'])) {
                throw new \RuntimeException('WHATSAPP_DRIVER=cloud requiere WHATSAPP_TOKEN y WHATSAPP_PHONE_NUMBER_ID.');
            }

            return new CloudApiClient($c['token'], $c['phone_number_id'], $c['api_version']);
        });

        $this->app->singleton(AsistenteIa::class, function () {
            $c = config('services.anthropic');

            if (! ($c['habilitado'] ?? false) || blank($c['api_key'])) {
                return new AsistenteApagado;
            }

            return new ClaudeAsistente(new Client(apiKey: $c['api_key']), $c);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
