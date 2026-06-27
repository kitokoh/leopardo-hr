<?php

namespace App\Modules\Payroll\Application\Actions;

use App\Models\Employee;
use App\Models\Payroll;
use App\Modules\Payroll\Infrastructure\Services\PayrollService;

class GeneratePayroll
{
    public function __construct(
        private readonly PayrollService $payrollService,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public function handle(Employee $manager, array $data): Payroll
    {
        return $this->payrollService->create($manager, $data);
    }
}
