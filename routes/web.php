<?php

use App\Http\Controllers\AssistantController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MonitoredMessageController;
use App\Http\Controllers\SystemSettingsController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [AuthController::class, 'create'])->name('login');
Route::post('/login', [AuthController::class, 'store'])->name('login.store');

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('/assistant/boot', [AssistantController::class, 'boot'])->name('assistant.boot');
    Route::post('/assistant/message', [AssistantController::class, 'message'])->name('assistant.message');
    Route::post('/assistant/action', [AssistantController::class, 'action'])->name('assistant.action');
    Route::post('/assistant/realtime/call', [AssistantController::class, 'realtimeCall'])->name('assistant.realtime.call');
    Route::post('/messages/import', [MonitoredMessageController::class, 'import'])->name('messages.import');
    Route::post('/messages/{message}/reanalyze', [MonitoredMessageController::class, 'reanalyze'])->name('messages.reanalyze');
    Route::resource('messages', MonitoredMessageController::class)->only(['index', 'store', 'show', 'destroy']);

    Route::middleware('role:system_admin')->prefix('admin')->name('admin.')->group(function (): void {
        Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
        Route::post('/users', [UserManagementController::class, 'store'])->name('users.store');
        Route::delete('/users/{user}', [UserManagementController::class, 'destroy'])->name('users.destroy');
        Route::get('/settings', [SystemSettingsController::class, 'index'])->name('settings.index');
        Route::post('/settings/logo', [SystemSettingsController::class, 'update'])->name('settings.logo.update');
        Route::delete('/settings/logo', [SystemSettingsController::class, 'destroy'])->name('settings.logo.destroy');
        Route::post('/settings/login-media', [SystemSettingsController::class, 'updateLoginMedia'])->name('settings.login-media.update');
        Route::delete('/settings/login-media', [SystemSettingsController::class, 'destroyLoginMedia'])->name('settings.login-media.destroy');
    });
});
