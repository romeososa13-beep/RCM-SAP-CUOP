<?php

namespace App\Providers;

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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
