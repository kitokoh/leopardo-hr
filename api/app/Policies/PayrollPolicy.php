<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\PaySlip;

class PayrollPolicy
{
    public function viewRuns(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function createRun(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh');
    }

    public function calculateRun(Employee $actor, PayrollRun $run): bool
    {
        return $actor->company_id === $run->company_id && $actor->hasManagerRole('principal', 'rh');
    }

    public function validateRun(Employee $actor, PayrollRun $run): bool
    {
        return $actor->company_id === $run->company_id && $actor->hasManagerRole('principal');
    }

    public function cancelRun(Employee $actor, PayrollRun $run): bool
    {
        return $actor->company_id === $run->company_id && $actor->hasManagerRole('principal');
    }

    public function viewSlip(Employee $actor, PaySlip $slip): bool
    {
        if ($actor->id === $slip->employee_id && $actor->company_id === $slip->company_id) {
            return true;
        }

        return $actor->company_id === $slip->company_id && $actor->isManager();
    }

    public function downloadPdf(Employee $actor, PaySlip $slip): bool
    {
        return $this->viewSlip($actor, $slip);
    }

    public function sendSlips(Employee $actor, PayrollRun $run): bool
    {
        return $actor->company_id === $run->company_id && $actor->isManager();
    }

    public function generateBankExport(Employee $actor, PayrollRun $run): bool
    {
        return $actor->company_id === $run->company_id && $actor->hasManagerRole('principal', 'rh');
    }
}
