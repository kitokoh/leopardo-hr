<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Employee;
use App\Models\ExpenseClaim;

class ExpenseClaimPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, ExpenseClaim $expense): bool
    {
        if ($expense->company_id !== $actor->company_id) {
            return false;
        }

        return $actor->isManager() || $actor->id === $expense->employee_id;
    }

    public function create(Employee $actor): bool
    {
        return $actor->status === 'active';
    }

    public function approve(Employee $actor, ExpenseClaim $expense): bool
    {
        return $expense->company_id === $actor->company_id
            && $actor->hasManagerRole('principal', 'comptable', 'rh');
    }

    public function reject(Employee $actor, ExpenseClaim $expense): bool
    {
        return $expense->company_id === $actor->company_id
            && $actor->hasManagerRole('principal', 'comptable', 'rh');
    }

    public function delete(Employee $actor, ExpenseClaim $expense): bool
    {
        if ($expense->company_id !== $actor->company_id) {
            return false;
        }

        return $actor->id === $expense->employee_id && $expense->status === 'draft';
    }
}
