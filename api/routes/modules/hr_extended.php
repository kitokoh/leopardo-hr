<?php

/**
 * Routes RH etendues — Modules congés avancés, contrats, recrutement,
 * formation, prêts, frais, organigramme, rapports, webhooks, audit.
 */

use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\ContractController;
use App\Http\Controllers\Api\V1\EmployeeLoanController;
use App\Http\Controllers\Api\V1\ExpenseClaimController;
use App\Http\Controllers\Api\V1\HrReportController;
use App\Http\Controllers\Api\V1\LeavePolicyController;
use App\Http\Controllers\Api\V1\OrgChartController;
use App\Http\Controllers\Api\V1\RecruitmentController;
use App\Http\Controllers\Api\V1\TrainingController;
use App\Http\Controllers\Api\V1\WebhookController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:api', 'auth:sanctum', 'tenant'])->group(function (): void {

    // ── Module A — Leave Policies & Balances ────────────────────────────────
    Route::get('/leave-policies', [LeavePolicyController::class, 'index']);
    Route::post('/leave-policies', [LeavePolicyController::class, 'store']);
    Route::get('/leave-policies/{leavePolicy}', [LeavePolicyController::class, 'show']);
    Route::put('/leave-policies/{leavePolicy}', [LeavePolicyController::class, 'update']);
    Route::get('/leave-balances', [LeavePolicyController::class, 'balances']);

    // ── Module B — Contracts ────────────────────────────────────────────────
    Route::get('/contracts', [ContractController::class, 'index']);
    Route::post('/contracts', [ContractController::class, 'store']);
    Route::get('/contracts/expiring', [ContractController::class, 'expiring']);
    Route::get('/contracts/{contract}', [ContractController::class, 'show']);
    Route::put('/contracts/{contract}', [ContractController::class, 'update']);
    Route::get('/contracts/{contract}/amendments', [ContractController::class, 'amendments']);
    Route::post('/contracts/{contract}/amendments', [ContractController::class, 'storeAmendment']);

    // ── Module C — Recruitment / ATS ────────────────────────────────────────
    Route::get('/recruitment/jobs', [RecruitmentController::class, 'indexJobs']);
    Route::post('/recruitment/jobs', [RecruitmentController::class, 'storeJob']);
    Route::get('/recruitment/jobs/{jobPosting}', [RecruitmentController::class, 'showJob']);
    Route::put('/recruitment/jobs/{jobPosting}', [RecruitmentController::class, 'updateJob']);
    Route::get('/recruitment/jobs/{jobPosting}/applicants', [RecruitmentController::class, 'indexApplicants']);
    Route::post('/recruitment/jobs/{jobPosting}/applicants', [RecruitmentController::class, 'storeApplicant']);
    Route::put('/recruitment/applicants/{applicant}', [RecruitmentController::class, 'updateApplicant']);
    Route::post('/recruitment/applicants/{applicant}/interviews', [RecruitmentController::class, 'storeInterview']);
    Route::put('/recruitment/interviews/{interview}', [RecruitmentController::class, 'updateInterview']);

    // ── Module D — Training / LMS ───────────────────────────────────────────
    Route::get('/training/courses', [TrainingController::class, 'indexCourses']);
    Route::post('/training/courses', [TrainingController::class, 'storeCourse']);
    Route::get('/training/courses/{trainingCourse}', [TrainingController::class, 'showCourse']);
    Route::put('/training/courses/{trainingCourse}', [TrainingController::class, 'updateCourse']);
    Route::get('/training/courses/{trainingCourse}/sessions', [TrainingController::class, 'indexSessions']);
    Route::post('/training/courses/{trainingCourse}/sessions', [TrainingController::class, 'storeSession']);
    Route::put('/training/sessions/{trainingSession}', [TrainingController::class, 'updateSession']);
    Route::post('/training/sessions/{trainingSession}/enroll', [TrainingController::class, 'enroll']);
    Route::put('/training/enrollments/{trainingEnrollment}', [TrainingController::class, 'updateEnrollment']);

    // ── Module E — Employee Loans ───────────────────────────────────────────
    Route::get('/loans', [EmployeeLoanController::class, 'index']);
    Route::post('/loans', [EmployeeLoanController::class, 'store']);
    Route::get('/loans/{employeeLoan}', [EmployeeLoanController::class, 'show']);
    Route::put('/loans/{employeeLoan}/approve', [EmployeeLoanController::class, 'approve']);
    Route::put('/loans/{employeeLoan}/disburse', [EmployeeLoanController::class, 'disburse']);

    // ── Module F — Expense Claims ───────────────────────────────────────────
    Route::get('/expense-claims', [ExpenseClaimController::class, 'index']);
    Route::post('/expense-claims', [ExpenseClaimController::class, 'store']);
    Route::get('/expense-claims/{expenseClaim}', [ExpenseClaimController::class, 'show']);
    Route::put('/expense-claims/{expenseClaim}/submit', [ExpenseClaimController::class, 'submit']);
    Route::put('/expense-claims/{expenseClaim}/approve', [ExpenseClaimController::class, 'approve']);
    Route::put('/expense-claims/{expenseClaim}/reject', [ExpenseClaimController::class, 'reject']);

    // ── Module G — Org Chart ────────────────────────────────────────────────
    Route::get('/org-chart', [OrgChartController::class, 'index']);
    Route::get('/org-chart/{employee}/subordinates', [OrgChartController::class, 'subordinates'])->whereNumber('employee');
    Route::get('/org-chart/{employee}/manager-chain', [OrgChartController::class, 'managerChain'])->whereNumber('employee');

    // ── Module H — HR Reports ───────────────────────────────────────────────
    Route::prefix('reports')->group(function (): void {
        Route::get('/headcount', [HrReportController::class, 'headcount']);
        Route::get('/turnover', [HrReportController::class, 'turnover']);
        Route::get('/absenteeism', [HrReportController::class, 'absenteeism']);
        Route::get('/payroll-summary', [HrReportController::class, 'payrollSummary']);
        Route::get('/overtime', [HrReportController::class, 'overtime']);
    });

    // ── Module I — Webhooks ─────────────────────────────────────────────────
    Route::get('/webhooks/events', [WebhookController::class, 'events']);
    Route::get('/webhooks', [WebhookController::class, 'index']);
    Route::post('/webhooks', [WebhookController::class, 'store']);
    Route::get('/webhooks/{webhookEndpoint}', [WebhookController::class, 'show']);
    Route::put('/webhooks/{webhookEndpoint}', [WebhookController::class, 'update']);
    Route::delete('/webhooks/{webhookEndpoint}', [WebhookController::class, 'destroy']);

    // ── Module J — Audit Trail ──────────────────────────────────────────────
    Route::get('/audit-logs', [AuditLogController::class, 'index']);
    Route::get('/audit-logs/{auditLog}', [AuditLogController::class, 'show']);
});
