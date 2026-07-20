<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Application\Actions;

use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Infrastructure\Services\PayrollService;

class GeneratePayroll
{
    public function __construct(
        private readonly PayrollService $payrollService,
    ) {}

    public function handle(string $companyId, string $period): PayrollRun
    {
        return $this->payrollService->generateForPeriod($companyId, $period);
    }
}
