<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ApprovalDecision;
use App\Models\ApprovalRequest;
use App\Models\ApprovalWorkflow;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            ->where('company_id', $actor->company_id)
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $workflows]);
    }

    public function storeWorkflow(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->hasManagerRole('principal', 'rh')) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'model_type' => 'required|string|max:100',
            'levels' => 'required|array|min:1',
            'levels.*.level' => 'required|integer|min:1',
            'levels.*.approver_type' => 'required|string',
            'auto_approve_below' => 'nullable|numeric|min:0',
            'escalation_hours' => 'nullable|integer|min:1',
            'active' => 'boolean',
        ]);

        $workflow = ApprovalWorkflow::create([
            ...$validated,
            'company_id' => $actor->company_id,
        ]);

        return response()->json(['data' => $workflow], 201);
    }

    public function updateWorkflow(Request $request, ApprovalWorkflow $approvalWorkflow): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($approvalWorkflow->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->hasManagerRole('principal', 'rh')) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:150',
            'levels' => 'sometimes|array|min:1',
            'auto_approve_below' => 'nullable|numeric|min:0',
            'escalation_hours' => 'nullable|integer|min:1',
            'active' => 'boolean',
        ]);

        $approvalWorkflow->update($validated);

        return response()->json(['data' => $approvalWorkflow->fresh()]);
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

        return response()->json($requests);
    }

    public function approve(Request $request, ApprovalRequest $approvalRequest): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($approvalRequest->company_id !== $actor->company_id) {
            abort(404);
        }
        if ($approvalRequest->status !== 'pending') {
            return response()->json(['message' => 'Request is not pending.'], 422);
        }

        $validated = $request->validate([
            'comment' => 'nullable|string|max:500',
        ]);

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

        /** @var ApprovalRequest $approvalRequestFresh */
        $approvalRequestFresh = $approvalRequest->fresh();

        return response()->json(['data' => $approvalRequestFresh->load('decisions')]);
    }

    public function reject(Request $request, ApprovalRequest $approvalRequest): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($approvalRequest->company_id !== $actor->company_id) {
            abort(404);
        }
        if ($approvalRequest->status !== 'pending') {
            return response()->json(['message' => 'Request is not pending.'], 422);
        }

        $validated = $request->validate([
            'comment' => 'required|string|max:500',
        ]);

        ApprovalDecision::create([
            'approval_request_id' => $approvalRequest->id,
            'level' => $approvalRequest->current_level,
            'approver_id' => $actor->id,
            'decision' => 'rejected',
            'comment' => $validated['comment'],
            'decided_at' => now(),
        ]);

        $approvalRequest->update(['status' => 'rejected']);

        /** @var ApprovalRequest $approvalRequestFresh */
        $approvalRequestFresh = $approvalRequest->fresh();

        return response()->json(['data' => $approvalRequestFresh->load('decisions')]);
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
