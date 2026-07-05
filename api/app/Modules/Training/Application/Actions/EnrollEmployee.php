<?php

declare(strict_types=1);

namespace App\Modules\Training\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\HR\Domain\Models\TrainingCourse;
use App\Modules\HR\Domain\Models\TrainingEnrollment;
use App\Modules\HR\Domain\Models\TrainingSession;
use Illuminate\Support\Facades\DB;

/**
 * Use Case: Enroll an employee in a training course or session.
 */
final class EnrollEmployee
{
    public function execute(
        Employee $employee,
        TrainingCourse $course,
        ?TrainingSession $session = null,
    ): TrainingEnrollment {
        // Prevent duplicate enrollment
        $existing = TrainingEnrollment::where('employee_id', $employee->id)
            ->where('course_id', $course->id)
            ->whereIn('status', ['enrolled', 'in_progress'])
            ->first();

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($employee, $course, $session): TrainingEnrollment {
            /** @var TrainingEnrollment $enrollment */
            $enrollment = TrainingEnrollment::create([
                'employee_id' => $employee->id,
                'course_id'   => $course->id,
                'session_id'  => $session?->id,
                'company_id'  => $employee->company_id,
                'status'      => 'enrolled',
            ]);

            return $enrollment;
        });
    }
}

