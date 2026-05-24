<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Employee;
use App\Models\EmployeeLoan;

class LoanPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, EmployeeLoan $loan): bool
    {
        if ($loan->company_id !== $actor->company_id) {
            return false;
        }

        return $actor->isManager() || $actor->id === $loan->employee_id;
    }

    public function create(Employee $actor): bool
    {
        return $actor->status === 'active';
    }

    public function approve(Employee $actor, EmployeeLoan $loan): bool
    {
        return $loan->company_id === $actor->company_id
            && $actor->hasManagerRole('principal', 'comptable');
    }

    public function reject(Employee $actor, EmployeeLoan $loan): bool
    {
        return $loan->company_id === $actor->company_id
            && $actor->hasManagerRole('principal', 'comptable');
    }

    public function disburse(Employee $actor, EmployeeLoan $loan): bool
    {
        return $loan->company_id === $actor->company_id
            && $actor->hasManagerRole('principal', 'comptable');
    }
}
