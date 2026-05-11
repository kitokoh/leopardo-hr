<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Employee;

class FeatureFlagPolicy
{
    public function viewMatrix(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function manageMatrix(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal');
    }

    public function checkFeature(Employee $actor): bool
    {
        return true;
    }
}
