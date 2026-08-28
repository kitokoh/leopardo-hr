<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CrmContactResource;
use App\Modules\CRM\Domain\Models\CrmContact;
use App\Modules\CRM\Interfaces\Api\V1\Requests\IndexCrmContactRequest;
use App\Modules\CRM\Interfaces\Api\V1\Requests\StoreCrmContactRequest;
use App\Modules\CRM\Interfaces\Api\V1\Requests\UpdateCrmContactRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * CRUD des contacts CRM client — Issue #5711 (CRM-V0-07).
 *
 * RBAC : middleware `api.manager:principal,rh` + Policy `CrmContactPolicy`
 * (garde unique). Un seul contact primaire par compte (contrôle applicatif
 * dans le Form Request + index unique partiel en base). Isolation tenant :
 * FK composite (account_id, company_id) + scope `BelongsToCompany` (404).
 */
class CrmContactController extends Controller
{
    public function index(IndexCrmContactRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', CrmContact::class);

        $validated = $request->validated();
        $perPage = (int) ($validated['per_page'] ?? 20);

        $query = CrmContact::query();

        if (! empty($validated['account_id'])) {
            $query->where('account_id', (int) $validated['account_id']);
        }

        if (! empty($validated['status'])) {
            $query->where('status', (string) $validated['status']);
        }

        if (isset($validated['is_primary'])) {
            $query->where('is_primary', filter_var($validated['is_primary'], FILTER_VALIDATE_BOOLEAN));
        }

        if (! empty($validated['search'])) {
            $needle = '%'.addcslashes((string) $validated['search'], '%_\\').'%';
            $query->where(function ($builder) use ($needle): void {
                $builder->where('first_name', 'like', $needle)
                    ->orWhere('last_name', 'like', $needle)
                    ->orWhere('email', 'like', $needle);
            });
        }

        $sortBy = (string) ($validated['sort_by'] ?? 'last_name');
        $sortDir = (string) ($validated['sort_dir'] ?? 'asc');
        $query->orderBy($sortBy, $sortDir)->orderBy('id', $sortDir);

        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => CrmContactResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(StoreCrmContactRequest $request): JsonResponse
    {
        Gate::authorize('create', CrmContact::class);

        $contact = CrmContact::query()->create($request->validated());

        return response()->json([
            'data' => new CrmContactResource($contact),
        ], 201);
    }

    public function show(Request $request, CrmContact $contact): JsonResponse
    {
        Gate::authorize('view', $contact);

        return response()->json([
            'data' => new CrmContactResource($contact),
        ]);
    }

    public function update(UpdateCrmContactRequest $request, CrmContact $contact): JsonResponse
    {
        Gate::authorize('update', $contact);

        $contact->update($request->validated());

        return response()->json([
            'data' => new CrmContactResource($contact->refresh()),
        ]);
    }

    public function destroy(Request $request, CrmContact $contact): JsonResponse
    {
        Gate::authorize('delete', $contact);

        $contact->update(['status' => 'archived']);

        return response()->json(null, 204);
    }
}
