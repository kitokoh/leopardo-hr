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

        $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'is_active' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = $request->integer('per_page', 20);
        $search = $request->string('search')->toString();

        $query = CrmSegment::query()
            ->orderBy('name');

        if ($search !== '') {
            $query->where('name', 'like', '%'.addcslashes($search, '%_\\').'%');
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
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

        $definitionInput = $request->input('definition');
        /** @var array<string, mixed> $definition */
        $definition = is_array($definitionInput) ? $definitionInput : [];

        $segment = $this->segments->create(
            $request->string('name')->toString(),
            $request->filled('description') ? $request->string('description')->toString() : null,
            $definition,
            $this->actorId(),
        );

        return response()->json(['data' => $this->serialize($segment->loadCount('members'))], 201);
    }

    public function show(CrmSegment $segment): JsonResponse
    {
        $this->assertTenantScope(request(), $segment);
        $this->authorize('view', $segment);

        return response()->json(['data' => $this->serialize($segment->loadCount('members'))]);
    }

    public function update(UpdateSegmentRequest $request, CrmSegment $segment): JsonResponse
    {
        $this->assertTenantScope($request, $segment);
        $this->authorize('update', $segment);

        $name = $request->filled('name') ? $request->string('name')->toString() : $segment->name;
        $description = $request->has('description')
            ? ($request->filled('description') ? $request->string('description')->toString() : null)
            : $segment->description;

        $definition = $segment->definition;
        if ($request->has('definition')) {
            $definitionInput = $request->input('definition');
            if (is_array($definitionInput)) {
                /** @var array<string, mixed> $definition */
                $definition = $definitionInput;
            }
        }

        $updated = $this->segments->update(
            $segment,
            $name,
            $description,
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
        $this->assertTenantScope(request(), $segment);
        $this->authorize('delete', $segment);

        $this->segments->destroy($segment, $this->actorId());

        return response()->json(null, 204);
    }

    public function rebuild(CrmSegment $segment): JsonResponse
    {
        $this->assertTenantScope(request(), $segment);
        $this->authorize('rebuild', $segment);

        $rebuild = $this->segments->rebuild($segment, $this->actorId());

        return response()->json(['data' => $this->serialize($rebuild->loadCount('members'))]);
    }

    public function members(CrmSegment $segment, Request $request): JsonResponse
    {
        $this->assertTenantScope($request, $segment);
        $this->authorize('view', $segment);

        $request->validate([
            'source' => ['nullable', 'string', 'in:computed,manual'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = $request->integer('per_page', 50);
        $source = $request->string('source')->toString();

        $query = CrmSegmentMember::query()
            ->where('segment_id', $segment->id)
            ->orderByDesc('id');

        if ($source !== '') {
            $query->where('source', $source);
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

    private function assertTenantScope(Request $request, CrmSegment $segment): void
    {
        // Binding implicite résolu avant le middleware tenant
        // (SubstituteBindings global) : garde explicite 404 cross-tenant
        // (même pattern que AccountingContactController::assertTenantScope).
        $companyId = $request->user()?->getAttribute('company_id');

        if (is_string($companyId) && (string) $segment->company_id !== $companyId) {
            abort(404);
        }
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

        return is_numeric($userId) ? (int) $userId : null;
    }
}
