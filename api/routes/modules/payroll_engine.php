<?php

declare(strict_types=1);

/**
 * Routes Payroll Engine — salaires, composants, bulletins, exports bancaires.
 *
 * Namespaces migrés vers App\Modules\Payroll\* (nouvelle architecture modulaire).
 *
 * RBAC:
 *   - /me/pay-slips: all employees (own pay slips)
 *   - Cotisation simulation: all employees
 *   - Everything else: managers (principal, comptable)
 */

use App\Modules\Payroll\Interfaces\Api\V1\BankExportController;
use App\Modules\Payroll\Interfaces\Api\V1\BulkPaymentController;
use App\Modules\Payroll\Interfaces\Api\V1\CotisationSimulationController;
use App\Modules\Payroll\Interfaces\Api\V1\PaymentBatchController;
use App\Modules\Payroll\Interfaces\Api\V1\PaymentDocumentController;
use App\Modules\Payroll\Interfaces\Api\V1\PayrollAuditController;
use App\Modules\Payroll\Interfaces\Api\V1\PayrollCycleController;
use App\Modules\Payroll\Interfaces\Api\V1\PayrollRunController;
use App\Modules\Payroll\Interfaces\Api\V1\PayrollSimulationController;
use App\Modules\Payroll\Interfaces\Api\V1\PaySlipController;
use App\Modules\Payroll\Interfaces\Api\V1\PublicHolidayController;
use App\Modules\Payroll\Interfaces\Api\V1\SalaryComponentController;
use App\Modules\Payroll\Interfaces\Api\V1\SalaryStructureController;
use App\Modules\Payroll\Interfaces\Api\V1\SocialContributionController;
use App\Modules\Payroll\Interfaces\Api\V1\SocialDeclarationController;
use App\Modules\Payroll\Interfaces\Api\V1\TaxSlabController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:api', 'auth:sanctum', 'token.refresh', 'tenant', 'throttle:api-plan', 'throttle:payroll-sensitive'])->group(function (): void {

    // ── Self-service pay slips (all employees) ───────────────────────────
    Route::get('/me/pay-slips', [PaySlipController::class, 'myPaySlips']);
    Route::get('/me/pay-slips/{paySlip}', [PaySlipController::class, 'myPaySlipDetail'])->whereNumber('paySlip');
    Route::get('/me/pay-slips/{paySlip}/pdf', [PaySlipController::class, 'downloadPdf'])->whereNumber('paySlip');
    // Issue #1817 — bulletin archivé dans le Cabinet employé (après clôture).
    Route::get('/me/pay-slips/{paySlip}/document', [PaySlipController::class, 'document'])->whereNumber('paySlip');
    Route::get('/me/payment-documents', [PaymentDocumentController::class, 'myDocuments']);
    Route::get('/me/payment-documents/{paymentDocument}/download', [PaymentDocumentController::class, 'download'])->whereNumber('paymentDocument');
    Route::post('/payment-confirmations/{paymentItem}/confirm', [PaymentBatchController::class, 'confirm'])->whereNumber('paymentItem');

    // ── Cotisation Simulation (all employees) ────────────────────────────
    Route::post('/cotisation-simulation', [CotisationSimulationController::class, 'simulate']);

    // ── Manager-only payroll routes (principal, comptable) ───────────────
    // MULTI-PAYS (#1867) : tenant.country — le pays légal du tenant doit être
    // présent et supporté avant toute écriture paie (runs, structures, barèmes).
    Route::middleware(['api.manager:principal,comptable', 'tenant.country'])->group(function (): void {

        // Salary Structures
        Route::get('/salary-structures', [SalaryStructureController::class, 'index']);
        Route::post('/salary-structures', [SalaryStructureController::class, 'store']);
        Route::get('/salary-structures/{salaryStructure}', [SalaryStructureController::class, 'show'])->whereNumber('salaryStructure');
        Route::put('/salary-structures/{salaryStructure}', [SalaryStructureController::class, 'update'])->whereNumber('salaryStructure');
        Route::delete('/salary-structures/{salaryStructure}', [SalaryStructureController::class, 'destroy'])->whereNumber('salaryStructure');

        // Salary Components
        Route::get('/salary-components', [SalaryComponentController::class, 'index']);
        Route::post('/salary-components', [SalaryComponentController::class, 'store']);
        Route::get('/salary-components/{salaryComponent}', [SalaryComponentController::class, 'show'])->whereNumber('salaryComponent');
        Route::put('/salary-components/{salaryComponent}', [SalaryComponentController::class, 'update'])->whereNumber('salaryComponent');
        Route::delete('/salary-components/{salaryComponent}', [SalaryComponentController::class, 'destroy'])->whereNumber('salaryComponent');

        // Public holidays (issue #1811) — manager principal : CRUD entreprise
        // uniquement (les fériés nationaux sont en lecture seule).
        Route::get('/public-holidays', [PublicHolidayController::class, 'index']);
        Route::post('/public-holidays', [PublicHolidayController::class, 'store']);
        Route::put('/public-holidays/{publicHoliday}', [PublicHolidayController::class, 'update'])->whereNumber('publicHoliday');
        Route::delete('/public-holidays/{publicHoliday}', [PublicHolidayController::class, 'destroy'])->whereNumber('publicHoliday');

        // Issue #1814 — simulation d'impact (dry-run, manager).
        Route::post('/payroll/simulate', [PayrollSimulationController::class, 'simulate']);

        // Tax Slabs
        Route::get('/tax-slabs', [TaxSlabController::class, 'index']);
        Route::post('/tax-slabs', [TaxSlabController::class, 'store']);
        Route::put('/tax-slabs/{taxSlab}', [TaxSlabController::class, 'update'])->whereNumber('taxSlab');
        Route::delete('/tax-slabs/{taxSlab}', [TaxSlabController::class, 'destroy'])->whereNumber('taxSlab');
        // Issue #1813 — workflow de validation des taux légaux.
        Route::put('/tax-slabs/{taxSlab}/submit', [TaxSlabController::class, 'submit'])->whereNumber('taxSlab');
        Route::get('/tax-slabs/{taxSlab}/history', [TaxSlabController::class, 'history'])->whereNumber('taxSlab');

        // Social Contributions
        Route::get('/social-contributions', [SocialContributionController::class, 'index']);
        Route::post('/social-contributions', [SocialContributionController::class, 'store']);
        Route::put('/social-contributions/{socialContribution}', [SocialContributionController::class, 'update'])->whereNumber('socialContribution');
        Route::delete('/social-contributions/{socialContribution}', [SocialContributionController::class, 'destroy'])->whereNumber('socialContribution');
        // Issue #1813 — workflow de validation des taux légaux.
        Route::put('/social-contributions/{socialContribution}/submit', [SocialContributionController::class, 'submit'])->whereNumber('socialContribution');
        Route::get('/social-contributions/{socialContribution}/history', [SocialContributionController::class, 'history'])->whereNumber('socialContribution');

        // Payroll Runs
        Route::get('/payroll-runs', [PayrollRunController::class, 'index']);
        Route::post('/payroll-runs', [PayrollRunController::class, 'store']);
        Route::get('/payroll-runs/{payrollRun}', [PayrollRunController::class, 'show'])->whereNumber('payrollRun');
        Route::post('/payroll-runs/{payrollRun}/calculate', [PayrollRunController::class, 'calculate'])->whereNumber('payrollRun');
        Route::post('/payroll-runs/{payrollRun}/validate', [PayrollRunController::class, 'validateRun'])->whereNumber('payrollRun');
        Route::post('/payroll-runs/{payrollRun}/lock', [PayrollRunController::class, 'lock'])->whereNumber('payrollRun');
        Route::post('/payroll-runs/{payrollRun}/unlock', [PayrollRunController::class, 'unlock'])->whereNumber('payrollRun');
        Route::post('/payroll-runs/{payrollRun}/cancel', [PayrollRunController::class, 'cancel'])->whereNumber('payrollRun');
        // DZ-DEPTH (#1818) — bulletins rétroactifs et régularisations.
        Route::post('/payroll-runs/{payrollRun}/regularize', [PayrollRunController::class, 'regularize'])->whereNumber('payrollRun');
        Route::get('/payroll-runs/{payrollRun}/regularizations', [PayrollRunController::class, 'regularizations'])->whereNumber('payrollRun');
        Route::get('/payroll-runs/{payrollRun}/summary', [PayrollRunController::class, 'summary'])->whereNumber('payrollRun');
        Route::get('/payroll-runs/{payrollRun}/anomalies', [PayrollRunController::class, 'anomalies'])->whereNumber('payrollRun');
        Route::get('/payroll-runs/{payrollRun}/export', [PayrollRunController::class, 'export'])->whereNumber('payrollRun');
        Route::get('/payroll-runs/{payrollRun}/journal', [PayrollRunController::class, 'journal'])->whereNumber('payrollRun');
        // #5243 — Bordereau de paie (totaux par cotisation + récap run).
        Route::get('/payroll-runs/{payrollRun}/bordereau', [PayrollRunController::class, 'bordereau'])->whereNumber('payrollRun');

        // Pay Slips (manager view)
        Route::get('/pay-slips', [PaySlipController::class, 'index']);
        Route::get('/payroll-runs/{payrollRun}/pay-slips', [PaySlipController::class, 'indexForRun'])->whereNumber('payrollRun');
        Route::post('/payroll-runs/{payrollRun}/send-slips', [PaySlipController::class, 'sendSlips'])->whereNumber('payrollRun');
        Route::get('/pay-slips/{paySlip}', [PaySlipController::class, 'show'])->whereNumber('paySlip');

        Route::post('/payroll-runs/{payrollRun}/bank-export', [BankExportController::class, 'generate'])->whereNumber('payrollRun');
        // Issue #2267 : contrat OpenAPI GET/POST /bank-exports (liste + génération).
        Route::get('/bank-exports', [BankExportController::class, 'index']);
        Route::post('/bank-exports', [BankExportController::class, 'store']);
        Route::get('/bank-exports/{bankExport}', [BankExportController::class, 'show'])->whereNumber('bankExport');
        Route::get('/bank-exports/{bankExport}/download', [BankExportController::class, 'download'])->whereNumber('bankExport');

        // Social Declarations — CNPS Cameroun DAS (CEMAC/CM #1823)
        Route::get('/payroll-runs/{payrollRun}/declarations/cnps-cm', [SocialDeclarationController::class, 'generateCnpsCmDeclaration'])->whereNumber('payrollRun');

        // Social Declarations (CNAS DZ / CNSS MA / DSN FR)
        Route::post('/social-declarations/cnas-dz', [SocialDeclarationController::class, 'generateCnasDz']);
        Route::post('/social-declarations/cnss-ma', [SocialDeclarationController::class, 'generateCnssMa']);
        Route::post('/social-declarations/dsn-fr', [SocialDeclarationController::class, 'generateDsnFr']);
        // #5243 — Déclaration Annuelle des Salaires DZ (CSV annuel).
        Route::post('/social-declarations/das-dz', [SocialDeclarationController::class, 'generateDasDz']);

        // Social Declarations — CNSS CI + IPRES/CSS SN (CEDEAO #1830)
        Route::get('/payroll-runs/{payrollRun}/declarations/cnss-ci', [SocialDeclarationController::class, 'generateCnssCiDeclaration'])->whereNumber('payrollRun');
        Route::get('/payroll-runs/{payrollRun}/declarations/ipres-sn', [SocialDeclarationController::class, 'generateIpresSnDeclaration'])->whereNumber('payrollRun');
        // CEMAC (#2155) — CNSS Gabon (GA) + Congo (CG)
        Route::get('/payroll-runs/{payrollRun}/declarations/cnss-ga', [SocialDeclarationController::class, 'generateCnssGaDeclaration'])->whereNumber('payrollRun');
        Route::get('/payroll-runs/{payrollRun}/declarations/cnss-cg', [SocialDeclarationController::class, 'generateCnssCgDeclaration'])->whereNumber('payrollRun');

        // CEDEAO (#2158) — CNSS Burkina Faso + INPS Mali
        Route::get('/payroll-runs/{payrollRun}/declarations/cnss-bf', [SocialDeclarationController::class, 'generateCnssBfDeclaration'])->whereNumber('payrollRun');
        Route::get('/payroll-runs/{payrollRun}/declarations/inps-ml', [SocialDeclarationController::class, 'generateInpsMlDeclaration'])->whereNumber('payrollRun');

        // Plan 61 — Payroll cycles
        Route::get('/payroll/cycles', [PayrollCycleController::class, 'index']);
        Route::get('/payroll/cycles/current', [PayrollCycleController::class, 'current']);

        // PA2-PAY-011 — Configurable company pay cycle rule (daily/weekly/monthly)
        Route::get('/payroll/cycle-settings', [PayrollCycleController::class, 'cycleSettings']);
        Route::put('/payroll/cycle-settings', [PayrollCycleController::class, 'updateCycleSettings']);

        // PA2-PAY-003 — Manager preview of a candidate pay cycle rule before saving it
        Route::get('/payroll/cycles/preview', [PayrollCycleController::class, 'preview']);

        // Plan 65 — Bulk payment
        Route::get('/payment-batches', [PaymentBatchController::class, 'index']);
        Route::post('/payment-batches', [PaymentBatchController::class, 'store']);
        Route::get('/payment-batches/{paymentBatch}', [PaymentBatchController::class, 'show'])->whereNumber('paymentBatch');
        Route::post('/payment-batches/{paymentBatch}/mark-paid', [PaymentBatchController::class, 'markPaid'])->whereNumber('paymentBatch');
        Route::post('/payroll-runs/{payrollRun}/bulk-pay', [BulkPaymentController::class, 'bulkPay'])->whereNumber('payrollRun');
        Route::get('/payroll-runs/{payrollRun}/bulk-pay/status', [BulkPaymentController::class, 'bulkPayStatus'])->whereNumber('payrollRun');
    });

    // ── Manager read-only payroll (principal, comptable, rh) ────────────
    // Issue #2749 — l'app RH (leopardo_hr) consomme ces lectures depuis ses
    // écrans paie ; le rôle rh était exclu du groupe principal/comptable
    // → 403 systématique. Les écritures paie restent principal/comptable.
    Route::middleware(['api.manager:principal,comptable,rh', 'tenant.country'])->group(function (): void {
        Route::get('/pay-slips/{paySlip}/pdf', [PaySlipController::class, 'downloadPdf'])->whereNumber('paySlip');
        Route::get('/payroll/mobile-summary', [PayrollCycleController::class, 'mobileSummary']);
        Route::get('/payments/{payrollRun}/documents', [PaymentDocumentController::class, 'payrollDocuments'])->whereNumber('payrollRun');
    });

    // Issue #1874 — audit des calculs de paie (lecture seule, immuable).
    // RBAC : manager principal/RH du tenant (PayrollAuditPolicy — isolation
    // tenant stricte : la liste est scopée à la société de l'acteur) ;
    // platform admin via /api/v1/admin/payroll/audit (routes api.php).
    Route::middleware(['api.manager:principal,rh'])->group(function (): void {
        Route::get('/payroll/audit', [PayrollAuditController::class, 'index']);
        Route::get('/payroll/audit/{correlationId}', [PayrollAuditController::class, 'show'])->whereUuid('correlationId');
    });
});
