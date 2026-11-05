<?php

use App\Http\Controllers\SecurityDashboardController;
use App\Http\Controllers\SecurityLoginController;
use Illuminate\Support\Facades\Route;

// Inicio: los invitados van al login; los logueados ven su sesión.
// (El middleware "guest" manda acá a quien ya inició sesión, por eso no puede redirigir al login: sería un bucle.)
Route::get('/', function () {
    return auth()->check() ? view('home') : redirect('/security-login');
})->name('home');

// Formulario de login. Se llama "login" para que el middleware "auth" sepa adónde mandar a los invitados.
Route::get('/security-login', function () {
    return view('security-login');
})->middleware('guest')->name('login');

Route::post('/security-login', [SecurityLoginController::class, 'login'])
    ->middleware('guest');

Route::post('/security-logout', [SecurityLoginController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

// Panel de seguridad: requiere estar logueado Y ser admin (Gate viewSecurityDashboard)
Route::middleware(['auth', 'can:viewSecurityDashboard'])->group(function () {
    Route::get('/security-dashboard', [SecurityDashboardController::class, 'index'])
        ->name('security.dashboard');

    // Mejora: exportación de evidencias en CSV
    Route::get('/security-dashboard/export', [SecurityDashboardController::class, 'export'])
        ->name('security.export');
});
