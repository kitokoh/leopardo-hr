<?php

declare(strict_types=1);

/**
 * Routes HealthManager (solution verticale hôpitaux & cliniques) — BC-30.
 *
 * Toutes les routes sont tenant-scoped et soumises au feature flag
 * `healthmanager` (HC-001 #7785) : solution inactive → 403 fail-closed
 * (contrôle `assertSolutionActive()` dans chaque contrôleur).
 *
 * Chemins : /health-manager/... (ids numériques bigint, whereNumber).
 * RBAC (HealthAccess + policies deny-by-default) : direction = gestion
 * complète ; accueil = registre patients ; praticien = lecture ; employé
 * lambda = 403. Données de santé JAMAIS exposées hors tenant.
 */

use App\Modules\HealthManager\Interfaces\Api\V1\Controllers\HealthBedController;
use App\Modules\HealthManager\Interfaces\Api\V1\Controllers\HealthDepartmentController;
use App\Modules\HealthManager\Interfaces\Api\V1\Controllers\HealthPatientController;
use App\Modules\HealthManager\Interfaces\Api\V1\Controllers\HealthPractitionerController;
use App\Modules\HealthManager\Interfaces\Api\V1\Controllers\HealthRoomController;
use App\Modules\HealthManager\Interfaces\Api\V1\Controllers\HealthSpecialtyController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:api', 'auth:sanctum', 'token.refresh', 'tenant', 'throttle:api-plan'])->group(function (): void {
    // HC-002 (#7786) — services médicaux (direction).
    Route::get('/health-manager/departments', [HealthDepartmentController::class, 'index']);
    Route::post('/health-manager/departments', [HealthDepartmentController::class, 'store']);
    Route::get('/health-manager/departments/{department}', [HealthDepartmentController::class, 'show'])->whereNumber('department');
    Route::put('/health-manager/departments/{department}', [HealthDepartmentController::class, 'update'])->whereNumber('department');
    Route::delete('/health-manager/departments/{department}', [HealthDepartmentController::class, 'destroy'])->whereNumber('department');

    // HC-002 (#7786) — salles (une salle appartient à un service).
    Route::get('/health-manager/rooms', [HealthRoomController::class, 'index']);
    Route::post('/health-manager/rooms', [HealthRoomController::class, 'store']);
    Route::get('/health-manager/rooms/{room}', [HealthRoomController::class, 'show'])->whereNumber('room');
    Route::put('/health-manager/rooms/{room}', [HealthRoomController::class, 'update'])->whereNumber('room');
    Route::delete('/health-manager/rooms/{room}', [HealthRoomController::class, 'destroy'])->whereNumber('room');

    // HC-002 (#7786) — lits (statut libre/occupé/maintenance).
    Route::get('/health-manager/beds', [HealthBedController::class, 'index']);
    Route::post('/health-manager/beds', [HealthBedController::class, 'store']);
    Route::get('/health-manager/beds/{bed}', [HealthBedController::class, 'show'])->whereNumber('bed');
    Route::put('/health-manager/beds/{bed}', [HealthBedController::class, 'update'])->whereNumber('bed');
    Route::delete('/health-manager/beds/{bed}', [HealthBedController::class, 'destroy'])->whereNumber('bed');

    // HC-002 (#7786) — spécialités (seedées à l'activation, éditables).
    Route::get('/health-manager/specialties', [HealthSpecialtyController::class, 'index']);
    Route::post('/health-manager/specialties', [HealthSpecialtyController::class, 'store']);
    Route::get('/health-manager/specialties/{specialty}', [HealthSpecialtyController::class, 'show'])->whereNumber('specialty');
    Route::put('/health-manager/specialties/{specialty}', [HealthSpecialtyController::class, 'update'])->whereNumber('specialty');
    Route::delete('/health-manager/specialties/{specialty}', [HealthSpecialtyController::class, 'destroy'])->whereNumber('specialty');

    // HC-002 (#7786) — praticiens (lien RH découplé, spécialités n-n).
    Route::get('/health-manager/practitioners', [HealthPractitionerController::class, 'index']);
    Route::post('/health-manager/practitioners', [HealthPractitionerController::class, 'store']);
    Route::get('/health-manager/practitioners/{practitioner}', [HealthPractitionerController::class, 'show'])->whereNumber('practitioner');
    Route::put('/health-manager/practitioners/{practitioner}', [HealthPractitionerController::class, 'update'])->whereNumber('practitioner');
    Route::delete('/health-manager/practitioners/{practitioner}', [HealthPractitionerController::class, 'destroy'])->whereNumber('practitioner');

    // HC-003 (#7787) — registre patients (RBAC strict : direction/accueil
    // gèrent, praticiens/facturation lisent). MRN généré côté serveur ;
    // DELETE = ARCHIVAGE (soft delete, jamais de suppression physique).
    Route::get('/health-manager/patients', [HealthPatientController::class, 'index']);
    Route::post('/health-manager/patients', [HealthPatientController::class, 'store']);
    Route::get('/health-manager/patients/{patient}', [HealthPatientController::class, 'show'])->whereNumber('patient');
    Route::put('/health-manager/patients/{patient}', [HealthPatientController::class, 'update'])->whereNumber('patient');
    Route::delete('/health-manager/patients/{patient}', [HealthPatientController::class, 'destroy'])->whereNumber('patient');
});
