<?php

declare(strict_types=1);

/**
 * Routes EduManager (solution verticale scolaire) — EDU-010.
 *
 * Toutes les routes sont tenant-scoped et soumises au feature flag
 * `edumanager` (activation #5817) : solution inactive → 403
 * (contrôle `assertSolutionActive()` dans chaque contrôleur).
 *
 * Chemins : /edu-manager/... (ids numériques bigint, whereNumber).
 * RBAC : direction (principal/rh/manager) = gestion complète ; enseignant
 * = périmètre SES classes (Policies EduManager + EduAccess) ; employé
 * lambda = 403. PII scolaires jamais exposées hors tenant.
 */

use App\Modules\EduManager\Interfaces\Api\V1\Controllers\EduAcademicYearController;
use App\Modules\EduManager\Interfaces\Api\V1\Controllers\EduAdmissionCampaignController;
use App\Modules\EduManager\Interfaces\Api\V1\Controllers\EduAdmissionController;
use App\Modules\EduManager\Interfaces\Api\V1\Controllers\EduAssessmentController;
use App\Modules\EduManager\Interfaces\Api\V1\Controllers\EduAttendanceController;
use App\Modules\EduManager\Interfaces\Api\V1\Controllers\EduCampusController;
use App\Modules\EduManager\Interfaces\Api\V1\Controllers\EduClassController;
use App\Modules\EduManager\Interfaces\Api\V1\Controllers\EduClassEnrollmentController;
use App\Modules\EduManager\Interfaces\Api\V1\Controllers\EduCourseSlotController;
use App\Modules\EduManager\Interfaces\Api\V1\Controllers\EduDashboardController;
use App\Modules\EduManager\Interfaces\Api\V1\Controllers\EduFeeController;
use App\Modules\EduManager\Interfaces\Api\V1\Controllers\EduGuardianPortalController;
use App\Modules\EduManager\Interfaces\Api\V1\Controllers\EduReportCardController;
use App\Modules\EduManager\Interfaces\Api\V1\Controllers\EduStudentController;
use App\Modules\EduManager\Interfaces\Api\V1\Controllers\EduSubjectController;
use App\Modules\EduManager\Interfaces\Api\V1\Controllers\EduTeacherWorkspaceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:api', 'auth:sanctum', 'token.refresh', 'tenant', 'throttle:api-plan'])->group(function (): void {
    // EDU-002/EDU-010 — campus (direction).
    Route::get('/edu-manager/campuses', [EduCampusController::class, 'index']);
    Route::post('/edu-manager/campuses', [EduCampusController::class, 'store']);
    Route::get('/edu-manager/campuses/{campus}', [EduCampusController::class, 'show'])->whereNumber('campus');
    Route::put('/edu-manager/campuses/{campus}', [EduCampusController::class, 'update'])->whereNumber('campus');
    Route::delete('/edu-manager/campuses/{campus}', [EduCampusController::class, 'destroy'])->whereNumber('campus');

    // EDU-003/EDU-010 — années scolaires (direction).
    Route::get('/edu-manager/academic-years', [EduAcademicYearController::class, 'index']);
    Route::post('/edu-manager/academic-years', [EduAcademicYearController::class, 'store']);
    Route::get('/edu-manager/academic-years/{year}', [EduAcademicYearController::class, 'show'])->whereNumber('year');
    Route::put('/edu-manager/academic-years/{year}', [EduAcademicYearController::class, 'update'])->whereNumber('year');
    Route::delete('/edu-manager/academic-years/{year}', [EduAcademicYearController::class, 'destroy'])->whereNumber('year');

    // EDU-003/EDU-010 — matières (direction).
    Route::get('/edu-manager/subjects', [EduSubjectController::class, 'index']);
    Route::post('/edu-manager/subjects', [EduSubjectController::class, 'store']);
    Route::get('/edu-manager/subjects/{subject}', [EduSubjectController::class, 'show'])->whereNumber('subject');
    Route::put('/edu-manager/subjects/{subject}', [EduSubjectController::class, 'update'])->whereNumber('subject');
    Route::delete('/edu-manager/subjects/{subject}', [EduSubjectController::class, 'destroy'])->whereNumber('subject');

    // EDU-002/EDU-010 — élèves (direction, PII).
    Route::get('/edu-manager/students', [EduStudentController::class, 'index']);
    Route::post('/edu-manager/students', [EduStudentController::class, 'store']);
    Route::get('/edu-manager/students/{student}', [EduStudentController::class, 'show'])->whereNumber('student');
    Route::put('/edu-manager/students/{student}', [EduStudentController::class, 'update'])->whereNumber('student');
    Route::delete('/edu-manager/students/{student}', [EduStudentController::class, 'destroy'])->whereNumber('student');

    // EDU-003/EDU-009/EDU-010 — classes + affectations enseignants.
    Route::get('/edu-manager/classes', [EduClassController::class, 'index']);
    Route::post('/edu-manager/classes', [EduClassController::class, 'store']);
    Route::get('/edu-manager/classes/{class}', [EduClassController::class, 'show'])->whereNumber('class');
    Route::put('/edu-manager/classes/{class}', [EduClassController::class, 'update'])->whereNumber('class');
    Route::delete('/edu-manager/classes/{class}', [EduClassController::class, 'destroy'])->whereNumber('class');
    Route::post('/edu-manager/classes/{class}/teachers', [EduClassController::class, 'assignTeacher'])->whereNumber('class');
    Route::delete('/edu-manager/teacher-subjects/{assignment}', [EduClassController::class, 'removeTeacher'])->whereNumber('assignment');

    // EDU-004/EDU-010 — admissions + conversion élève.
    Route::get('/edu-manager/admissions', [EduAdmissionController::class, 'index']);
    Route::post('/edu-manager/admissions', [EduAdmissionController::class, 'store']);
    Route::get('/edu-manager/admissions/{admission}', [EduAdmissionController::class, 'show'])->whereNumber('admission');
    Route::post('/edu-manager/admissions/{admission}/convert', [EduAdmissionController::class, 'convert'])->whereNumber('admission');

    // EDU-005/EDU-010 — présence scolaire (idempotente, corrections versionnées).
    Route::get('/edu-manager/classes/{class}/attendances', [EduAttendanceController::class, 'index'])->whereNumber('class');
    Route::post('/edu-manager/classes/{class}/attendances', [EduAttendanceController::class, 'store'])->whereNumber('class');
    Route::post('/edu-manager/attendances/{attendance}/correct', [EduAttendanceController::class, 'correct'])->whereNumber('attendance');

    // EDU-006/EDU-010 — emplois du temps (créneaux + conflits).
    Route::get('/edu-manager/course-slots', [EduCourseSlotController::class, 'index']);
    Route::post('/edu-manager/course-slots', [EduCourseSlotController::class, 'store']);
    Route::get('/edu-manager/course-slots/{slot}', [EduCourseSlotController::class, 'show'])->whereNumber('slot');
    Route::put('/edu-manager/course-slots/{slot}', [EduCourseSlotController::class, 'update'])->whereNumber('slot');
    Route::delete('/edu-manager/course-slots/{slot}', [EduCourseSlotController::class, 'destroy'])->whereNumber('slot');

    // EDU-007/EDU-010 — évaluations + notes versionnées.
    Route::get('/edu-manager/assessments', [EduAssessmentController::class, 'index']);
    Route::post('/edu-manager/assessments', [EduAssessmentController::class, 'store']);
    Route::get('/edu-manager/assessments/{assessment}', [EduAssessmentController::class, 'show'])->whereNumber('assessment');
    Route::put('/edu-manager/assessments/{assessment}', [EduAssessmentController::class, 'update'])->whereNumber('assessment');
    Route::delete('/edu-manager/assessments/{assessment}', [EduAssessmentController::class, 'destroy'])->whereNumber('assessment');
    Route::post('/edu-manager/assessments/{assessment}/grades', [EduAssessmentController::class, 'grade'])->whereNumber('assessment');
    Route::post('/edu-manager/grades/{grade}/publish', [EduAssessmentController::class, 'publishGrade'])->whereNumber('grade');
    Route::post('/edu-manager/grades/{grade}/correct', [EduAssessmentController::class, 'correctGrade'])->whereNumber('grade');

    // EDU-008/EDU-010 — bulletins (génération, validation, publication).
    Route::get('/edu-manager/report-cards', [EduReportCardController::class, 'index']);
    Route::post('/edu-manager/report-cards/generate', [EduReportCardController::class, 'generate']);
    Route::get('/edu-manager/report-cards/{card}', [EduReportCardController::class, 'show'])->whereNumber('card');
    Route::post('/edu-manager/report-cards/{card}/validate', [EduReportCardController::class, 'validate'])->whereNumber('card');
    Route::post('/edu-manager/report-cards/{card}/publish', [EduReportCardController::class, 'publish'])->whereNumber('card');

    // EDU-011 (#5827) — tableau de bord de l'administration scolaire.
    Route::get('/edu-manager/dashboard', [EduDashboardController::class, 'index']);

    // EDU-011 (#5827) — inscriptions d'élèves dans les classes.
    Route::get('/edu-manager/classes/{class}/enrollments', [EduClassEnrollmentController::class, 'index'])->whereNumber('class');
    Route::post('/edu-manager/classes/{class}/enrollments', [EduClassEnrollmentController::class, 'store'])->whereNumber('class');
    Route::delete('/edu-manager/class-enrollments/{enrollment}', [EduClassEnrollmentController::class, 'destroy'])->whereNumber('enrollment');

    // EDU-012 (#5828) — espace enseignant (classes, effectifs, saisie).
    Route::get('/edu-manager/teacher/workspace', [EduTeacherWorkspaceController::class, 'index']);

    // EDU-015 (#5831) — marketing admissions : relances consenties + opt-out.
    Route::post('/edu-manager/admissions/{admission}/follow-ups', [EduAdmissionCampaignController::class, 'followUp'])->whereNumber('admission');
    Route::post('/edu-manager/admissions/{admission}/opt-out', [EduAdmissionCampaignController::class, 'optOut'])->whereNumber('admission');

    // EDU-016 (#5832) — frais scolaires + contrat Accounting.
    Route::get('/edu-manager/fee-types', [EduFeeController::class, 'indexFeeTypes']);
    Route::post('/edu-manager/fee-types', [EduFeeController::class, 'storeFeeType']);
    Route::get('/edu-manager/fee-charges', [EduFeeController::class, 'indexCharges']);
    Route::post('/edu-manager/fee-charges', [EduFeeController::class, 'storeCharge']);
    Route::post('/edu-manager/fee-charges/{charge}/payments', [EduFeeController::class, 'storePayment'])->whereNumber('charge');
    Route::post('/edu-manager/fee-charges/{charge}/waive', [EduFeeController::class, 'waive'])->whereNumber('charge');
    Route::post('/edu-manager/fee-charges/{charge}/cancel', [EduFeeController::class, 'cancel'])->whereNumber('charge');
    Route::get('/edu-manager/fee-accounting-entries', [EduFeeController::class, 'indexEntries']);

    // EDU-013 (#5829) — génération d'un lien de portail guardian (direction).
    Route::post('/edu-manager/guardians/{guardian}/portal-link', [EduGuardianPortalController::class, 'createLink'])->whereNumber('guardian');
});

/**
 * Portail guardian — route PUBLIQUE (EDU-013, #5829) : consultation du
 * résumé via `portal_token` (64 caractères — le token EST la credential,
 * pattern AccountingDocumentShare #5428). Pas d'auth Sanctum ni de
 * TenantMiddleware : résolution O(1) + expiration/révocation + audit
 * (edu_portal_access_logs). Throttle dédié 60/min.
 */
Route::get('/edu-manager/portal/{token}', [EduGuardianPortalController::class, 'summary'])
    ->middleware('throttle:60,1');
