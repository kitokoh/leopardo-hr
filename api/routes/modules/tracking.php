<?php

use App\Http\Controllers\Api\V1\FleetController;
use App\Http\Controllers\Api\V1\TrackingSyncController;
use App\Http\Controllers\Api\V1\VehicleAlertController;
use App\Http\Controllers\Api\V1\VehicleController;
use App\Http\Controllers\Api\V1\VehicleMaintenanceController;
use App\Http\Controllers\Api\V1\VehicleTripController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:api', 'auth:sanctum', 'tenant', 'throttle:api-plan'])->group(function (): void {
    // Vehicles CRUD
    Route::get('/vehicles', [VehicleController::class, 'index']);
    Route::post('/vehicles', [VehicleController::class, 'store']);
    Route::get('/vehicles/{id}', [VehicleController::class, 'show'])->whereNumber('id');
    Route::put('/vehicles/{id}', [VehicleController::class, 'update'])->whereNumber('id');
    Route::delete('/vehicles/{id}', [VehicleController::class, 'destroy'])->whereNumber('id');

    // Vehicle sub-resources
    Route::get('/vehicles/{id}/position', [VehicleController::class, 'position'])->whereNumber('id');
    Route::get('/vehicles/{id}/trips', [VehicleController::class, 'trips'])->whereNumber('id');
    Route::get('/vehicles/{id}/alerts', [VehicleController::class, 'vehicleAlerts'])->whereNumber('id');
    Route::get('/vehicles/{id}/maintenance', [VehicleController::class, 'maintenance'])->whereNumber('id');

    // Vehicle assignments
    Route::post('/vehicles/{id}/assign', [VehicleController::class, 'assign'])->whereNumber('id');
    Route::post('/vehicles/{id}/unassign', [VehicleController::class, 'unassign'])->whereNumber('id');
    Route::get('/vehicles/{id}/assignments', [VehicleController::class, 'assignments'])->whereNumber('id');

    // Trips
    Route::get('/vehicle-trips', [VehicleTripController::class, 'index']);
    Route::get('/vehicle-trips/{id}', [VehicleTripController::class, 'show'])->whereNumber('id');

    // Alerts
    Route::get('/vehicle-alerts', [VehicleAlertController::class, 'index']);
    Route::post('/vehicle-alerts/{id}/acknowledge', [VehicleAlertController::class, 'acknowledge'])->whereNumber('id');

    // Maintenance
    Route::get('/vehicle-maintenance', [VehicleMaintenanceController::class, 'index']);
    Route::post('/vehicle-maintenance', [VehicleMaintenanceController::class, 'store']);
    Route::put('/vehicle-maintenance/{id}', [VehicleMaintenanceController::class, 'update'])->whereNumber('id');
    Route::delete('/vehicle-maintenance/{id}', [VehicleMaintenanceController::class, 'destroy'])->whereNumber('id');

    // Traccar sync
    Route::post('/tracking/sync-devices', [TrackingSyncController::class, 'syncDevices']);
    Route::post('/tracking/sync-positions', [TrackingSyncController::class, 'syncPositions']);
    Route::post('/tracking/sync-trips', [TrackingSyncController::class, 'syncTrips']);

    // Fleet dashboard
    Route::get('/fleet/overview', [FleetController::class, 'overview']);
    Route::get('/fleet/live-map', [FleetController::class, 'liveMap']);
    Route::get('/fleet/reports/fuel', [FleetController::class, 'fuelReport']);
    Route::get('/fleet/reports/mileage', [FleetController::class, 'mileageReport']);
    Route::get('/fleet/reports/maintenance-due', [FleetController::class, 'maintenanceDue']);
});
