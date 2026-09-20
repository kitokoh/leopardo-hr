<?php

declare(strict_types=1);

/**
 * Routes HealthManager (solution verticale hôpitaux & cliniques privées)
 * — HC-001 (#7785) à HC-007 (#7791).
 *
 * Toutes les routes sont tenant-scoped et soumises au feature flag
 * `healthmanager` (fail-closed) : solution inactive → 403
 * (contrôle `assertSolutionActive()` dans chaque contrôleur).
 *
 * Chemins : /health-manager/... (ids numériques bigint, whereNumber).
 * RBAC : direction (principal/rh/manager) = gestion complète ; praticien
 * = son agenda + ses consultations + patients (lecture) ; réception =
 * patients (administratif), rendez-vous, admissions ; facturation =
 * catalogue d'actes, factures, encaissements ; employé lambda = 403.
 * Le contenu médical (consultations, prescriptions) n'est JAMAIS visible
 * de la réception (Policies HealthManager + HealthAccess).
 */

use App\Modules\HealthManager\Interfaces\Api\V1\Controllers\HealthAdmissionController;
use App\Modules\HealthManager\Interfaces\Api\V1\Controllers\HealthAppointmentController;
use App\Modules\HealthManager\Interfaces\Api\V1\Controllers\HealthBedController;
use App\Modules\HealthManager\Interfaces\Api\V1\Controllers\HealthCareActController;
use App\Modules\HealthManager\Interfaces\Api\V1\Controllers\HealthConsultationController;
use App\Modules\HealthManager\Interfaces\Api\V1\Controllers\HealthDashboardController;
use App\Modules\HealthManager\Interfaces\Api\V1\Controllers\HealthDepartmentController;
use App\Modules\HealthManager\Interfaces\Api\V1\Controllers\HealthInvoiceController;
use App\Modules\HealthManager\Interfaces\Api\V1\Controllers\HealthPatientController;
use App\Modules\HealthManager\Interfaces\Api\V1\Controllers\HealthPractitionerController;
use App\Modules\HealthManager\Interfaces\Api\V1\Controllers\HealthPrescriptionController;
use App\Modules\HealthManager\Interfaces\Api\V1\Controllers\HealthRoomController;
use App\Modules\HealthManager\Interfaces\Api\V1\Controllers\HealthSpecialtyController;
use App\Modules\HealthManager\Interfaces\Api\V1\Controllers\HealthStaffRoleController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:api', 'auth:sanctum', 'token.refresh', 'tenant', 'throttle:api-plan'])
    ->prefix('health-manager')
    ->group(function (): void {
        // ── Tableau de bord (HC-008 #7792) ─────────────────────────────
        Route::get('/dashboard', [HealthDashboardController::class, 'index']);

        // ── Référentiel structure (HC-002 #7786) ───────────────────────
        Route::get('/departments', [HealthDepartmentController::class, 'index']);
        Route::post('/departments', [HealthDepartmentController::class, 'store']);
        Route::get('/departments/{department}', [HealthDepartmentController::class, 'show'])->whereNumber('department');
        Route::put('/departments/{department}', [HealthDepartmentController::class, 'update'])->whereNumber('department');
        Route::delete('/departments/{department}', [HealthDepartmentController::class, 'destroy'])->whereNumber('department');

        Route::get('/rooms', [HealthRoomController::class, 'index']);
        Route::post('/rooms', [HealthRoomController::class, 'store']);
        Route::get('/rooms/{room}', [HealthRoomController::class, 'show'])->whereNumber('room');
        Route::put('/rooms/{room}', [HealthRoomController::class, 'update'])->whereNumber('room');
        Route::delete('/rooms/{room}', [HealthRoomController::class, 'destroy'])->whereNumber('room');

        Route::get('/beds', [HealthBedController::class, 'index']);
        Route::post('/beds', [HealthBedController::class, 'store']);
        Route::get('/beds/{bed}', [HealthBedController::class, 'show'])->whereNumber('bed');
        Route::put('/beds/{bed}', [HealthBedController::class, 'update'])->whereNumber('bed');
        Route::delete('/beds/{bed}', [HealthBedController::class, 'destroy'])->whereNumber('bed');

        Route::get('/specialties', [HealthSpecialtyController::class, 'index']);
        Route::post('/specialties', [HealthSpecialtyController::class, 'store']);
        Route::put('/specialties/{specialty}', [HealthSpecialtyController::class, 'update'])->whereNumber('specialty');
        Route::delete('/specialties/{specialty}', [HealthSpecialtyController::class, 'destroy'])->whereNumber('specialty');

        Route::get('/practitioners', [HealthPractitionerController::class, 'index']);
        Route::post('/practitioners', [HealthPractitionerController::class, 'store']);
        Route::get('/practitioners/{practitioner}', [HealthPractitionerController::class, 'show'])->whereNumber('practitioner');
        Route::put('/practitioners/{practitioner}', [HealthPractitionerController::class, 'update'])->whereNumber('practitioner');
        Route::delete('/practitioners/{practitioner}', [HealthPractitionerController::class, 'destroy'])->whereNumber('practitioner');

        Route::get('/staff-roles', [HealthStaffRoleController::class, 'index']);
        Route::post('/staff-roles', [HealthStaffRoleController::class, 'store']);
        Route::delete('/staff-roles/{staffRole}', [HealthStaffRoleController::class, 'destroy'])->whereNumber('staffRole');

        // ── Registre patients (HC-003 #7787) ───────────────────────────
        Route::get('/patients', [HealthPatientController::class, 'index']);
        Route::post('/patients', [HealthPatientController::class, 'store']);
        Route::get('/patients/{patient}', [HealthPatientController::class, 'show'])->whereNumber('patient');
        Route::put('/patients/{patient}', [HealthPatientController::class, 'update'])->whereNumber('patient');
        // Archivage logique (jamais de suppression physique — données de santé).
        Route::post('/patients/{patient}/archive', [HealthPatientController::class, 'archive'])->whereNumber('patient');

        // ── Rendez-vous & agenda (HC-004 #7788) ────────────────────────
        Route::get('/appointments', [HealthAppointmentController::class, 'index']);
        Route::post('/appointments', [HealthAppointmentController::class, 'store']);
        Route::get('/appointments/agenda', [HealthAppointmentController::class, 'agenda']);
        Route::get('/appointments/{appointment}', [HealthAppointmentController::class, 'show'])->whereNumber('appointment');
        Route::put('/appointments/{appointment}', [HealthAppointmentController::class, 'update'])->whereNumber('appointment');
        Route::post('/appointments/{appointment}/status', [HealthAppointmentController::class, 'transition'])->whereNumber('appointment');

        // ── Consultations & prescriptions (HC-005 #7789) ───────────────
        Route::get('/consultations', [HealthConsultationController::class, 'index']);
        Route::post('/consultations', [HealthConsultationController::class, 'store']);
        Route::get('/consultations/{consultation}', [HealthConsultationController::class, 'show'])->whereNumber('consultation');
        Route::put('/consultations/{consultation}', [HealthConsultationController::class, 'update'])->whereNumber('consultation');

        Route::get('/prescriptions', [HealthPrescriptionController::class, 'index']);
        Route::post('/prescriptions', [HealthPrescriptionController::class, 'store']);
        Route::get('/prescriptions/{prescription}', [HealthPrescriptionController::class, 'show'])->whereNumber('prescription');

        // ── Hospitalisations & lits (HC-006 #7790) ─────────────────────
        Route::get('/admissions', [HealthAdmissionController::class, 'index']);
        Route::post('/admissions', [HealthAdmissionController::class, 'store']);
        Route::get('/admissions/occupancy', [HealthAdmissionController::class, 'occupancy']);
        Route::get('/admissions/{admission}', [HealthAdmissionController::class, 'show'])->whereNumber('admission');
        Route::post('/admissions/{admission}/transfer', [HealthAdmissionController::class, 'transfer'])->whereNumber('admission');
        Route::post('/admissions/{admission}/discharge', [HealthAdmissionController::class, 'discharge'])->whereNumber('admission');

        // ── Actes & facturation des soins (HC-007 #7791) ───────────────
        Route::get('/care-acts', [HealthCareActController::class, 'index']);
        Route::post('/care-acts', [HealthCareActController::class, 'store']);
        Route::put('/care-acts/{careAct}', [HealthCareActController::class, 'update'])->whereNumber('careAct');
        Route::delete('/care-acts/{careAct}', [HealthCareActController::class, 'destroy'])->whereNumber('careAct');

        Route::get('/invoices', [HealthInvoiceController::class, 'index']);
        Route::post('/invoices', [HealthInvoiceController::class, 'store']);
        Route::get('/invoices/stats', [HealthInvoiceController::class, 'stats']);
        Route::get('/invoices/{invoice}', [HealthInvoiceController::class, 'show'])->whereNumber('invoice');
        Route::post('/invoices/{invoice}/issue', [HealthInvoiceController::class, 'issue'])->whereNumber('invoice');
        Route::post('/invoices/{invoice}/payments', [HealthInvoiceController::class, 'pay'])->whereNumber('invoice');
        Route::post('/invoices/{invoice}/cancel', [HealthInvoiceController::class, 'cancel'])->whereNumber('invoice');
    });
