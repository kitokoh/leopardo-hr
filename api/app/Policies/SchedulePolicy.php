<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Employee;
use App\Models\Schedule;

class SchedulePolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, Schedule $schedule): bool
    {
        return $schedule->company_id === $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function update(Employee $actor, Schedule $schedule): bool
    {
        return $schedule->company_id === $actor->company_id && $actor->isManager();
    }

    public function delete(Employee $actor, Schedule $schedule): bool
    {
        return $schedule->company_id === $actor->company_id
            && $actor->hasManagerRole('principal', 'rh');
    }
}
