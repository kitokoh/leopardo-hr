<?php

/**
 * Routes RH etendues — Modules congés avancés, contrats, recrutement,
 * formation, prêts, frais, organigramme, rapports, webhooks, audit.
 *
 * RBAC:
 *   - /me/* routes: all authenticated employees
 *   - Admin routes (policies, contracts CRUD, recruitment, webhooks, audit): managers
 *   - Reports, audit and predictions: all managers; controller policies still apply where relevant
 *   - Org chart: all authenticated (read-only)
 *   - Approval actions: context-dependent (manager for workflows, all for own approvals)
 */

use App\Http\Controllers\Api\V1\AdvancedReportController;
use App\Http\Controllers\Api\V1\ApprovalController;
use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\ContractController;
use App\Http\Controllers\Api\V1\EmployeeLoanController;
use App\Http\Controllers\Api\V1\ExpenseClaimController;
use App\Http\Controllers\Api\V1\HrReportController;
use App\Http\Controllers\Api\V1\JobPostingActionController;
use App\Http\Controllers\Api\V1\LeavePolicyController;
use App\Http\Controllers\Api\V1\OrgChartController;
use App\Http\Controllers\Api\V1\PredictionController;
use App\Http\Controllers\Api\V1\RecruitmentController;
use App\Http\Controllers\Api\V1\SelfServiceController;
use App\Http\Controllers\Api\V1\TrainingController;
use App\Http\Controllers\Api\V1\WebhookController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:api', 'auth:sanctum', 'tenant', 'throttle:api-plan'])->group(function (): void {

    // ── Self-Service (all employees) ─────────────────────────────────────
    Route::get('/me/leave-balances', [LeavePolicyController::class, 'myBalances']);
    Route::get('/me/contracts', [ContractController::class, 'myContracts']);
    Route::get('/me/trainings', [SelfServiceController::class, 'myTrainings']);
    Route::post('/me/trainings/{sessionId}/enroll', [SelfServiceController::class, 'selfEnroll'])->whereNumber('sessionId');
    Route::get('/me/loans', [SelfServiceController::class, 'myLoans']);
    Route::get('/me/loans/{loanId}/repayments', [SelfServiceController::class, 'myLoanRepayments'])->whereNumber('loanId');

    // ── Org Chart (all employees, read-only) ─────────────────────────────
    Route::get('/org-chart', [OrgChartController::class, 'index']);
    Route::get('/org-chart/{employee}/subordinates', [OrgChartController::class, 'subordinates'])->whereNumber('employee');
    Route::get('/org-chart/{employee}/manager-chain', [OrgChartController::class, 'managerChain'])->whereNumber('employee');

    // ── Expense Claims (employees can submit, managers approve) ──────────
    Route::get('/expense-claims', [ExpenseClaimController::class, 'index']);
    Route::post('/expense-claims', [ExpenseClaimController::class, 'store']);
    Route::get('/expense-claims/{expenseClaim}', [ExpenseClaimController::class, 'show']);
    Route::put('/expense-claims/{expenseClaim}/submit', [ExpenseClaimController::class, 'submit']);

    // ── Approval actions (employee can see their pending, managers approve) ──
    Route::get('/approvals/pending', [ApprovalController::class, 'pending']);
    Route::post('/approvals/{approvalRequest}/approve', [ApprovalController::class, 'approve']);
    Route::post('/approvals/{approvalRequest}/reject', [ApprovalController::class, 'reject']);
    Route::get('/approvals/history', [ApprovalController::class, 'history']);

    // ── Training read (all employees can see courses) ────────────────────
    Route::get('/training/courses', [TrainingController::class, 'indexCourses']);
    Route::get('/training/courses/{trainingCourse}', [TrainingController::class, 'showCourse']);
    Route::get('/training/courses/{trainingCourse}/sessions', [TrainingController::class, 'indexSessions']);
    Route::post('/training/sessions/{trainingSession}/enroll', [TrainingController::class, 'enroll']);

    // ── Loan read (employees can see their loans) ────────────────────────
    Route::get('/loans', [EmployeeLoanController::class, 'index']);
    Route::post('/loans', [EmployeeLoanController::class, 'store']);
    Route::get('/loans/{employeeLoan}', [EmployeeLoanController::class, 'show']);

    // ══════════════════════════════════════════════════════════════════════
    //   MANAGER-ONLY ROUTES (api.manager middleware)
    // ══════════════════════════════════════════════════════════════════════
    Route::middleware('api.manager')->group(function (): void {

        // ── Leave Policies (principal, rh) ───────────────────────────────
        Route::get('/leave-policies', [LeavePolicyController::class, 'index']);
        Route::post('/leave-policies', [LeavePolicyController::class, 'store']);
        Route::get('/leave-policies/{leavePolicy}', [LeavePolicyController::class, 'show']);
        Route::put('/leave-policies/{leavePolicy}', [LeavePolicyController::class, 'update']);
        Route::delete('/leave-policies/{leavePolicy}', [LeavePolicyController::class, 'destroy']);
        Route::get('/leave-balances', [LeavePolicyController::class, 'balances']);
        Route::get('/leave-accruals', [LeavePolicyController::class, 'accruals']);
        Route::post('/leave-accruals', [LeavePolicyController::class, 'storeAccrual']);

        // ── Contracts (CRUD, activate/suspend/terminate) ─────────────────
        Route::get('/contracts', [ContractController::class, 'index']);
        Route::post('/contracts', [ContractController::class, 'store']);
        Route::get('/contracts/expiring', [ContractController::class, 'expiring']);
        Route::get('/contracts/{contract}', [ContractController::class, 'show']);
        Route::put('/contracts/{contract}', [ContractController::class, 'update']);
        Route::post('/contracts/{contract}/activate', [ContractController::class, 'activate']);
        Route::post('/contracts/{contract}/suspend', [ContractController::class, 'suspend']);
        Route::post('/contracts/{contract}/terminate', [ContractController::class, 'terminate']);
        Route::post('/contracts/{contract}/renew', [ContractController::class, 'renew']);
        Route::get('/contracts/{contract}/amendments', [ContractController::class, 'amendments']);
        Route::post('/contracts/{contract}/amendments', [ContractController::class, 'storeAmendment']);
        Route::get('/contracts/{contract}/generate-pdf', [ContractController::class, 'generatePdf']);

        // ── Recruitment / ATS ────────────────────────────────────────────
        Route::get('/recruitment/jobs', [RecruitmentController::class, 'indexJobs']);
        Route::post('/recruitment/jobs', [RecruitmentController::class, 'storeJob']);
        Route::get('/recruitment/jobs/{jobPosting}', [RecruitmentController::class, 'showJob']);
        Route::put('/recruitment/jobs/{jobPosting}', [RecruitmentController::class, 'updateJob']);
        Route::get('/recruitment/jobs/{jobPosting}/applicants', [RecruitmentController::class, 'indexApplicants']);
        Route::post('/recruitment/jobs/{jobPosting}/applicants', [RecruitmentController::class, 'storeApplicant']);
        Route::put('/recruitment/applicants/{applicant}', [RecruitmentController::class, 'updateApplicant']);
        Route::post('/recruitment/applicants/{applicant}/interviews', [RecruitmentController::class, 'storeInterview']);
        Route::put('/recruitment/interviews/{interview}', [RecruitmentController::class, 'updateInterview']);

        Route::post('/recruitment/jobs/{id}/publish', [JobPostingActionController::class, 'publish'])->whereNumber('id');
        Route::post('/recruitment/jobs/{id}/close', [JobPostingActionController::class, 'close'])->whereNumber('id');
        Route::delete('/recruitment/jobs/{id}', [JobPostingActionController::class, 'destroy'])->whereNumber('id');
        Route::get('/recruitment/applicants/{id}', [JobPostingActionController::class, 'showApplicant'])->whereNumber('id');
        Route::patch('/recruitment/applicants/{id}/status', [JobPostingActionController::class, 'updateApplicantStatus'])->whereNumber('id');
        Route::delete('/recruitment/applicants/{id}', [JobPostingActionController::class, 'destroyApplicant'])->whereNumber('id');
        Route::patch('/recruitment/interviews/{id}/feedback', [JobPostingActionController::class, 'interviewFeedback'])->whereNumber('id');
        Route::delete('/recruitment/interviews/{id}', [JobPostingActionController::class, 'destroyInterview'])->whereNumber('id');

        // ── Training management ──────────────────────────────────────────
        Route::post('/training/courses', [TrainingController::class, 'storeCourse']);
        Route::put('/training/courses/{trainingCourse}', [TrainingController::class, 'updateCourse']);
        Route::post('/training/courses/{trainingCourse}/sessions', [TrainingController::class, 'storeSession']);
        Route::put('/training/sessions/{trainingSession}', [TrainingController::class, 'updateSession']);
        Route::put('/training/enrollments/{trainingEnrollment}', [TrainingController::class, 'updateEnrollment']);

        // ── Loan management ──────────────────────────────────────────────
        Route::put('/loans/{employeeLoan}/approve', [EmployeeLoanController::class, 'approve']);
        Route::put('/loans/{employeeLoan}/disburse', [EmployeeLoanController::class, 'disburse']);

        // ── Expense approval ─────────────────────────────────────────────
        Route::put('/expense-claims/{expenseClaim}/approve', [ExpenseClaimController::class, 'approve']);
        Route::put('/expense-claims/{expenseClaim}/reject', [ExpenseClaimController::class, 'reject']);

        // ── Approval Workflows (principal, rh) ───────────────────────────
        Route::get('/approval-workflows', [ApprovalController::class, 'indexWorkflows']);
        Route::post('/approval-workflows', [ApprovalController::class, 'storeWorkflow']);
        Route::put('/approval-workflows/{approvalWorkflow}', [ApprovalController::class, 'updateWorkflow']);
        Route::delete('/approval-workflows/{approvalWorkflow}', [ApprovalController::class, 'destroyWorkflow']);
    });

    // ══════════════════════════════════════════════════════════════════════
    //   ADMIN ROUTES (all managers; controller policies still apply)
    // ══════════════════════════════════════════════════════════════════════
    Route::middleware('api.manager')->group(function (): void {

        // ── HR Reports ───────────────────────────────────────────────────
        Route::prefix('reports')->group(function (): void {
            Route::get('/headcount', [HrReportController::class, 'headcount']);
            Route::get('/turnover', [HrReportController::class, 'turnover']);
            Route::get('/absenteeism', [HrReportController::class, 'absenteeism']);
            Route::get('/payroll-summary', [HrReportController::class, 'payrollSummary']);
            Route::get('/overtime', [HrReportController::class, 'overtime']);

            // Advanced Reports
            Route::get('/recruitment-pipeline', [AdvancedReportController::class, 'recruitmentPipeline']);
            Route::get('/training-completion', [AdvancedReportController::class, 'trainingCompletion']);
            Route::get('/loan-summary', [AdvancedReportController::class, 'loanSummary']);
            Route::get('/demographics', [AdvancedReportController::class, 'demographicBreakdown']);
            Route::get('/cost-analysis', [AdvancedReportController::class, 'costAnalysis']);
        });

        // ── Webhooks ─────────────────────────────────────────────────────
        Route::get('/webhooks/events', [WebhookController::class, 'events']);
        Route::get('/webhooks', [WebhookController::class, 'index']);
        Route::post('/webhooks', [WebhookController::class, 'store']);
        Route::get('/webhooks/{webhookEndpoint}', [WebhookController::class, 'show']);
        Route::put('/webhooks/{webhookEndpoint}', [WebhookController::class, 'update']);
        Route::delete('/webhooks/{webhookEndpoint}', [WebhookController::class, 'destroy']);

        // ── Audit Trail ──────────────────────────────────────────────────
        Route::get('/audit-logs', [AuditLogController::class, 'index']);
        Route::get('/audit-logs/export-csv', [AuditLogController::class, 'exportCsv']);
        Route::get('/audit-logs/{auditLog}', [AuditLogController::class, 'show']);

        // ── IA Predictions ───────────────────────────────────────────────
        Route::prefix('predictions')->group(function (): void {
            Route::get('/turnover', [PredictionController::class, 'turnover']);
            Route::get('/absenteeism', [PredictionController::class, 'absenteeism']);
            Route::get('/notifications', [PredictionController::class, 'proactiveNotifications']);
        });
    });
});
