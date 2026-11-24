<?php

use App\Http\Controllers\CorteCajaExportController;
use App\Http\Controllers\ReciboPagoController;
use App\Livewire\Alumnos\PadronAlumnos;
use App\Livewire\Auth\Login;
use App\Livewire\Caja\EstadoCuenta;
use App\Livewire\Reportes\CarteraVencida;
use App\Livewire\Reportes\CorteCaja;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/login', Login::class)->middleware('guest')->name('login');

Route::post('/logout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect('/login');
})->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/', function () {
        $u = auth()->user();

        return redirect()->route($u->hasAnyRole(['Administrador', 'Cajero']) ? 'caja.estado-cuenta' : 'alumnos.padron');
    })->name('home');

    // Módulo A: Caja
    Route::middleware('role:Administrador|Cajero')->group(function () {
        Route::get('/caja/estado-cuenta/{matricula?}', EstadoCuenta::class)->name('caja.estado-cuenta');
        Route::get('/caja/recibos/{pago}', ReciboPagoController::class)->withTrashed()->name('caja.recibo');
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
        Route::get('/reportes/cartera-vencida', CarteraVencida::class)->name('reportes.cartera-vencida');
    });
});
