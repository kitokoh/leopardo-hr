<?php

declare(strict_types=1);

namespace App\Shared\Traits;

use App\Models\ApprovalRequest;
use App\Models\ApprovalWorkflow;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * Trait Approvable
 *
 * Gives any Eloquent model a polymorphic approval workflow.
 *
 * Usage:
 *   use App\Shared\Traits\Approvable;
 *
 * Required: the model must have `company_id` and an ApprovalWorkflow
 * configured for its class in the `approval_workflows` table.
 */
trait Approvable
{
    public function approvalRequest(): MorphOne
    {
        return $this->morphOne(ApprovalRequest::class, 'approvable');
    }

    public function submitForApproval(): ApprovalRequest
    {
        $workflow = ApprovalWorkflow::where('model_type', static::class)
            ->where('company_id', $this->company_id)
            ->where('active', true)
            ->firstOrFail();

        return ApprovalRequest::create([
            'company_id'      => $this->company_id,
            'workflow_id'     => $workflow->id,
            'approvable_type' => static::class,
            'approvable_id'   => $this->id,
            'requester_id'    => auth()->id(),
            'current_level'   => 1,
            'status'          => 'pending',
        ]);
    }

    public function isApproved(): bool
    {
        return $this->approvalRequest?->status === 'approved';
    }

    public function isPendingApproval(): bool
    {
        return $this->approvalRequest?->status === 'pending';
    }
}
