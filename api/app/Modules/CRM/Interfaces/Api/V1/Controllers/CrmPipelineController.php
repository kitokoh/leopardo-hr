<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CrmPipelineResource;
use App\Modules\CRM\Domain\Models\CrmPipeline;
use App\Modules\CRM\Domain\Models\CrmPipelineStage;
use App\Modules\CRM\Interfaces\Api\V1\Requests\StoreCrmPipelineRequest;
use App\Modules\CRM\Interfaces\Api\V1\Requests\StoreCrmPipelineStageRequest;
use App\Modules\CRM\Interfaces\Api\V1\Requests\UpdateCrmPipelineRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Pipelines et étapes CRM client — Issue #5711 (CRM-V0-07).
 *
 * Paramétrage commercial du tenant : réservé aux managers `principal`/`rh`
 * (Policy `CrmPipelinePolicy`). Isolation tenant : scope `BelongsToCompany`
 * (404 cross-tenant). `position` par défaut = fin de pipeline (max + 1).
 */
class CrmPipelineController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', CrmPipeline::class);

        $pipelines = CrmPipeline::query()
            ->with('stages')
            ->orderBy('name', 'asc')
            ->get();

        return response()->json([
            'data' => CrmPipelineResource::collection($pipelines),
        ]);
    }

    public function store(StoreCrmPipelineRequest $request): JsonResponse
    {
        Gate::authorize('create', CrmPipeline::class);

        $pipeline = CrmPipeline::query()->create($request->validated());

        return response()->json([
            'data' => new CrmPipelineResource($pipeline),
        ], 201);
    }

    public function show(Request $request, CrmPipeline $pipeline): JsonResponse
    {
        Gate::authorize('view', $pipeline);

        return response()->json([
            'data' => new CrmPipelineResource($pipeline->load('stages')),
        ]);
    }

    public function update(UpdateCrmPipelineRequest $request, CrmPipeline $pipeline): JsonResponse
    {
        Gate::authorize('update', $pipeline);

        $pipeline->update($request->validated());

        return response()->json([
            'data' => new CrmPipelineResource($pipeline->refresh()),
        ]);
    }

    public function destroy(Request $request, CrmPipeline $pipeline): JsonResponse
    {
        Gate::authorize('delete', $pipeline);

        // Les stages sont supprimés en cascade (FK) ; les opportunités d'un
        // pipeline supprimé sont bloquées en base (FK cascadeOnDelete).
        $pipeline->delete();

        return response()->json(null, 204);
    }

    public function storeStage(StoreCrmPipelineStageRequest $request, CrmPipeline $pipeline): JsonResponse
    {
        Gate::authorize('update', $pipeline);

        $payload = $request->validated();
        $payload['pipeline_id'] = $pipeline->id;
        $payload['position'] ??= (int) $pipeline->stages()->max('position') + 1;

        $stage = CrmPipelineStage::query()->create($payload);

        return response()->json([
            'data' => new \App\Http\Resources\Api\V1\CrmPipelineStageResource($stage),
        ], 201);
    }

    public function destroyStage(Request $request, CrmPipeline $pipeline, CrmPipelineStage $stage): JsonResponse
    {
        Gate::authorize('update', $pipeline);

        if ($stage->pipeline_id !== $pipeline->id) {
            abort(404);
        }

        $stage->delete();

        return response()->json(null, 204);
    }
}
