<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\CRM\Domain\Models\CrmPipeline;
use App\Modules\CRM\Interfaces\Api\V1\Requests\StoreCrmPipelineRequest;
use App\Modules\CRM\Interfaces\Api\V1\Requests\UpdateCrmPipelineRequest;
use App\Http\Resources\Api\V1\CrmPipelineResource;
use App\Modules\CRM\Interfaces\Api\V1\Support\CrmQueryHelpers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Issue #5711 — Pipelines CRM client (tenant).
 *
 * Le binding de route `{crmPipeline}` est scopé au tenant courant par le
 * trait BelongsToCompany : un pipeline d'un autre tenant est déjà 404.
 */
class CrmPipelineController extends Controller
{
    use CrmQueryHelpers;
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CrmPipeline::class);
        $this->rejectUnknownQueryKeys($request, ['per_page', 'sort_by', 'sort_dir']);

        $query = CrmPipeline::query();
        $query = $this->applySort($query, $request, [
            'name' => 'name',
            'created_at' => 'created_at',
            'updated_at' => 'updated_at',
        ]);

        return CrmPipelineResource::collection($query->paginate($this->perPage($request)));
    }

    public function store(StoreCrmPipelineRequest $request): JsonResponse
    {
        $this->authorize('create', CrmPipeline::class);

        /** @var Employee $actor */
        $actor = $request->user();

        $pipeline = CrmPipeline::query()->create([
            'company_id' => $actor->company_id,
            'name' => $request->validated('name'),
            'description' => $request->validated('description'),
            'is_default' => (bool) $request->validated('is_default', false),
            'created_by' => $actor->id,
        ]);

        return new JsonResponse(['data' => new CrmPipelineResource($pipeline)], 201);
    }

    public function show(CrmPipeline $crmPipeline): JsonResponse
    {
        $this->authorize('view', $crmPipeline);

        return new JsonResponse(['data' => $crmPipeline]);
    }

    public function update(UpdateCrmPipelineRequest $request, CrmPipeline $crmPipeline): JsonResponse
    {
        $this->authorize('update', $crmPipeline);

        $crmPipeline->update($request->validated());

        return new JsonResponse(['data' => new CrmPipelineResource($crmPipeline->fresh())]);
    }

    public function destroy(CrmPipeline $crmPipeline): JsonResponse
    {
        $this->authorize('delete', $crmPipeline);

        $crmPipeline->delete();

        return new JsonResponse(null, 204);
    }
}
