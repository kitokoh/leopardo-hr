<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CrmLeadResource;
use App\Modules\CRM\Domain\Models\CrmLead;
use App\Modules\CRM\Interfaces\Api\V1\Requests\StoreCrmLeadRequest;
use App\Modules\CRM\Interfaces\Api\V1\Requests\UpdateCrmLeadRequest;
use App\Modules\CRM\Interfaces\Api\V1\Support\CrmQueryHelpers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Issue #5711 — Leads CRM client (tenant).
 *
 * Filtres et tri strictement allowlistés ; un employé non-manager ne voit
 * que les leads dont il est l'owner (Policy `view`), les mutations restent
 * réservées aux managers `principal`/`rh`/`marketing`.
 */
class CrmLeadController extends Controller
{
    use CrmQueryHelpers;
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CrmLead::class);
        $this->rejectUnknownQueryKeys($request, [
            'per_page', 'sort_by', 'sort_dir',
            'status', 'priority', 'source', 'owner_id',
        ]);

        $query = CrmLead::query();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('priority')) {
            $query->where('priority', $request->input('priority'));
        }
        if ($request->filled('source')) {
            $query->where('source', $request->input('source'));
        }
        if ($request->filled('owner_id')) {
            $query->where('owner_id', (int) $request->input('owner_id'));
        }

        $query = $this->applySort($query, $request, [
            'created_at' => 'created_at',
            'updated_at' => 'updated_at',
            'first_name' => 'first_name',
            'last_name' => 'last_name',
            'email' => 'email',
            'priority' => 'priority',
        ]);

        return CrmLeadResource::collection($query->paginate($this->perPage($request)));
    }

    public function store(StoreCrmLeadRequest $request): JsonResponse
    {
        $this->authorize('create', CrmLead::class);

        /** @var Employee $actor */
        $actor = $request->user();

        $lead = CrmLead::query()->create([
            'company_id' => $actor->company_id,
            'first_name' => $request->validated('first_name'),
            'last_name' => $request->validated('last_name'),
            'email' => $request->validated('email'),
            'phone' => $request->validated('phone'),
            'company_name' => $request->validated('company_name'),
            'source' => $request->validated('source'),
            'status' => $request->validated('status', CrmLead::STATUS_NEW),
            'priority' => $request->validated('priority', CrmLead::PRIORITY_MEDIUM),
            'owner_id' => $request->validated('owner_id'),
            'notes' => $request->validated('notes'),
            'created_by' => $actor->id,
        ]);

        return new JsonResponse(['data' => new CrmLeadResource($lead)], 201);
    }

    public function show(CrmLead $crmLead): JsonResponse
    {
        $this->authorize('view', $crmLead);

        return new JsonResponse(['data' => $crmLead]);
    }

    public function update(UpdateCrmLeadRequest $request, CrmLead $crmLead): JsonResponse
    {
        $this->authorize('update', $crmLead);

        $crmLead->update($request->validated());

        return new JsonResponse(['data' => new CrmLeadResource($crmLead->fresh())]);
    }

    public function destroy(CrmLead $crmLead): JsonResponse
    {
        $this->authorize('delete', $crmLead);

        $crmLead->delete();

        return new JsonResponse(null, 204);
    }
}
