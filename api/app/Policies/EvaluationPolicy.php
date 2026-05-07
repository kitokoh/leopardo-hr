<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\Evaluation;

class EvaluationPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true; // Filtering is handled in the controller index
    }

    public function view(Employee $actor, Evaluation $evaluation): bool
    {
        if ($evaluation->company_id !== $actor->company_id) {
            return false;
        }

        return $actor->isManager() || $actor->id === $evaluation->employee_id;
    }

    public function create(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function update(Employee $actor, Evaluation $evaluation): bool
    {
        if ($evaluation->company_id !== $actor->company_id) {
            return false;
        }

        return $actor->isManager();
    }

    public function delete(Employee $actor, Evaluation $evaluation): bool
    {
        if ($evaluation->company_id !== $actor->company_id) {
            return false;
        }

        return $actor->isManager();
    }

    public function submit(Employee $actor, Evaluation $evaluation): bool
    {
        if ($evaluation->company_id !== $actor->company_id) {
            return false;
        }

        return $actor->isManager();
    }

    public function acknowledge(Employee $actor, Evaluation $evaluation): bool
    {
        if ($evaluation->company_id !== $actor->company_id) {
            return false;
        }

        return $actor->id === $evaluation->employee_id;
    }
}
