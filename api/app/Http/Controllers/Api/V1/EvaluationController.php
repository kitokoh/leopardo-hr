<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
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
    public function index(Request $request): JsonResponse
    {
        $actor = $request->user();

        $request->validate([
            'employee_id' => ['nullable', 'integer', 'min:1'],
            'evaluator_id' => ['nullable', 'integer', 'min:1'],
            'period' => ['nullable', 'string', 'max:20'],
            'status' => ['nullable', 'in:draft,submitted,acknowledged'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

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
        $paginated = $query->orderByDesc('created_at')->paginate($perPage);

        return response()->json([
            'data' => $paginated->map(fn ($e) => $this->serialize($e)),
            'meta' => ['current_page' => $paginated->currentPage(), 'last_page' => $paginated->lastPage(), 'per_page' => $paginated->perPage(), 'total' => $paginated->total()],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $actor = $request->user();
        if (! $actor->isManager()) {
            abort(403);
        }

        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'min:1'],
            'period' => ['required', 'string', 'max:20'],
            'score' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'criteria' => ['nullable', 'array'],
            'criteria.*.label' => ['required_with:criteria', 'string', 'max:100'],
            'criteria.*.score' => ['required_with:criteria', 'numeric', 'min:0', 'max:5'],
            'strengths' => ['nullable', 'string', 'max:2000'],
            'improvements' => ['nullable', 'string', 'max:2000'],
            'overall_comment' => ['nullable', 'string', 'max:2000'],
        ]);

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

        return response()->json(['data' => $this->serialize($evaluation->load('employee', 'evaluator'))], 201);
    }

    public function show(Request $request, Evaluation $evaluation): JsonResponse
    {
        $actor = $request->user();
        if ($evaluation->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->isManager() && $evaluation->employee_id !== $actor->id) {
            abort(403);
        }

        return response()->json(['data' => $this->serialize($evaluation->load('employee', 'evaluator'))]);
    }

    public function update(Request $request, Evaluation $evaluation): JsonResponse
    {
        $actor = $request->user();
        if ($evaluation->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->isManager()) {
            abort(403);
        }

        if ($evaluation->status === 'acknowledged') {
            return response()->json(['error' => ['code' => 'EVALUATION_ALREADY_ACKNOWLEDGED', 'message' => 'Une évaluation accusée de réception ne peut plus être modifiée.']], 422);
        }

        $data = $request->validate([
            'score' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'criteria' => ['nullable', 'array'],
            'criteria.*.label' => ['required_with:criteria', 'string', 'max:100'],
            'criteria.*.score' => ['required_with:criteria', 'numeric', 'min:0', 'max:5'],
            'strengths' => ['nullable', 'string', 'max:2000'],
            'improvements' => ['nullable', 'string', 'max:2000'],
            'overall_comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $evaluation->update($data);

        return response()->json(['data' => $this->serialize($evaluation->fresh()->load('employee', 'evaluator'))]);
    }

    public function submit(Request $request, Evaluation $evaluation): JsonResponse
    {
        $actor = $request->user();
        if ($evaluation->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->isManager()) {
            abort(403);
        }

        if ($evaluation->status !== 'draft') {
            return response()->json(['error' => ['code' => 'EVALUATION_NOT_DRAFT', 'message' => 'Seule une évaluation en brouillon peut être soumise.']], 422);
        }

        $evaluation->update(['status' => 'submitted']);

        return response()->json(['data' => $this->serialize($evaluation->fresh()->load('employee', 'evaluator'))]);
    }

    public function acknowledge(Request $request, Evaluation $evaluation): JsonResponse
    {
        $actor = $request->user();
        if ($evaluation->company_id !== $actor->company_id) {
            abort(404);
        }
        if ($evaluation->employee_id !== $actor->id) {
            abort(403);
        }

        if ($evaluation->status !== 'submitted') {
            return response()->json(['error' => ['code' => 'EVALUATION_NOT_SUBMITTED', 'message' => 'Seule une évaluation soumise peut être accusée de réception.']], 422);
        }

        $evaluation->update(['status' => 'acknowledged', 'acknowledged_at' => Carbon::now()]);

        return response()->json(['data' => $this->serialize($evaluation->fresh()->load('employee', 'evaluator'))]);
    }

    public function destroy(Request $request, Evaluation $evaluation): JsonResponse
    {
        $actor = $request->user();
        if ($evaluation->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->isManager()) {
            abort(403);
        }

        if ($evaluation->status !== 'draft') {
            return response()->json(['error' => ['code' => 'EVALUATION_NOT_DRAFT', 'message' => 'Seule une évaluation en brouillon peut être supprimée.']], 422);
        }

        $evaluation->delete();

        return response()->json(['message' => 'Evaluation deleted successfully']);
    }

    private function serialize(Evaluation $e): array
    {
        return [
            'id' => $e->id,
            'employee_id' => $e->employee_id,
            'employee' => $e->relationLoaded('employee') ? ['id' => $e->employee->id, 'first_name' => $e->employee->first_name, 'last_name' => $e->employee->last_name, 'email' => $e->employee->email] : null,
            'evaluator_id' => $e->evaluator_id,
            'evaluator' => $e->relationLoaded('evaluator') ? ['id' => $e->evaluator->id, 'first_name' => $e->evaluator->first_name, 'last_name' => $e->evaluator->last_name] : null,
            'period' => $e->period,
            'score' => $e->score,
            'criteria' => $e->criteria,
            'strengths' => $e->strengths,
            'improvements' => $e->improvements,
            'overall_comment' => $e->overall_comment,
            'status' => $e->status,
            'acknowledged_at' => $e->acknowledged_at?->toIso8601String(),
            'created_at' => $e->created_at?->toIso8601String(),
            'updated_at' => $e->updated_at?->toIso8601String(),
        ];
    }
}
