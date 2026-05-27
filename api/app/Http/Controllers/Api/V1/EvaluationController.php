<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Evaluation\EvaluationIndexRequest;
use App\Http\Requests\Api\V1\Evaluation\StoreEvaluationRequest;
use App\Http\Requests\Api\V1\Evaluation\UpdateEvaluationRequest;
use App\Http\Resources\Api\V1\EvaluationResource;
use App\Models\Employee;
use App\Models\Evaluation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Module 7 (complément) — Evaluations
 *
 * RBAC:
 *  - Manager : CRUD complet, soumet (draft → submitted)
 *  - Employé : lecture seule de ses propres évaluations, accuse réception (submitted → acknowledged)
 *
 * Workflow: draft → submitted → acknowledged
 */
class EvaluationController extends Controller
{
    public function index(EvaluationIndexRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $query = Evaluation::query()
            ->select([
                'id',
                'company_id',
                'employee_id',
                'evaluator_id',
                'period',
                'score',
                'criteria',
                'strengths',
                'improvements',
                'overall_comment',
                'status',
                'acknowledged_at',
                'created_at',
                'updated_at',
            ])
            ->with([
                'employee:id,first_name,last_name,email',
                'evaluator:id,first_name,last_name',
            ]);

        if (! $actor->isManager()) {
            $query->where('employee_id', $actor->id);
        } else {
            if ($request->filled('employee_id')) {
                $query->where('employee_id', $request->integer('employee_id'));
            }
            if ($request->filled('evaluator_id')) {
                $query->where('evaluator_id', $request->integer('evaluator_id'));
            }
        }

        if ($request->filled('period')) {
            $query->where('period', $request->input('period'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $perPage = $request->integer('per_page', 15);

        return EvaluationResource::collection($query->orderByDesc('created_at')->paginate($perPage))
            ->response();
    }

    public function store(StoreEvaluationRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $data = $request->validated();

        if (Evaluation::where('employee_id', $data['employee_id'])->where('evaluator_id', $actor->id)->where('period', $data['period'])->exists()) {
            return response()->json(['error' => ['code' => 'EVALUATION_ALREADY_EXISTS', 'message' => 'Une évaluation existe déjà pour cet employé sur cette période.']], 422);
        }

        $evaluation = Evaluation::create([
            'company_id' => $actor->company_id,
            'employee_id' => $data['employee_id'],
            'evaluator_id' => $actor->id,
            'period' => $data['period'],
            'score' => $data['score'] ?? null,
            'criteria' => $data['criteria'] ?? [],
            'strengths' => $data['strengths'] ?? null,
            'improvements' => $data['improvements'] ?? null,
            'overall_comment' => $data['overall_comment'] ?? null,
            'status' => 'draft',
        ]);

        return (new EvaluationResource($evaluation->load('employee', 'evaluator')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Evaluation $evaluation): JsonResponse
    {
        $this->authorize('view', $evaluation);

        return (new EvaluationResource($evaluation->load('employee', 'evaluator')))->response();
    }

    public function update(UpdateEvaluationRequest $request, Evaluation $evaluation): JsonResponse
    {
        $this->authorize('update', $evaluation);

        if ($evaluation->status === 'acknowledged') {
            return response()->json(['error' => ['code' => 'EVALUATION_ALREADY_ACKNOWLEDGED', 'message' => 'Une évaluation accusée de réception ne peut plus être modifiée.']], 422);
        }

        $data = $request->validated();

        $evaluation->update($data);

        /** @var Evaluation $fresh */
        $fresh = $evaluation->fresh();

        return (new EvaluationResource($fresh->load('employee', 'evaluator')))->response();
    }

    public function submit(Request $request, Evaluation $evaluation): JsonResponse
    {
        $this->authorize('submit', $evaluation);

        if ($evaluation->status !== 'draft') {
            return response()->json(['error' => ['code' => 'EVALUATION_NOT_DRAFT', 'message' => 'Seule une évaluation en brouillon peut être soumise.']], 422);
        }

        $evaluation->update(['status' => 'submitted']);

        /** @var Evaluation $fresh */
        $fresh = $evaluation->fresh();

        return (new EvaluationResource($fresh->load('employee', 'evaluator')))->response();
    }

    public function acknowledge(Request $request, Evaluation $evaluation): JsonResponse
    {
        $this->authorize('acknowledge', $evaluation);

        if ($evaluation->status !== 'submitted') {
            return response()->json(['error' => ['code' => 'EVALUATION_NOT_SUBMITTED', 'message' => 'Seule une évaluation soumise peut être accusée de réception.']], 422);
        }

        $evaluation->update(['status' => 'acknowledged', 'acknowledged_at' => Carbon::now()]);

        /** @var Evaluation $fresh */
        $fresh = $evaluation->fresh();

        return (new EvaluationResource($fresh->load('employee', 'evaluator')))->response();
    }

    public function destroy(Request $request, Evaluation $evaluation): JsonResponse
    {
        $this->authorize('delete', $evaluation);

        if ($evaluation->status !== 'draft') {
            return response()->json(['error' => ['code' => 'EVALUATION_NOT_DRAFT', 'message' => 'Seule une évaluation en brouillon peut être supprimée.']], 422);
        }

        $evaluation->delete();

        return response()->json(['message' => 'Evaluation deleted successfully']);
    }
}
