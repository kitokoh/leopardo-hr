<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CrmOpportunityResource;
use App\Modules\CRM\Domain\Models\CrmOpportunity;
use App\Modules\CRM\Interfaces\Api\V1\Requests\IndexCrmOpportunityRequest;
use App\Modules\CRM\Interfaces\Api\V1\Requests\StoreCrmOpportunityRequest;
use App\Modules\CRM\Interfaces\Api\V1\Requests\UpdateCrmOpportunityRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * CRUD des opportunités CRM client — Issue #5711 (CRM-V0-07).
 *
 * RBAC : middleware `api.manager:principal,rh` + Policy `CrmOpportunityPolicy`
 * (garde unique, aucune garde inline). `won_at`/`lost_at` sont dérivés de
 * l'étape courante (is_won/is_lost) à chaque mutation. Isolation tenant :
 * scope `BelongsToCompany` (404 cross-tenant).
 */
class CrmOpportunityController extends Controller
{
    public function index(IndexCrmOpportunityRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', CrmOpportunity::class);

        $validated = $request->validated();
        $perPage = (int) ($validated['per_page'] ?? 20);

        $query = CrmOpportunity::query()->with('stage');

        foreach (['pipeline_id', 'stage_id', 'owner_id'] as $filter) {
            if (! empty($validated[$filter])) {
                $query->where($filter, (int) $validated[$filter]);
            }
        }

        if (! empty($validated['search'])) {
            $needle = '%'.addcslashes((string) $validated['search'], '%_\\').'%';
            $query->where('name', 'like', $needle);
        }

        $sortBy = (string) ($validated['sort_by'] ?? 'created_at');
        $sortDir = (string) ($validated['sort_dir'] ?? 'desc');
        $query->orderBy($sortBy, $sortDir)->orderBy('id', $sortDir);

        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => CrmOpportunityResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(StoreCrmOpportunityRequest $request): JsonResponse
    {
        Gate::authorize('create', CrmOpportunity::class);

        $payload = $this->withDerivedStageState($request->validated());
        $payload['created_by'] = $request->user()?->id;

        $opportunity = CrmOpportunity::query()->create($payload);

        return response()->json([
            'data' => new CrmOpportunityResource($opportunity->load('stage')),
        ], 201);
    }

    public function show(Request $request, CrmOpportunity $opportunity): JsonResponse
    {
        Gate::authorize('view', $opportunity);

        return response()->json([
            'data' => new CrmOpportunityResource($opportunity->load('stage')),
        ]);
    }

    public function update(UpdateCrmOpportunityRequest $request, CrmOpportunity $opportunity): JsonResponse
    {
        Gate::authorize('update', $opportunity);

        $opportunity->update($this->withDerivedStageState($request->validated()));

        return response()->json([
            'data' => new CrmOpportunityResource($opportunity->refresh()->load('stage')),
        ]);
    }

    public function destroy(Request $request, CrmOpportunity $opportunity): JsonResponse
    {
        Gate::authorize('delete', $opportunity);

        $opportunity->delete();

        return response()->json(null, 204);
    }

    /**
     * Dérive won_at/lost_at depuis l'étape courante (is_won/is_lost).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function withDerivedStageState(array $payload): array
    {
        $stageId = isset($payload['stage_id']) ? (int) $payload['stage_id'] : null;

        if ($stageId === null) {
            unset($payload['won_at'], $payload['lost_at']);

            return $payload;
        }

        $stage = \App\Modules\CRM\Domain\Models\CrmPipelineStage::query()->find($stageId);

        $payload['won_at'] = $stage?->is_won ? now() : null;
        $payload['lost_at'] = $stage?->is_lost ? now() : null;

        return $payload;
    }
}
