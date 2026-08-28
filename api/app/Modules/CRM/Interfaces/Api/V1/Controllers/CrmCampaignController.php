<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CRM\Application\Services\CampaignService;
use App\Modules\CRM\Domain\Enums\CampaignStatus;
use App\Modules\CRM\Domain\Models\CrmCampaign;
use App\Modules\CRM\Interfaces\Api\V1\Requests\StoreCampaignRequest;
use App\Modules\CRM\Interfaces\Api\V1\Requests\UpdateCampaignRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Campagnes marketing tenant — Issue #5724.
 *
 * RBAC : lecture / report = tout manager du tenant ; écritures et actions
 * (start/pause/resume/cancel/finish) = `principal` / `marketing`
 * (middleware + Policy `CrmCampaignPolicy`). Isolation tenant :
 * `CrmCampaign` porte `BelongsToCompany` (404 cross-tenant).
 */
class CrmCampaignController extends Controller
{
    public function __construct(private readonly CampaignService $campaigns) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CrmCampaign::class);

        $validated = $request->validate([
            'status' => ['nullable', 'string', 'in:'.implode(',', CampaignStatus::values())],
            'channel' => ['nullable', 'string', 'in:email,sms,whatsapp'],
            'search' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 20);

        $query = CrmCampaign::query()
            ->withCount('sends')
            ->orderByDesc('id');

        if (! empty($validated['status'])) {
            $query->where('status', (string) $validated['status']);
        }

        if (! empty($validated['channel'])) {
            $query->where('channel', (string) $validated['channel']);
        }

        if (! empty($validated['search'])) {
            $query->where('name', 'like', '%'.addcslashes((string) $validated['search'], '%_\\').'%');
        }

        $paginator = $query->paginate($perPage);

        $items = [];
        foreach ($paginator->items() as $campaign) {
            $items[] = $this->serialize($campaign);
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

    public function store(StoreCampaignRequest $request): JsonResponse
    {
        $this->authorize('create', CrmCampaign::class);

        $campaign = $this->campaigns->create(
            $request->string('name')->toString(),
            $request->filled('description') ? $request->string('description')->toString() : null,
            $request->string('channel')->toString(),
            $request->filled('segment_id') ? $request->integer('segment_id') : null,
            $this->audience($request),
            $request->filled('scheduled_at') ? now()->parse($request->string('scheduled_at')->toString()) : null,
            $this->actorId(),
        );

        return response()->json(['data' => $this->serialize($campaign->loadCount('sends'))], 201);
    }

    public function show(CrmCampaign $campaign): JsonResponse
    {
        $this->assertTenantScope(request(), $campaign);
        $this->authorize('view', $campaign);

        return response()->json(['data' => $this->serialize($campaign->loadCount('sends'))]);
    }

    public function update(UpdateCampaignRequest $request, CrmCampaign $campaign): JsonResponse
    {
        $this->assertTenantScope($request, $campaign);
        $this->authorize('update', $campaign);

        $name = $request->filled('name') ? $request->string('name')->toString() : $campaign->name;
        $description = $request->has('description')
            ? ($request->filled('description') ? $request->string('description')->toString() : null)
            : $campaign->description;
        $segmentId = $request->has('segment_id')
            ? ($request->filled('segment_id') ? $request->integer('segment_id') : null)
            : $campaign->segment_id;
        $audience = $request->has('audience') ? $this->audience($request) : ($campaign->audience_snapshot ?? []);
        $scheduledAt = $request->has('scheduled_at')
            ? ($request->filled('scheduled_at') ? now()->parse($request->string('scheduled_at')->toString()) : null)
            : $campaign->scheduled_at;

        $campaign = $this->campaigns->update(
            $campaign,
            $name,
            $description,
            $segmentId,
            $audience,
            $scheduledAt,
            $this->actorId(),
        );

        return response()->json(['data' => $this->serialize($campaign->loadCount('sends'))]);
    }

    public function destroy(CrmCampaign $campaign): JsonResponse
    {
        $this->assertTenantScope(request(), $campaign);
        $this->authorize('delete', $campaign);

        $campaign->sends()->delete();
        $campaign->delete();

        return response()->json(null, 204);
    }

    public function start(CrmCampaign $campaign): JsonResponse
    {
        $this->assertTenantScope(request(), $campaign);
        $this->authorize('start', $campaign);

        return response()->json(['data' => $this->serialize($this->campaigns->start($campaign, $this->actorId())->loadCount('sends'))]);
    }

    public function pause(CrmCampaign $campaign): JsonResponse
    {
        $this->assertTenantScope(request(), $campaign);
        $this->authorize('pause', $campaign);

        return response()->json(['data' => $this->serialize($this->campaigns->pause($campaign, $this->actorId()))]);
    }

    public function resume(CrmCampaign $campaign): JsonResponse
    {
        $this->assertTenantScope(request(), $campaign);
        $this->authorize('resume', $campaign);

        return response()->json(['data' => $this->serialize($this->campaigns->resume($campaign, $this->actorId()))]);
    }

    public function cancel(CrmCampaign $campaign): JsonResponse
    {
        $this->assertTenantScope(request(), $campaign);
        $this->authorize('cancel', $campaign);

        return response()->json(['data' => $this->serialize($this->campaigns->cancel($campaign, $this->actorId()))]);
    }

    public function finish(CrmCampaign $campaign): JsonResponse
    {
        $this->assertTenantScope(request(), $campaign);
        $this->authorize('finish', $campaign);

        return response()->json(['data' => $this->serialize($this->campaigns->finish($campaign, $this->actorId()))]);
    }

    public function report(CrmCampaign $campaign): JsonResponse
    {
        $this->assertTenantScope(request(), $campaign);
        $this->authorize('report', $campaign);

        return response()->json(['data' => $this->campaigns->report($campaign)]);
    }

    /**
     * @return list<int>
     */
    private function audience(Request $request): array
    {
        $audience = $request->input('audience');

        if (! is_array($audience)) {
            return [];
        }

        $ids = [];
        foreach ($audience as $contactId) {
            if (is_numeric($contactId)) {
                $ids[] = (int) $contactId;
            }
        }

        return $ids;
    }

    private function assertTenantScope(Request $request, CrmCampaign $campaign): void
    {
        // Binding implicite résolu avant le middleware tenant
        // (SubstituteBindings global) : garde explicite 404 cross-tenant
        // (même pattern que AccountingContactController::assertTenantScope).
        $companyId = $request->user()?->getAttribute('company_id');

        if ($companyId !== null && (string) $campaign->company_id !== (string) $companyId) {
            abort(404);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(CrmCampaign $campaign): array
    {
        return [
            'id' => $campaign->id,
            'name' => $campaign->name,
            'description' => $campaign->description,
            'channel' => $campaign->channel,
            'status' => $campaign->status,
            'segment_id' => $campaign->segment_id,
            'audience_snapshot' => $campaign->audience_snapshot,
            'scheduled_at' => $campaign->scheduled_at?->toIso8601String(),
            'started_at' => $campaign->started_at?->toIso8601String(),
            'finished_at' => $campaign->finished_at?->toIso8601String(),
            'sends_count' => $campaign->sends_count,
            'created_at' => $campaign->created_at?->toIso8601String(),
            'updated_at' => $campaign->updated_at?->toIso8601String(),
        ];
    }

    private function actorId(): ?int
    {
        $userId = request()->user()?->getAuthIdentifier();

        return $userId !== null ? (int) $userId : null;
    }
}
