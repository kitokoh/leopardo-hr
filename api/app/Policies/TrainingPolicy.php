<?php

declare(strict_types=1);

namespace App\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\HR\Domain\Models\TrainingCourse;

class TrainingPolicy
{
    public function viewCourses(Employee $actor): bool
    {
        return true;
    }

    public function createCourse(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh');
    }

    public function updateCourse(Employee $actor, TrainingCourse $course): bool
    {
        return $actor->company_id === $course->company_id && $actor->hasManagerRole('principal', 'rh');
    }

    public function deleteCourse(Employee $actor, TrainingCourse $course): bool
    {
        return $actor->company_id === $course->company_id && $actor->hasManagerRole('principal');
    }

    public function manageSessions(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh');
    }

    public function enrollSelf(Employee $actor): bool
    {
        return $actor->status === 'active';
    }

    public function enrollOthers(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh');
    }

    public function viewEnrollments(Employee $actor): bool
    {
        return $actor->isManager();
    }
}

