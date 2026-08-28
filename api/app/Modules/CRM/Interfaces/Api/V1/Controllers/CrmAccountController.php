<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CrmAccountResource;
use App\Modules\CRM\Domain\Models\CrmAccount;
use App\Modules\CRM\Interfaces\Api\V1\Requests\IndexCrmAccountRequest;
use App\Modules\CRM\Interfaces\Api\V1\Requests\StoreCrmAccountRequest;
use App\Modules\CRM\Interfaces\Api\V1\Requests\UpdateCrmAccountRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * CRUD des comptes CRM client — Issue #5711 (CRM-V0-07).
 *
 * RBAC : middleware `api.manager:principal,rh` + Policy `CrmAccountPolicy`
 * (garde unique). Isolation tenant : scope `BelongsToCompany` (404
 * cross-tenant). PII `phone`/`tax_id` chiffrées au repos (casts encrypted).
 */
class CrmAccountController extends Controller
{
    public function index(IndexCrmAccountRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', CrmAccount::class);

        $validated = $request->validated();
        $perPage = (int) ($validated['per_page'] ?? 20);

        $query = CrmAccount::query()->withCount('contacts');

        foreach (['status', 'source'] as $filter) {
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
                $builder->where('name', 'like', $needle)
                    ->orWhere('email', 'like', $needle)
                    ->orWhere('legal_name', 'like', $needle);
            });
        }

        $sortBy = (string) ($validated['sort_by'] ?? 'name');
        $sortDir = (string) ($validated['sort_dir'] ?? 'asc');
        $query->orderBy($sortBy, $sortDir)->orderBy('id', $sortDir);

        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => CrmAccountResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(StoreCrmAccountRequest $request): JsonResponse
    {
        Gate::authorize('create', CrmAccount::class);

        $account = CrmAccount::query()->create($request->validated());

        return response()->json([
            'data' => new CrmAccountResource($account),
        ], 201);
    }

    public function show(Request $request, CrmAccount $account): JsonResponse
    {
        Gate::authorize('view', $account);

        return response()->json([
            'data' => new CrmAccountResource($account->loadCount('contacts')),
        ]);
    }

    public function update(UpdateCrmAccountRequest $request, CrmAccount $account): JsonResponse
    {
        Gate::authorize('update', $account);

        $account->update($request->validated());

        return response()->json([
            'data' => new CrmAccountResource($account->refresh()),
        ]);
    }

    public function destroy(Request $request, CrmAccount $account): JsonResponse
    {
        Gate::authorize('delete', $account);

        // Archivage doux : passer en `archived` plutôt que supprimer les
        // contacts et l'historique commercial (contrainte #5708).
        $account->update(['status' => CrmAccount::STATUS_ARCHIVED]);

        return response()->json(null, 204);
    }
}
