<?php

namespace App\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\HR\Domain\Models\Evaluation;

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

        if ($actor->id === $evaluation->employee_id) {
            return true;
        }

        return $this->managesEvaluatedEmployee($actor, $evaluation);
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

        return $this->managesEvaluatedEmployee($actor, $evaluation);
    }

    public function delete(Employee $actor, Evaluation $evaluation): bool
    {
        if ($evaluation->company_id !== $actor->company_id) {
            return false;
        }

        return $this->managesEvaluatedEmployee($actor, $evaluation);
    }

    public function submit(Employee $actor, Evaluation $evaluation): bool
    {
        if ($evaluation->company_id !== $actor->company_id) {
            return false;
        }

        return $this->managesEvaluatedEmployee($actor, $evaluation);
    }

    public function acknowledge(Employee $actor, Evaluation $evaluation): bool
    {
        if ($evaluation->company_id !== $actor->company_id) {
            return false;
        }

        return $actor->id === $evaluation->employee_id;
    }

    /**
     * PA2-SEC-002 / PA2-SEC-003: manager_role=dept may only act on
     * evaluations for employees within their own department, and
     * manager_role=superviseur only for their own directly assigned team.
     * Company-wide manager roles are unaffected.
     */
    private function managesEvaluatedEmployee(Employee $actor, Evaluation $evaluation): bool
    {
        if (! $actor->isManager()) {
            return false;
        }

        if (! $actor->isTeamScoped()) {
            return true;
        }

        $target = $evaluation->employee;

        return $target !== null && $actor->managesTeamMemberOf($target);
    }
}
