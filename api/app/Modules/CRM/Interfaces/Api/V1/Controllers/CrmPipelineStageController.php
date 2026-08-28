<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CrmPipelineStageResource;
use App\Modules\CRM\Domain\Models\CrmPipeline;
use App\Modules\CRM\Domain\Models\CrmPipelineStage;
use App\Modules\CRM\Interfaces\Api\V1\Requests\StoreCrmPipelineStageRequest;
use App\Modules\CRM\Interfaces\Api\V1\Requests\UpdateCrmPipelineStageRequest;
use App\Modules\CRM\Interfaces\Api\V1\Support\CrmQueryHelpers;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Issue #5711 — Stages de pipeline CRM client (tenant).
 *
 * Les stages sont toujours rattachés à un pipeline (binding `{crmPipeline}`
 * scopé au tenant). Le rejet d'une position dupliquée ou d'un couple
 * won/lost arrive en 422 (contrôle application) avant le CHECK en base.
 */
class CrmPipelineStageController extends Controller
{
    use CrmQueryHelpers;
    public function index(Request $request, CrmPipeline $crmPipeline): JsonResponse
    {
        $this->authorize('viewAny', CrmPipelineStage::class);
        $this->rejectUnknownQueryKeys($request, ['per_page', 'sort_by', 'sort_dir']);

        $query = CrmPipelineStage::query()
            ->where('pipeline_id', $crmPipeline->id)
            ->orderBy('position');

        return CrmPipelineStageResource::collection($query->paginate($this->perPage($request)));
    }

    public function store(StoreCrmPipelineStageRequest $request, CrmPipeline $crmPipeline): JsonResponse
    {
        $this->authorize('create', CrmPipelineStage::class);

        /** @var Employee $actor */
        $actor = $request->user();

        try {
            $stage = CrmPipelineStage::query()->create([
                'company_id' => $actor->company_id,
                'pipeline_id' => $crmPipeline->id,
                'name' => $request->validated('name'),
                'position' => (int) $request->validated('position'),
                'color' => $request->validated('color'),
                'is_won' => (bool) $request->validated('is_won', false),
                'is_lost' => (bool) $request->validated('is_lost', false),
                'created_by' => $actor->id,
            ]);
        } catch (QueryException $exception) {
            if ($this->isUniqueViolation($exception)) {
                throw ValidationException::withMessages([
                    'position' => [__('crm.pipeline_stage_position_taken')],
                ]);
            }

            throw $exception;
        }

        return new JsonResponse(['data' => new CrmPipelineStageResource($stage)], 201);
    }

    public function update(UpdateCrmPipelineStageRequest $request, CrmPipeline $crmPipeline, CrmPipelineStage $crmPipelineStage): JsonResponse
    {
        $this->authorize('update', $crmPipelineStage);

        try {
            $crmPipelineStage->update($request->validated());
        } catch (QueryException $exception) {
            if ($this->isUniqueViolation($exception)) {
                throw ValidationException::withMessages([
                    'position' => [__('crm.pipeline_stage_position_taken')],
                ]);
            }

            throw $exception;
        }

        return new JsonResponse(['data' => new CrmPipelineStageResource($crmPipelineStage->fresh())]);
    }

    public function destroy(CrmPipeline $crmPipeline, CrmPipelineStage $crmPipelineStage): JsonResponse
    {
        $this->authorize('delete', $crmPipelineStage);

        $crmPipelineStage->delete();

        return new JsonResponse(null, 204);
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        $sqlState = $exception->getPrevious()?->getCode() ?? '';

        // PostgreSQL 23505 / MySQL 1062 — violation de contrainte unique.
        return $sqlState === '23505' || $sqlState === 1062 || str_contains($exception->getMessage(), 'UNIQUE');
    }
}
