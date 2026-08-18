<?php

/**
 * Routes Integrations: push notifications, calendar sync, ZKTeco and kiosks.
 */

use App\Modules\Attendance\Interfaces\Api\V1\CalendarSyncController;
use App\Modules\Attendance\Interfaces\Api\V1\KioskController;
use App\Modules\Attendance\Interfaces\Api\V1\ZktecoController;
use App\Modules\Notification\Interfaces\Api\V1\Controllers\DeviceTokenController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:api', 'auth:sanctum', 'token.refresh', 'tenant', 'throttle:api-plan'])->group(function (): void {
    Route::post('/device-tokens', [DeviceTokenController::class, 'register']);
    Route::delete('/device-tokens', [DeviceTokenController::class, 'unregister']);
    Route::get('/device-tokens', [DeviceTokenController::class, 'index']);
    Route::post('/push-notifications/send', [DeviceTokenController::class, 'sendTest']);

    Route::get('/calendar/connections', [CalendarSyncController::class, 'connections']);
    Route::post('/calendar/connect', [CalendarSyncController::class, 'connect']);
    Route::delete('/calendar/disconnect/{provider}', [CalendarSyncController::class, 'disconnect']);
    Route::post('/calendar/sync', [CalendarSyncController::class, 'sync']);
    Route::get('/calendar/events', [CalendarSyncController::class, 'events']);

    // #4692 (audit 360° 2026-08-16) : la gestion des appareils ZKTeco est
    // une surface sensible (tokens de device) — garde middleware api.manager
    // sur TOUTES les routes du CRUD, pas seulement regenerate-token. Le
    // contrôleur garde son abort_unless(isManager) en défense en profondeur.
    Route::middleware('api.manager')->group(function (): void {
        Route::get('/zkteco/devices', [ZktecoController::class, 'index']);
        Route::post('/zkteco/devices', [ZktecoController::class, 'store']);
        Route::get('/zkteco/devices/{id}', [ZktecoController::class, 'show'])->whereNumber('id');
        Route::put('/zkteco/devices/{id}', [ZktecoController::class, 'update'])->whereNumber('id');
        Route::delete('/zkteco/devices/{id}', [ZktecoController::class, 'destroy'])->whereNumber('id');
        Route::get('/zkteco/devices/{id}/sync-logs', [ZktecoController::class, 'syncLogs'])->whereNumber('id');
        Route::post('/zkteco/devices/{serialNumber}/push-users', [ZktecoController::class, 'pushUsers']);
        // Sécurité #2216 : rotation du token de device (manager uniquement)
        Route::post('/zkteco/devices/{id}/regenerate-token', [ZktecoController::class, 'regenerateToken'])->whereNumber('id');
    });
});

// Device kiosks authenticate with X-Kiosk-Token, not a Sanctum user token.
// #3367 : bucket dédié par device_code (kiosk-punch) au lieu du throttle:api
// anonyme partagé (60/min/IP) — un kiosque compromis ne doit pas épuiser le
// quota IP du site ni être ralenti par le reste du trafic non authentifié.
Route::middleware(['throttle:kiosk-punch', 'kiosk.search_path'])->group(function (): void {
    Route::post('/kiosks/{deviceCode}/employee-info', [KioskController::class, 'employeeInfo']);
    Route::get('/kiosks/{deviceCode}/announcements', [KioskController::class, 'announcements']);
    Route::post('/kiosks/{deviceCode}/leave-balance', [KioskController::class, 'leaveBalance']);
    Route::post('/kiosks/{deviceCode}/qr-punch', [KioskController::class, 'qrPunch']);
});

Route::middleware(['throttle:api'])->group(function (): void {
    Route::post('/zkteco/heartbeat/{serialNumber}', [ZktecoController::class, 'heartbeat'])
        ->middleware('zkteco.device');
    Route::post('/zkteco/sync-attendance/{serialNumber}', [ZktecoController::class, 'syncAttendance'])
        ->middleware('zkteco.device');
});
