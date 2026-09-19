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

use App\Modules\HealthManager\Interfaces\Api\V1\Controllers\HealthAdmissionController;
use App\Modules\HealthManager\Interfaces\Api\V1\Controllers\HealthAppointmentController;
use App\Modules\HealthManager\Interfaces\Api\V1\Controllers\HealthBedController;
use App\Modules\HealthManager\Interfaces\Api\V1\Controllers\HealthCareActController;
use App\Modules\HealthManager\Interfaces\Api\V1\Controllers\HealthConsultationController;
use App\Modules\HealthManager\Interfaces\Api\V1\Controllers\HealthDepartmentController;
use App\Modules\HealthManager\Interfaces\Api\V1\Controllers\HealthInvoiceController;
use App\Modules\HealthManager\Interfaces\Api\V1\Controllers\HealthPatientController;
use App\Modules\HealthManager\Interfaces\Api\V1\Controllers\HealthPractitionerController;
use App\Modules\HealthManager\Interfaces\Api\V1\Controllers\HealthPrescriptionController;
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

    // HC-004 (#7788) — rendez-vous & agenda praticiens : direction et
    // accueil planifient et voient tout, un praticien actif ne voit que SON
    // agenda. Conflit de créneau praticien → 409 ; transitions de statut
    // validées (machine à états) → 422. `/agenda` AVANT `/{appointment}`.
    Route::get('/health-manager/appointments', [HealthAppointmentController::class, 'index']);
    Route::get('/health-manager/appointments/agenda', [HealthAppointmentController::class, 'agenda']);
    Route::post('/health-manager/appointments', [HealthAppointmentController::class, 'store']);
    Route::get('/health-manager/appointments/{appointment}', [HealthAppointmentController::class, 'show'])->whereNumber('appointment');
    Route::put('/health-manager/appointments/{appointment}', [HealthAppointmentController::class, 'update'])->whereNumber('appointment');
    Route::post('/health-manager/appointments/{appointment}/status', [HealthAppointmentController::class, 'updateStatus'])->whereNumber('appointment');
    Route::delete('/health-manager/appointments/{appointment}', [HealthAppointmentController::class, 'destroy'])->whereNumber('appointment');

    // HC-005 (#7789) — consultations & ordonnances : CONTENU MÉDICAL.
    // Praticiens actifs et direction lisent ; seul le praticien AUTEUR (ou
    // la direction) modifie SA consultation ; la réception n'accède JAMAIS
    // (403). Pas de suppression : un dossier médical ne s'efface pas.
    Route::get('/health-manager/consultations', [HealthConsultationController::class, 'index']);
    Route::post('/health-manager/consultations', [HealthConsultationController::class, 'store']);
    Route::get('/health-manager/consultations/{consultation}', [HealthConsultationController::class, 'show'])->whereNumber('consultation');
    Route::put('/health-manager/consultations/{consultation}', [HealthConsultationController::class, 'update'])->whereNumber('consultation');

    // HC-005 (#7789) — ordonnances (≥ 1 ligne, transaction) ; historique
    // par patient (?patient_id=). Immuables après émission.
    Route::get('/health-manager/prescriptions', [HealthPrescriptionController::class, 'index']);
    Route::post('/health-manager/prescriptions', [HealthPrescriptionController::class, 'store']);
    Route::get('/health-manager/prescriptions/{prescription}', [HealthPrescriptionController::class, 'show'])->whereNumber('prescription');

    // HC-006 (#7790) — hospitalisations : admission (lit libre verrouillé
    // sous transaction, 409 sinon), transfert tracé (libère l'ancien lit),
    // sortie (libère le lit, notes chiffrées). Pas de suppression : un
    // séjour ne s'efface pas. `/occupancy` = occupation par service.
    Route::get('/health-manager/admissions', [HealthAdmissionController::class, 'index']);
    Route::post('/health-manager/admissions', [HealthAdmissionController::class, 'store']);
    Route::get('/health-manager/admissions/{admission}', [HealthAdmissionController::class, 'show'])->whereNumber('admission');
    Route::post('/health-manager/admissions/{admission}/transfer', [HealthAdmissionController::class, 'transfer'])->whereNumber('admission');
    Route::post('/health-manager/admissions/{admission}/discharge', [HealthAdmissionController::class, 'discharge'])->whereNumber('admission');
    Route::get('/health-manager/occupancy', [HealthAdmissionController::class, 'occupancy']);

    // HC-007 (#7791) — catalogue d'actes médicaux (tarifs) : health.billing
    // et health.admin gèrent. Pas de DELETE : un acte facturé se DÉSACTIVE
    // (les factures existantes gardent leurs prix FIGÉS).
    Route::get('/health-manager/care-acts', [HealthCareActController::class, 'index']);
    Route::post('/health-manager/care-acts', [HealthCareActController::class, 'store']);
    Route::get('/health-manager/care-acts/{careAct}', [HealthCareActController::class, 'show'])->whereNumber('careAct');
    Route::put('/health-manager/care-acts/{careAct}', [HealthCareActController::class, 'update'])->whereNumber('careAct');

    // HC-007 (#7791) — factures de soins : total recalculé SERVEUR (Σ
    // lignes − remise, prix figés), numéro HINV-YYYY-NNNN à l'émission,
    // paiements sous transaction (sur-paiement 422), facture émise non
    // modifiable (annulation seulement). Pas de DELETE (piste comptable).
    // `/billing/stats` = CA du mois + impayés.
    Route::get('/health-manager/invoices', [HealthInvoiceController::class, 'index']);
    Route::post('/health-manager/invoices', [HealthInvoiceController::class, 'store']);
    Route::get('/health-manager/invoices/{invoice}', [HealthInvoiceController::class, 'show'])->whereNumber('invoice');
    Route::put('/health-manager/invoices/{invoice}', [HealthInvoiceController::class, 'update'])->whereNumber('invoice');
    Route::post('/health-manager/invoices/{invoice}/issue', [HealthInvoiceController::class, 'issue'])->whereNumber('invoice');
    Route::post('/health-manager/invoices/{invoice}/cancel', [HealthInvoiceController::class, 'cancel'])->whereNumber('invoice');
    Route::post('/health-manager/invoices/{invoice}/payments', [HealthInvoiceController::class, 'storePayment'])->whereNumber('invoice');
    Route::get('/health-manager/billing/stats', [HealthInvoiceController::class, 'stats']);
});
