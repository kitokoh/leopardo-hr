<?php

namespace App\Modules\Payroll\Application\Actions;

use App\Exceptions\PayrollAlreadyValidatedException;
use App\Models\Employee;
use App\Models\Payroll;
use App\Modules\Payroll\Infrastructure\Services\PayrollService;

class ValidatePayroll
{
    public function __construct(
        private readonly PayrollService $payrollService,
    ) {}

    /**
     * @throws PayrollAlreadyValidatedException
     */
    public function handle(Payroll $payroll, Employee $validator): Payroll
    {
        return $this->payrollService->validate($payroll, $validator);
    }
}
