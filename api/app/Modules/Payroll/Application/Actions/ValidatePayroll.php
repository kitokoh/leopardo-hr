<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Payroll\Domain\Exceptions\PayrollAlreadyValidatedException;
use App\Modules\Payroll\Domain\Models\Payroll;
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
