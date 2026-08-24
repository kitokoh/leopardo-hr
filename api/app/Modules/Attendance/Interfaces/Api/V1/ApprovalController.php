<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Interfaces\Api\V1;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ApprovalDecisionResource;
use App\Http\Resources\Api\V1\ApprovalRequestResource;
use App\Http\Resources\Api\V1\ApprovalWorkflowResource;
use App\Modules\Attendance\Domain\Models\ApprovalDecision;
use App\Modules\Attendance\Domain\Models\ApprovalRequest;
use App\Modules\Attendance\Domain\Models\ApprovalWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        return (new ApprovalWorkflowResource($workflow))
            ->response()
            ->setStatusCode(201);
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

        return response()->json(['message' => __('attendance.workflow_deactivated')]);
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
            ->paginate(max(1, min(100, $request->integer('per_page', 15))));

        return ApprovalRequestResource::collection($requests)->response();
    }

    public function approve(Request $request, ApprovalRequest $approvalRequest): ApprovalRequestResource
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($approvalRequest->company_id !== $actor->company_id) {
            abort(404);
        }
        if ($approvalRequest->status !== 'pending') {
            return response()->json(['message' => __('attendance.request_not_pending')], 422);
        }
        // QA #3146 — la policy enregistrée n'était jamais invoquée : tout employé
        // authentifié pouvait approuver/rejeter n'importe quelle demande. La policy
        // exige manager + même société + statut pending.
        $this->authorize('approve', $approvalRequest);

        $validated = $request->validate([
            'comment' => 'nullable|string|max:500',
        ]);

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
            $maxLevel = count($workflow->levels);

            if ($approvalRequest->current_level >= $maxLevel) {
                $approvalRequest->update(['status' => 'approved']);
            } else {
                $approvalRequest->update(['current_level' => $approvalRequest->current_level + 1]);
            }

            return $approvalRequest->fresh();
        });

        return new ApprovalRequestResource($result->load('decisions'));
    }

    public function reject(Request $request, ApprovalRequest $approvalRequest): ApprovalRequestResource
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($approvalRequest->company_id !== $actor->company_id) {
            abort(404);
        }
        if ($approvalRequest->status !== 'pending') {
            return response()->json(['message' => __('attendance.request_not_pending')], 422);
        }
        // QA #3146 — même garde que approve (policy ApprovalRequestPolicy).
        $this->authorize('reject', $approvalRequest);

        $validated = $request->validate([
            'comment' => 'required|string|max:500',
        ]);

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
            ->paginate(max(1, min(100, $request->integer('per_page', 15))));

        // #4688 (audit 360° 2026-08-16) : enveloppe {data, links, meta} alignée
        // sur pending()/approve()/reject() (resource collection) — avant, le
        // paginator brut renvoyait les attributs modèles à plat.
        return ApprovalDecisionResource::collection($decisions)->response();
    }
}
