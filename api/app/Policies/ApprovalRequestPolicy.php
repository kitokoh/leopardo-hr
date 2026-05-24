<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ApprovalRequest;
use App\Models\Employee;

class ApprovalRequestPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, ApprovalRequest $request): bool
    {
        if ($request->company_id !== $actor->company_id) {
            return false;
        }

        return $actor->isManager() || $actor->id === $request->requester_id;
    }

    public function create(Employee $actor): bool
    {
        return $actor->status === 'active';
    }

    public function approve(Employee $actor, ApprovalRequest $request): bool
    {
        return $request->company_id === $actor->company_id
            && $actor->isManager()
            && $request->status === 'pending';
    }

    public function reject(Employee $actor, ApprovalRequest $request): bool
    {
        return $request->company_id === $actor->company_id
            && $actor->isManager()
            && $request->status === 'pending';
    }
}
