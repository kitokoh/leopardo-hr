<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ApprovalRequestResource;
use App\Http\Resources\Api\V1\ApprovalWorkflowResource;
use App\Models\ApprovalDecision;
use App\Models\ApprovalRequest;
use App\Models\ApprovalWorkflow;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\Api\V1\Approval\ApproveApprovalRequest;
use App\Http\Requests\Api\V1\Approval\RejectApprovalRequest;
use App\Http\Requests\Api\V1\Approval\StoreWorkflowApprovalRequest;
use App\Http\Requests\Api\V1\Approval\UpdateWorkflowApprovalRequest;

class ApprovalController extends Controller
{
    public function indexWorkflows(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->hasManagerRole('principal', 'rh')) {
            abort(403);
        }

        $workflows = ApprovalWorkflow::query()
            ->select(['id', 'company_id', 'name', 'levels', 'active', 'created_at'])
            ->where('company_id', $actor->company_id)
            ->orderBy('name')
            ->get();

        return ApprovalWorkflowResource::collection($workflows)->response();
    }

    public function storeWorkflow(StoreWorkflowApprovalRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->hasManagerRole('principal', 'rh')) {
            abort(403);
        }

        $validated = $request->validated();

        $workflow = ApprovalWorkflow::create([
            ...$validated,
            'company_id' => $actor->company_id,
        ]);

        return (new ApprovalWorkflowResource($workflow))
            ->response()
            ->setStatusCode(201);
    }

    public function updateWorkflow(UpdateWorkflowApprovalRequest $request, ApprovalWorkflow $approvalWorkflow): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($approvalWorkflow->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->hasManagerRole('principal', 'rh')) {
            abort(403);
        }

        $validated = $request->validated();

        $approvalWorkflow->update($validated);

        return (new ApprovalWorkflowResource($approvalWorkflow->fresh()))->response();
    }

    public function destroyWorkflow(Request $request, ApprovalWorkflow $approvalWorkflow): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($approvalWorkflow->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->hasManagerRole('principal', 'rh')) {
            abort(403);
        }

        $approvalWorkflow->update(['active' => false]);

        return response()->json(['message' => 'Workflow deactivated.']);
    }

    public function pending(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $requests = ApprovalRequest::query()
            ->pending()
            ->with(['workflow:id,name', 'requester:id,first_name,last_name', 'approvable'])
            ->where('company_id', $actor->company_id)
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return ApprovalRequestResource::collection($requests)->response();
    }

    public function approve(ApproveApprovalRequest $request, ApprovalRequest $approvalRequest): ApprovalRequestResource
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($approvalRequest->company_id !== $actor->company_id) {
            abort(404);
        }
        if ($approvalRequest->status !== 'pending') {
            return response()->json(['message' => 'Request is not pending.'], 422);
        }

        $validated = $request->validated();

        $result = DB::transaction(function () use ($approvalRequest, $actor, $validated) {
            ApprovalDecision::create([
                'approval_request_id' => $approvalRequest->id,
                'level' => $approvalRequest->current_level,
                'approver_id' => $actor->id,
                'decision' => 'approved',
                'comment' => $validated['comment'] ?? null,
                'decided_at' => now(),
            ]);

            $workflow = $approvalRequest->workflow;
            $levels = is_array($workflow->levels) ? $workflow->levels : [];
            $maxLevel = count($levels);

            if ($approvalRequest->current_level >= $maxLevel) {
                $approvalRequest->update(['status' => 'approved']);
            } else {
                $approvalRequest->update(['current_level' => $approvalRequest->current_level + 1]);
            }

            return $approvalRequest->fresh();
        });

        return new ApprovalRequestResource($result->load('decisions'));
    }

    public function reject(RejectApprovalRequest $request, ApprovalRequest $approvalRequest): ApprovalRequestResource
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($approvalRequest->company_id !== $actor->company_id) {
            abort(404);
        }
        if ($approvalRequest->status !== 'pending') {
            return response()->json(['message' => 'Request is not pending.'], 422);
        }

        $validated = $request->validated();

        $result = DB::transaction(function () use ($approvalRequest, $actor, $validated) {
            ApprovalDecision::create([
                'approval_request_id' => $approvalRequest->id,
                'level' => $approvalRequest->current_level,
                'approver_id' => $actor->id,
                'decision' => 'rejected',
                'comment' => $validated['comment'],
                'decided_at' => now(),
            ]);

            $approvalRequest->update(['status' => 'rejected']);

            return $approvalRequest->fresh();
        });

        return new ApprovalRequestResource($result->load('decisions'));
    }

    public function history(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $decisions = ApprovalDecision::query()
            ->where('approver_id', $actor->id)
            ->with(['request:id,status,approvable_type,approvable_id,requester_id', 'request.requester:id,first_name,last_name'])
            ->orderByDesc('decided_at')
            ->paginate($request->integer('per_page', 15));

        return response()->json($decisions);
    }
}
