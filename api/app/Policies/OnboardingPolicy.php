<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Employee;

class OnboardingPolicy
{
    public function viewChecklist(Employee $actor): bool
    {
        return true;
    }

    public function completeStep(Employee $actor): bool
    {
        return true;
    }

    public function skipStep(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function manageSteps(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh');
    }
}
