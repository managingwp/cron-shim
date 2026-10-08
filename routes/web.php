<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\IncidentController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\RunController;
use App\Http\Controllers\Settings\NotificationChannelController;
use App\Http\Controllers\SiteController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:10,1')->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::view('/dashboard', 'dashboard')->name('dashboard');

    Route::resource('sites', SiteController::class);
    Route::post('/sites/{site}/rotate', [SiteController::class, 'rotate'])->name('sites.rotate');
    Route::post('/sites/{site}/toggle', [SiteController::class, 'toggle'])->name('sites.toggle');

    Route::get('/runs', [RunController::class, 'index'])->name('runs.index');
    Route::get('/runs/{run}', [RunController::class, 'show'])->name('runs.show');
    Route::get('/logs', [LogController::class, 'index'])->name('logs.index');

    Route::get('/incidents', [IncidentController::class, 'index'])->name('incidents.index');
    Route::post('/incidents/{incident}/resolve', [IncidentController::class, 'resolve'])->name('incidents.resolve');
    Route::post('/incidents/{incident}/acknowledge', [IncidentController::class, 'acknowledge'])->name('incidents.acknowledge');

    Route::get('/settings', [NotificationChannelController::class, 'index'])->name('settings.index');
    Route::post('/settings/channels', [NotificationChannelController::class, 'store'])->name('settings.channels.store');
    Route::delete('/settings/channels/{channel}', [NotificationChannelController::class, 'destroy'])->name('settings.channels.destroy');
    Route::post('/settings/channels/{channel}/test', [NotificationChannelController::class, 'test'])->name('settings.channels.test');
});
