<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CrmLeadResource;
use App\Modules\CRM\Domain\Models\CrmLead;
use App\Modules\CRM\Interfaces\Api\V1\Requests\IndexCrmLeadRequest;
use App\Modules\CRM\Interfaces\Api\V1\Requests\StoreCrmLeadRequest;
use App\Modules\CRM\Interfaces\Api\V1\Requests\UpdateCrmLeadRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * CRUD des leads CRM client — Issue #5711 (CRM-V0-07).
 *
 * RBAC : middleware `api.manager:principal,rh` (403 pour les non-managers),
 * puis Policy `CrmLeadPolicy` comme garde applicative UNIQUE (aucune garde
 * inline). Isolation tenant : scope global `BelongsToCompany` → un lead
 * d'un autre tenant est introuvable (404), jamais visible.
 */
class CrmLeadController extends Controller
{
    public function index(IndexCrmLeadRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', CrmLead::class);

        $validated = $request->validated();
        $perPage = (int) ($validated['per_page'] ?? 20);

        $query = CrmLead::query();

        foreach (['status', 'priority', 'source'] as $filter) {
            if (! empty($validated[$filter])) {
                $query->where($filter, (string) $validated[$filter]);
            }
        }

        if (! empty($validated['owner_id'])) {
            $query->where('owner_id', (int) $validated['owner_id']);
        }

        if (! empty($validated['search'])) {
            $needle = '%'.addcslashes((string) $validated['search'], '%_\\').'%';
            $query->where(function ($builder) use ($needle): void {
                $builder->where('first_name', 'like', $needle)
                    ->orWhere('last_name', 'like', $needle)
                    ->orWhere('email', 'like', $needle)
                    ->orWhere('company_name', 'like', $needle);
            });
        }

        $sortBy = (string) ($validated['sort_by'] ?? 'created_at');
        $sortDir = (string) ($validated['sort_dir'] ?? 'desc');
        $query->orderBy($sortBy, $sortDir)->orderBy('id', $sortDir);

        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => CrmLeadResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(StoreCrmLeadRequest $request): JsonResponse
    {
        Gate::authorize('create', CrmLead::class);

        $payload = $request->validated();
        $payload['created_by'] = $request->user()?->id;

        $lead = CrmLead::query()->create($payload);

        return response()->json([
            'data' => new CrmLeadResource($lead),
        ], 201);
    }

    public function show(Request $request, CrmLead $lead): JsonResponse
    {
        Gate::authorize('view', $lead);

        return response()->json([
            'data' => new CrmLeadResource($lead),
        ]);
    }

    public function update(UpdateCrmLeadRequest $request, CrmLead $lead): JsonResponse
    {
        Gate::authorize('update', $lead);

        $lead->update($request->validated());

        return response()->json([
            'data' => new CrmLeadResource($lead->refresh()),
        ]);
    }

    public function destroy(Request $request, CrmLead $lead): JsonResponse
    {
        Gate::authorize('delete', $lead);

        $lead->delete();

        return response()->json(null, 204);
    }
}
