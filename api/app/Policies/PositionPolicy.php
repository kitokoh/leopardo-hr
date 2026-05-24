<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Employee;
use App\Models\Position;

class PositionPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, Position $position): bool
    {
        return $position->company_id === $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function update(Employee $actor, Position $position): bool
    {
        return $position->company_id === $actor->company_id && $actor->isManager();
    }

    public function delete(Employee $actor, Position $position): bool
    {
        return $position->company_id === $actor->company_id
            && $actor->hasManagerRole('principal', 'rh');
    }
}
