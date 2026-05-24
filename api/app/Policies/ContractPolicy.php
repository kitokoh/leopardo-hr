<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Contract;
use App\Models\Employee;

class ContractPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function view(Employee $actor, Contract $contract): bool
    {
        if ($contract->company_id !== $actor->company_id) {
            return false;
        }

        return $actor->isManager() || $actor->id === $contract->employee_id;
    }

    public function create(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh');
    }

    public function update(Employee $actor, Contract $contract): bool
    {
        return $contract->company_id === $actor->company_id
            && $actor->hasManagerRole('principal', 'rh');
    }

    public function activate(Employee $actor, Contract $contract): bool
    {
        return $contract->company_id === $actor->company_id
            && $actor->hasManagerRole('principal', 'rh');
    }

    public function terminate(Employee $actor, Contract $contract): bool
    {
        return $contract->company_id === $actor->company_id
            && $actor->hasManagerRole('principal', 'rh');
    }

    public function renew(Employee $actor, Contract $contract): bool
    {
        return $contract->company_id === $actor->company_id
            && $actor->hasManagerRole('principal', 'rh');
    }
}
