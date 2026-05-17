<?php

/**
 * Routes Integrations — Push notifications, Calendar sync, ZKTeco.
 */

use App\Http\Controllers\Api\V1\CalendarSyncController;
use App\Http\Controllers\Api\V1\DeviceTokenController;
use App\Http\Controllers\Api\V1\KioskController;
use App\Http\Controllers\Api\V1\ZktecoController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:api', 'auth:sanctum', 'tenant', 'throttle:api-plan'])->group(function (): void {

    // ── Push Notification Device Tokens ───────────────────────────────────
    Route::post('/device-tokens', [DeviceTokenController::class, 'register']);
    Route::delete('/device-tokens', [DeviceTokenController::class, 'unregister']);
    Route::get('/device-tokens', [DeviceTokenController::class, 'index']);
    Route::post('/push-notifications/send', [DeviceTokenController::class, 'sendTest']);

    // ── Calendar Sync ─────────────────────────────────────────────────────
    Route::get('/calendar/connections', [CalendarSyncController::class, 'connections']);
    Route::post('/calendar/connect', [CalendarSyncController::class, 'connect']);
    Route::delete('/calendar/disconnect/{provider}', [CalendarSyncController::class, 'disconnect']);
    Route::post('/calendar/sync', [CalendarSyncController::class, 'sync']);
    Route::get('/calendar/events', [CalendarSyncController::class, 'events']);

    // ── ZKTeco Device Management ──────────────────────────────────────────
    Route::get('/zkteco/devices', [ZktecoController::class, 'index']);
    Route::post('/zkteco/devices', [ZktecoController::class, 'store']);
    Route::get('/zkteco/devices/{id}', [ZktecoController::class, 'show'])->whereNumber('id');
    Route::put('/zkteco/devices/{id}', [ZktecoController::class, 'update'])->whereNumber('id');
    Route::delete('/zkteco/devices/{id}', [ZktecoController::class, 'destroy'])->whereNumber('id');
    Route::get('/zkteco/devices/{id}/sync-logs', [ZktecoController::class, 'syncLogs'])->whereNumber('id');
    Route::post('/zkteco/devices/{serialNumber}/push-users', [ZktecoController::class, 'pushUsers']);

    // ── Kiosk Extensions (H1-H4) ──────────────────────────────────────────
    Route::post('/kiosks/{deviceCode}/employee-info', [KioskController::class, 'employeeInfo']);
    Route::get('/kiosks/{deviceCode}/announcements', [KioskController::class, 'announcements']);
    Route::post('/kiosks/{deviceCode}/leave-balance', [KioskController::class, 'leaveBalance']);
    Route::post('/kiosks/{deviceCode}/qr-punch', [KioskController::class, 'qrPunch']);
});

// ── ZKTeco Device-to-Server (token-based, no Sanctum) ─────────────────
Route::middleware(['throttle:api'])->group(function (): void {
    Route::post('/zkteco/heartbeat/{serialNumber}', [ZktecoController::class, 'heartbeat']);
    Route::post('/zkteco/sync-attendance/{serialNumber}', [ZktecoController::class, 'syncAttendance']);
});
