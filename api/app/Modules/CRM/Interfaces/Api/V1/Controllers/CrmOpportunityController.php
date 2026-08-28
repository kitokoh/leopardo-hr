<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\CRM\Domain\Models\CrmOpportunity;
use App\Modules\CRM\Domain\Models\CrmPipelineStage;
use App\Modules\CRM\Interfaces\Api\V1\Requests\StoreCrmOpportunityRequest;
use App\Modules\CRM\Interfaces\Api\V1\Requests\UpdateCrmOpportunityRequest;
use App\Http\Resources\Api\V1\CrmOpportunityResource;
use App\Modules\CRM\Interfaces\Api\V1\Support\CrmQueryHelpers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Issue #5711 — Opportunités CRM client (tenant).
 *
 * `won_at`/`lost_at` sont horodatés automatiquement à la transition de
 * stage (dérivée de `crm_pipeline_stages.is_won/is_lost`).
 */
class CrmOpportunityController extends Controller
{
    use CrmQueryHelpers;
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CrmOpportunity::class);
        $this->rejectUnknownQueryKeys($request, [
            'per_page', 'sort_by', 'sort_dir',
            'pipeline_id', 'stage_id', 'owner_id', 'won', 'lost',
        ]);

        $query = CrmOpportunity::query();

        if ($request->filled('pipeline_id')) {
            $query->where('pipeline_id', (int) $request->input('pipeline_id'));
        }
        if ($request->filled('stage_id')) {
            $query->where('stage_id', (int) $request->input('stage_id'));
        }
        if ($request->filled('owner_id')) {
            $query->where('owner_id', (int) $request->input('owner_id'));
        }
        if ($request->filled('won')) {
            $query->whereHas('stage', fn ($stageQuery) => $stageQuery->where('is_won', filter_var($request->input('won'), FILTER_VALIDATE_BOOLEAN)));
        }
        if ($request->filled('lost')) {
            $query->whereHas('stage', fn ($stageQuery) => $stageQuery->where('is_lost', filter_var($request->input('lost'), FILTER_VALIDATE_BOOLEAN)));
        }

        $query = $this->applySort($query, $request, [
            'created_at' => 'created_at',
            'updated_at' => 'updated_at',
            'name' => 'name',
            'amount' => 'amount',
            'expected_close_date' => 'expected_close_date',
        ]);

        return CrmOpportunityResource::collection($query->paginate($this->perPage($request)));
    }

    public function store(StoreCrmOpportunityRequest $request): JsonResponse
    {
        $this->authorize('create', CrmOpportunity::class);

        /** @var Employee $actor */
        $actor = $request->user();

        $stage = CrmPipelineStage::query()->findOrFail((int) $request->validated('stage_id'));

        $opportunity = CrmOpportunity::query()->create([
            'company_id' => $actor->company_id,
            'pipeline_id' => (int) $request->validated('pipeline_id'),
            'stage_id' => (int) $request->validated('stage_id'),
            'name' => $request->validated('name'),
            'account_id' => $request->validated('account_id'),
            'converted_from_lead_id' => $request->validated('converted_from_lead_id'),
            'amount' => $request->validated('amount'),
            'currency' => $request->validated('currency'),
            'expected_close_date' => $request->validated('expected_close_date'),
            'owner_id' => $request->validated('owner_id'),
            'source' => $request->validated('source'),
            'description' => $request->validated('description'),
            'won_at' => $stage->is_won ? now() : null,
            'lost_at' => $stage->is_lost ? now() : null,
            'created_by' => $actor->id,
        ]);

        return new JsonResponse(['data' => new CrmOpportunityResource($opportunity)], 201);
    }

    public function show(CrmOpportunity $crmOpportunity): JsonResponse
    {
        $this->authorize('view', $crmOpportunity);

        return new JsonResponse(['data' => $crmOpportunity]);
    }

    public function update(UpdateCrmOpportunityRequest $request, CrmOpportunity $crmOpportunity): JsonResponse
    {
        $this->authorize('update', $crmOpportunity);

        $data = $request->validated();

        // Transition de stage : horodatage won/lost dérivé.
        if (array_key_exists('stage_id', $data)) {
            $stage = CrmPipelineStage::query()->findOrFail((int) $data['stage_id']);
            $data['won_at'] = $stage->is_won ? now() : null;
            $data['lost_at'] = $stage->is_lost ? now() : null;
        }

        $crmOpportunity->update($data);

        return new JsonResponse(['data' => new CrmOpportunityResource($crmOpportunity->fresh())]);
    }

    public function destroy(CrmOpportunity $crmOpportunity): JsonResponse
    {
        $this->authorize('delete', $crmOpportunity);

        $crmOpportunity->delete();

        return new JsonResponse(null, 204);
    }
}
