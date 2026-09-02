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
use App\Modules\EduManager\Interfaces\Api\V1\Controllers\EduAdmissionController;
use App\Modules\EduManager\Interfaces\Api\V1\Controllers\EduAssessmentController;
use App\Modules\EduManager\Interfaces\Api\V1\Controllers\EduAttendanceController;
use App\Modules\EduManager\Interfaces\Api\V1\Controllers\EduCampusController;
use App\Modules\EduManager\Interfaces\Api\V1\Controllers\EduClassController;
use App\Modules\EduManager\Interfaces\Api\V1\Controllers\EduCourseSlotController;
use App\Modules\EduManager\Interfaces\Api\V1\Controllers\EduFeeController;
use App\Modules\EduManager\Interfaces\Api\V1\Controllers\EduGuardianPortalController;
use App\Modules\EduManager\Interfaces\Api\V1\Controllers\EduImportExportController;
use App\Modules\EduManager\Interfaces\Api\V1\Controllers\EduMarketingController;
use App\Modules\EduManager\Interfaces\Api\V1\Controllers\EduNotificationController;
use App\Modules\EduManager\Interfaces\Api\V1\Controllers\EduReportCardController;
use App\Modules\EduManager\Interfaces\Api\V1\Controllers\EduReportController;
use App\Modules\EduManager\Interfaces\Api\V1\Controllers\EduRetentionController;
use App\Modules\EduManager\Interfaces\Api\V1\Controllers\EduStudentController;
use App\Modules\EduManager\Interfaces\Api\V1\Controllers\EduSubjectController;
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
    Route::post('/edu-manager/report-cards/{card}/validate', [EduReportCardController::class, 'validateCard'])->whereNumber('card');
    Route::post('/edu-manager/report-cards/{card}/publish', [EduReportCardController::class, 'publish'])->whereNumber('card');

    // EDU-017 (#5833) — import/export sécurisé (direction).
    Route::post('/edu-manager/imports/preview', [EduImportExportController::class, 'preview']);
    Route::post('/edu-manager/imports/{import}/commit', [EduImportExportController::class, 'commit'])->whereNumber('import');
    Route::post('/edu-manager/imports/{import}/cancel', [EduImportExportController::class, 'cancel'])->whereNumber('import');
    Route::get('/edu-manager/exports/{kind}', [EduImportExportController::class, 'export']);

    // EDU-018 (#5834) — rapports agrégés (direction, aucun détail nominatif).
    Route::get('/edu-manager/reports/presence', [EduReportController::class, 'presence']);
    Route::get('/edu-manager/reports/enrollment', [EduReportController::class, 'enrollment']);
    Route::get('/edu-manager/reports/results', [EduReportController::class, 'results']);
    Route::get('/edu-manager/reports/capacity', [EduReportController::class, 'capacity']);

    // EDU-019 (#5835) — RGPD : anonymisation + export individuel (direction).
    Route::post('/edu-manager/students/{student}/anonymize', [EduRetentionController::class, 'anonymize'])->whereNumber('student');
    Route::get('/edu-manager/students/{student}/privacy-export', [EduRetentionController::class, 'privacyExport'])->whereNumber('student');

    // EDU-016 (#5832) — frais scolaires (direction ; contrat Accounting read model).
    Route::get('/edu-manager/fees', [EduFeeController::class, 'index']);
    Route::post('/edu-manager/fees', [EduFeeController::class, 'store']);
    Route::get('/edu-manager/fees/{fee}', [EduFeeController::class, 'show'])->whereNumber('fee');
    Route::post('/edu-manager/fees/{fee}/pay', [EduFeeController::class, 'pay'])->whereNumber('fee');
    Route::post('/edu-manager/fees/{fee}/cancel', [EduFeeController::class, 'cancel'])->whereNumber('fee');
    Route::post('/edu-manager/fees/{fee}/waive', [EduFeeController::class, 'waive'])->whereNumber('fee');

    // EDU-015 (#5831) — marketing admissions (segments consentis + opt-out).
    Route::get('/edu-manager/marketing/eligible-prospects', [EduMarketingController::class, 'eligibleProspects']);
    Route::post('/edu-manager/admissions/{admission}/opt-out', [EduMarketingController::class, 'optOut'])->whereNumber('admission');

    // EDU-014 (#5830) — notifications EduManager (lecture direction).
    Route::get('/edu-manager/notifications', [EduNotificationController::class, 'index']);

    // EDU-013 (#5829) — portail guardian (profil, enfants, présence, bulletins, liens expirables).
    Route::get('/edu-manager/guardians/me', [EduGuardianPortalController::class, 'me']);
    Route::get('/edu-manager/guardians/me/students', [EduGuardianPortalController::class, 'students']);
    Route::get('/edu-manager/guardians/me/students/{student}/presences', [EduGuardianPortalController::class, 'presences'])->whereNumber('student');
    Route::get('/edu-manager/guardians/me/students/{student}/report-cards', [EduGuardianPortalController::class, 'reportCards'])->whereNumber('student');
    Route::post('/edu-manager/guardians/access-links', [EduGuardianPortalController::class, 'issueLink']);
    Route::post('/edu-manager/guardians/access-links/redeem', [EduGuardianPortalController::class, 'redeem']);
});
