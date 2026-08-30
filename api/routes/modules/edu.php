<?php

declare(strict_types=1);

/**
 * Routes EduManager (BC-16) — EDU-003/005/007/009/010 (issues #5819/#5821/#5823/#5825/#5826).
 *
 * Toutes les routes sont tenant-scoped (auth:sanctum + tenant middleware).
 * RBAC (EDU-009) : direction/administration = manager (policies deny-by-default) ;
 * enseignant = ses classes uniquement (EduTeacherAssignment) ; guardian =
 * endpoint dédié (batch UI guardian, non exposé ici).
 */

use App\Modules\EduManager\Interfaces\Api\V1\Controllers\EduAcademicYearController;
use App\Modules\EduManager\Interfaces\Api\V1\Controllers\EduAttendanceController;
use App\Modules\EduManager\Interfaces\Api\V1\Controllers\EduClassController;
use App\Modules\EduManager\Interfaces\Api\V1\Controllers\EduEvaluationController;
use App\Modules\EduManager\Interfaces\Api\V1\Controllers\EduSubjectController;
use App\Modules\EduManager\Interfaces\Api\V1\Controllers\EduTeacherController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:api', 'auth:sanctum', 'token.refresh', 'tenant', 'throttle:api-plan'])->group(function (): void {
    // EDU-003/010 — référentiel scolaire (lecture : tout employé du tenant).
    Route::get('/edu/academic-years', [EduAcademicYearController::class, 'index']);
    Route::get('/edu/classes', [EduClassController::class, 'index']);
    Route::get('/edu/subjects', [EduSubjectController::class, 'index']);
    Route::get('/edu/teachers', [EduTeacherController::class, 'index']);

    // EDU-005/010 — présence (direction + enseignant de la classe).
    Route::get('/edu/attendance', [EduAttendanceController::class, 'index']);
    Route::post('/edu/attendance', [EduAttendanceController::class, 'record']);
    Route::post('/edu/attendance/{record}/correct', [EduAttendanceController::class, 'correct'])->whereNumber('record');

    // EDU-007/010 — évaluations & notes.
    Route::get('/edu/evaluations', [EduEvaluationController::class, 'index']);
    Route::post('/edu/evaluations', [EduEvaluationController::class, 'store']);
    Route::get('/edu/evaluations/{evaluation}/grades', [EduEvaluationController::class, 'grades'])->whereNumber('evaluation');
    Route::post('/edu/evaluations/{evaluation}/grades', [EduEvaluationController::class, 'enterGrade'])->whereNumber('evaluation');
    Route::post('/edu/evaluations/{evaluation}/publish', [EduEvaluationController::class, 'publish'])->whereNumber('evaluation');
    Route::post('/edu/grades/{entry}/correct', [EduEvaluationController::class, 'correctGrade'])->whereNumber('entry');

    // Administration (direction) — CRUD référentiel.
    Route::middleware('api.manager')->group(function (): void {
        Route::post('/edu/academic-years', [EduAcademicYearController::class, 'store']);
        Route::put('/edu/academic-years/{year}', [EduAcademicYearController::class, 'update'])->whereNumber('year');
        Route::post('/edu/classes', [EduClassController::class, 'store']);
        Route::put('/edu/classes/{class}', [EduClassController::class, 'update'])->whereNumber('class');
        Route::post('/edu/subjects', [EduSubjectController::class, 'store']);
        Route::put('/edu/subjects/{subject}', [EduSubjectController::class, 'update'])->whereNumber('subject');
        Route::post('/edu/teachers', [EduTeacherController::class, 'store']);
        Route::post('/edu/teachers/{teacher}/assignments', [EduTeacherController::class, 'assign'])->whereNumber('teacher');
    });
});
