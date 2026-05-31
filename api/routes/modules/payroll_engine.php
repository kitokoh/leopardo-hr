<?php

/**
 * Routes Payroll Engine — salaires, composants, bulletins, exports bancaires.
 *
 * RBAC:
 *   - /me/pay-slips: all employees (own pay slips)
 *   - Cotisation simulation: all employees
 *   - Everything else: managers (principal, comptable)
 */

use App\Http\Controllers\Api\V1\BankExportController;
use App\Http\Controllers\Api\V1\BulkPaymentController;
use App\Http\Controllers\Api\V1\CotisationSimulationController;
use App\Http\Controllers\Api\V1\PayrollCycleController;
use App\Http\Controllers\Api\V1\PayrollRunController;
use App\Http\Controllers\Api\V1\PaySlipController;
use App\Http\Controllers\Api\V1\SalaryComponentController;
use App\Http\Controllers\Api\V1\SalaryStructureController;
use App\Http\Controllers\Api\V1\SocialContributionController;
use App\Http\Controllers\Api\V1\SocialDeclarationController;
use App\Http\Controllers\Api\V1\TaxSlabController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:api', 'auth:sanctum', 'tenant', 'throttle:api-plan', 'throttle:payroll-sensitive'])->group(function () {

    // ── Self-service pay slips (all employees) ───────────────────────────
    Route::get('/me/pay-slips', [PaySlipController::class, 'myPaySlips']);
    Route::get('/me/pay-slips/{paySlip}', [PaySlipController::class, 'myPaySlipDetail'])->whereNumber('paySlip');
    Route::get('/me/pay-slips/{paySlip}/pdf', [PaySlipController::class, 'downloadPdf'])->whereNumber('paySlip');

    // ── Cotisation Simulation (all employees) ────────────────────────────
    Route::post('/cotisation-simulation', [CotisationSimulationController::class, 'simulate']);

    // ── Manager-only payroll routes (principal, comptable) ───────────────
    Route::middleware('api.manager:principal,comptable')->group(function () {

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

        // Tax Slabs
        Route::get('/tax-slabs', [TaxSlabController::class, 'index']);
        Route::post('/tax-slabs', [TaxSlabController::class, 'store']);
        Route::put('/tax-slabs/{taxSlab}', [TaxSlabController::class, 'update'])->whereNumber('taxSlab');
        Route::delete('/tax-slabs/{taxSlab}', [TaxSlabController::class, 'destroy'])->whereNumber('taxSlab');

        // Social Contributions
        Route::get('/social-contributions', [SocialContributionController::class, 'index']);
        Route::post('/social-contributions', [SocialContributionController::class, 'store']);
        Route::put('/social-contributions/{socialContribution}', [SocialContributionController::class, 'update'])->whereNumber('socialContribution');
        Route::delete('/social-contributions/{socialContribution}', [SocialContributionController::class, 'destroy'])->whereNumber('socialContribution');

        // Payroll Runs
        Route::get('/payroll-runs', [PayrollRunController::class, 'index']);
        Route::post('/payroll-runs', [PayrollRunController::class, 'store']);
        Route::get('/payroll-runs/{payrollRun}', [PayrollRunController::class, 'show'])->whereNumber('payrollRun');
        Route::post('/payroll-runs/{payrollRun}/calculate', [PayrollRunController::class, 'calculate'])->whereNumber('payrollRun');
        Route::post('/payroll-runs/{payrollRun}/validate', [PayrollRunController::class, 'validateRun'])->whereNumber('payrollRun');
        Route::post('/payroll-runs/{payrollRun}/cancel', [PayrollRunController::class, 'cancel'])->whereNumber('payrollRun');
        Route::get('/payroll-runs/{payrollRun}/summary', [PayrollRunController::class, 'summary'])->whereNumber('payrollRun');

        // Pay Slips (manager view)
        Route::get('/pay-slips', [PaySlipController::class, 'index']);
        Route::get('/payroll-runs/{payrollRun}/pay-slips', [PaySlipController::class, 'indexForRun'])->whereNumber('payrollRun');
        Route::post('/payroll-runs/{payrollRun}/send-slips', [PaySlipController::class, 'sendSlips'])->whereNumber('payrollRun');
        Route::get('/pay-slips/{paySlip}', [PaySlipController::class, 'show'])->whereNumber('paySlip');
        Route::get('/pay-slips/{paySlip}/pdf', [PaySlipController::class, 'downloadPdf'])->whereNumber('paySlip');

        // Bank Exports
        Route::post('/payroll-runs/{payrollRun}/bank-export', [BankExportController::class, 'generate'])->whereNumber('payrollRun');
        Route::get('/bank-exports/{bankExport}', [BankExportController::class, 'show'])->whereNumber('bankExport');
        Route::get('/bank-exports/{bankExport}/download', [BankExportController::class, 'download'])->whereNumber('bankExport');

        // Social Declarations (CNAS DZ / CNSS MA / DSN FR)
        Route::post('/social-declarations/cnas-dz', [SocialDeclarationController::class, 'generateCnasDz']);
        Route::post('/social-declarations/cnss-ma', [SocialDeclarationController::class, 'generateCnssMa']);
        Route::post('/social-declarations/dsn-fr', [SocialDeclarationController::class, 'generateDsnFr']);

        // Plan 61 — Payroll cycles
        Route::get('/payroll/cycles', [PayrollCycleController::class, 'index']);
        Route::get('/payroll/cycles/current', [PayrollCycleController::class, 'current']);

        // Plan 65 — Bulk payment
        Route::post('/payroll-runs/{payrollRun}/bulk-pay', [BulkPaymentController::class, 'bulkPay'])->whereNumber('payrollRun');
        Route::get('/payroll-runs/{payrollRun}/bulk-pay/status', [BulkPaymentController::class, 'bulkPayStatus'])->whereNumber('payrollRun');
    });
});
