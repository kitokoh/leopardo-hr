<?php

namespace App\Modules\Payroll\Application\Actions;

use App\Modules\Payroll\Domain\Exceptions\PayrollAlreadyValidatedException;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Infrastructure\Services\PayrollService;

class ValidatePayroll
{
    public function __construct(
        private readonly PayrollService $payrollService,
    ) {}

    /**
     * @throws PayrollAlreadyValidatedException
     */
    public function handle(PayrollRun $payrollRun): PayrollRun
    {
        return $this->payrollService->validate($payrollRun);
    }
}
