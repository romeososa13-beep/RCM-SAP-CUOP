<?php

use App\Http\Controllers\CorteCajaExportController;
use App\Http\Controllers\ReciboPagoController;
use App\Http\Controllers\Webhooks\WhatsAppWebhookController;
use App\Livewire\Admin\BitacoraCambios;
use App\Livewire\Admin\Catalogos;
use App\Livewire\Admin\Institucion;
use App\Livewire\Admin\Usuarios;
use App\Livewire\Alumnos\PadronAlumnos;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\MiCuenta;
use App\Livewire\Caja\EstadoCuenta;
use App\Livewire\Cobranza\Campanas;
use App\Livewire\Reportes\BloqueoPlataformas;
use App\Livewire\Reportes\CarteraVencida;
use App\Livewire\Reportes\CorteCaja;
use App\Livewire\Reportes\Ingresos;
use App\Livewire\Reportes\Tablero;
use App\Livewire\WhatsApp\Bandeja;
use App\Models\Pago;
use App\Services\Cobranza\ReciboPdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/login', Login::class)->middleware('guest')->name('login');

// Webhook de WhatsApp (Meta). Público: se autentica con verify_token y la firma HMAC.
Route::get('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'verificar'])->name('webhooks.whatsapp');
Route::post('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'recibir'])->middleware('throttle:240,1');

Route::post('/logout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect('/login');
})->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/', function () {
        $u = auth()->user();

        return redirect()->route(match (true) {
            $u->hasRole('Administrador') => 'tablero',
            $u->hasRole('Cajero') => 'caja.estado-cuenta',
            default => 'alumnos.padron',
        });
    })->name('home');

    // Módulo A: Caja
    Route::middleware('role:Administrador|Cajero')->group(function () {
        Route::get('/caja/estado-cuenta/{matricula?}', EstadoCuenta::class)->name('caja.estado-cuenta');
        Route::get('/caja/recibos/{pago}', ReciboPagoController::class)->withTrashed()->name('caja.recibo');
        Route::get('/whatsapp', Bandeja::class)->name('whatsapp');
        Route::get('/cobranza/campanas', Campanas::class)->name('cobranza.campanas');
    });

    // Módulo B: Control Escolar
    Route::middleware('role:Administrador|Control Escolar')->group(function () {
        Route::get('/alumnos', PadronAlumnos::class)->name('alumnos.padron');
    });

    // Módulo C: Reportes
    Route::middleware('role:Administrador|Cajero')->group(function () {
        Route::get('/reportes/corte-caja', CorteCaja::class)->name('reportes.corte-caja');
        Route::get('/reportes/corte-caja/exportar/{formato}', CorteCajaExportController::class)
            ->whereIn('formato', ['pdf', 'xlsx'])->name('reportes.corte-caja.exportar');
    });
    Route::middleware('role:Administrador')->group(function () {
        Route::get('/tablero', Tablero::class)->name('tablero');
        Route::get('/reportes/ingresos', Ingresos::class)->name('reportes.ingresos');
        Route::get('/reportes/cartera-vencida', CarteraVencida::class)->name('reportes.cartera-vencida');

        // Administración
        Route::get('/admin/usuarios', Usuarios::class)->name('admin.usuarios');
        Route::get('/admin/catalogos', Catalogos::class)->name('admin.catalogos');
        Route::get('/admin/bitacora', BitacoraCambios::class)->name('admin.bitacora');
        Route::get('/admin/institucion', Institucion::class)->name('admin.institucion');
        // Recibo de muestra con los datos de la institución (usa el último pago registrado)
        Route::get('/admin/institucion/muestra', function (ReciboPdf $recibo) {
            $pago = Pago::latest('id')->first();
            abort_unless($pago, 404, 'Todavía no hay pagos registrados para generar una muestra.');

            return $recibo->generar($pago, request('formato') === 'media-carta' ? 'media-carta' : 'ticket')->stream('recibo-muestra.pdf');
        })->name('admin.institucion.muestra');
    });

    // Bloqueo de plataformas: lo consultan caja, control escolar y administración.
    Route::get('/reportes/bloqueo-plataformas', BloqueoPlataformas::class)
        ->middleware('role:Administrador|Cajero|Control Escolar')->name('reportes.bloqueo');

    Route::get('/mi-cuenta', MiCuenta::class)->name('mi-cuenta');
});
