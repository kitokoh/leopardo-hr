<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CrmActivityResource;
use App\Modules\CRM\Domain\Models\CrmActivity;
use App\Modules\CRM\Interfaces\Api\V1\Requests\IndexCrmActivityRequest;
use App\Modules\CRM\Interfaces\Api\V1\Requests\StoreCrmActivityRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Timeline CRM (activities, append-only) — Issue #5711 (CRM-V0-07).
 *
 * Append-only : aucune route de mise à jour. Création par les managers
 * `principal`/`rh` (Policy `CrmActivityPolicy`), suppression par les mêmes
 * managers. Isolation tenant : scope `BelongsToCompany` (404 cross-tenant).
 * Chaque mutation est tracée dans `audit_logs` (trait Auditable).
 */
class CrmActivityController extends Controller
{
    public function index(IndexCrmActivityRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', CrmActivity::class);

        $validated = $request->validated();
        $perPage = (int) ($validated['per_page'] ?? 20);

        $query = CrmActivity::query();

        if (! empty($validated['activity_type'])) {
            $query->where('activity_type', (string) $validated['activity_type']);
        }

        if (! empty($validated['related_type'])) {
            $query->where('related_type', (string) $validated['related_type']);
        }

        if (! empty($validated['related_id'])) {
            $query->where('related_id', (int) $validated['related_id']);
        }

        if (! empty($validated['owner_id'])) {
            $query->where('owner_id', (int) $validated['owner_id']);
        }

        $sortBy = (string) ($validated['sort_by'] ?? 'happened_at');
        $sortDir = (string) ($validated['sort_dir'] ?? 'desc');
        $query->orderBy($sortBy, $sortDir)->orderBy('id', $sortDir);

        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => CrmActivityResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(StoreCrmActivityRequest $request): JsonResponse
    {
        Gate::authorize('create', CrmActivity::class);

        $payload = $request->validated();
        $payload['happened_at'] ??= now();

        $activity = CrmActivity::query()->create($payload);

        return response()->json([
            'data' => new CrmActivityResource($activity),
        ], 201);
    }

    public function show(Request $request, CrmActivity $activity): JsonResponse
    {
        Gate::authorize('view', $activity);

        return response()->json([
            'data' => new CrmActivityResource($activity),
        ]);
    }

    public function destroy(Request $request, CrmActivity $activity): JsonResponse
    {
        Gate::authorize('delete', $activity);

        $activity->delete();

        return response()->json(null, 204);
    }
}
