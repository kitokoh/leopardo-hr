<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CRM\Application\Services\SegmentService;
use App\Modules\CRM\Domain\Models\CrmSegment;
use App\Modules\CRM\Domain\Models\CrmSegmentMember;
use App\Modules\CRM\Interfaces\Api\V1\Requests\StoreSegmentRequest;
use App\Modules\CRM\Interfaces\Api\V1\Requests\UpdateSegmentRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Segments CRM — Issue #5723.
 *
 * RBAC : lecture = tout manager du tenant ; écritures / rebuild =
 * `principal` / `marketing` (middleware + Policy `CrmSegmentPolicy`).
 * Isolation tenant : `CrmSegment` porte `BelongsToCompany` (404 cross-tenant).
 */
class CrmSegmentController extends Controller
{
    public function __construct(private readonly SegmentService $segments) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CrmSegment::class);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'is_active' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 20);

        $query = CrmSegment::query()
            ->orderBy('name');

        if (! empty($validated['search'])) {
            $query->where('name', 'like', '%'.addcslashes((string) $validated['search'], '%_\\').'%');
        }

        if (array_key_exists('is_active', $validated)) {
            $query->where('is_active', (bool) $validated['is_active']);
        }

        $paginator = $query->withCount('members')->paginate($perPage);

        $items = [];
        foreach ($paginator->items() as $segment) {
            $items[] = $this->serialize($segment);
        }

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(StoreSegmentRequest $request): JsonResponse
    {
        $this->authorize('create', CrmSegment::class);

        $segment = $this->segments->create(
            $request->string('name')->toString(),
            $request->filled('description') ? $request->string('description')->toString() : null,
            $request->input('definition'),
            $this->actorId(),
        );

        return response()->json(['data' => $this->serialize($segment->loadCount('members'))], 201);
    }

    public function show(CrmSegment $segment): JsonResponse
    {
        $this->authorize('view', $segment);

        return response()->json(['data' => $this->serialize($segment->loadCount('members'))]);
    }

    public function update(UpdateSegmentRequest $request, CrmSegment $segment): JsonResponse
    {
        $this->authorize('update', $segment);

        $definition = $request->has('definition')
            ? $request->input('definition')
            : $segment->definition;

        $updated = $this->segments->update(
            $segment,
            $request->input('name', $segment->name),
            $request->has('description') ? $request->input('description') : $segment->description,
            $definition,
            $this->actorId(),
        );

        if ($request->has('is_active')) {
            $updated = $this->segments->toggleActive($updated, (bool) $request->input('is_active'), $this->actorId());
        }

        return response()->json(['data' => $this->serialize($updated->loadCount('members'))]);
    }

    public function destroy(CrmSegment $segment): JsonResponse
    {
        $this->authorize('delete', $segment);

        $this->segments->destroy($segment, $this->actorId());

        return response()->json(null, 204);
    }

    public function rebuild(CrmSegment $segment): JsonResponse
    {
        $this->authorize('rebuild', $segment);

        $rebuild = $this->segments->rebuild($segment, $this->actorId());

        return response()->json(['data' => $this->serialize($rebuild->loadCount('members'))]);
    }

    public function members(CrmSegment $segment, Request $request): JsonResponse
    {
        $this->authorize('view', $segment);

        $validated = $request->validate([
            'source' => ['nullable', 'string', 'in:computed,manual'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 50);

        $query = CrmSegmentMember::query()
            ->where('segment_id', $segment->id)
            ->orderByDesc('id');

        if (! empty($validated['source'])) {
            $query->where('source', (string) $validated['source']);
        }

        $paginator = $query->paginate($perPage);

        $items = [];
        foreach ($paginator->items() as $member) {
            $items[] = [
                'contact_id' => $member->contact_id,
                'source' => $member->source,
                'built_at' => $member->built_at?->toIso8601String(),
            ];
        }

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(CrmSegment $segment): array
    {
        return [
            'id' => $segment->id,
            'name' => $segment->name,
            'description' => $segment->description,
            'definition' => $segment->definition,
            'version' => $segment->version,
            'is_active' => $segment->is_active,
            'members_count' => $segment->members_count,
            'created_at' => $segment->created_at?->toIso8601String(),
            'updated_at' => $segment->updated_at?->toIso8601String(),
        ];
    }

    private function actorId(): ?int
    {
        $userId = request()->user()?->getAuthIdentifier();

        return $userId !== null ? (int) $userId : null;
    }
}
