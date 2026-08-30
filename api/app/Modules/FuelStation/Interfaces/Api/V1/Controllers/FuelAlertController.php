<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Feature\Infrastructure\Services\FeatureFlag;
use App\Http\Controllers\Controller;
use App\Modules\FuelStation\Domain\Exceptions\FuelSolutionInactiveException;
use App\Modules\FuelStation\Domain\Models\FuelAlert;
use App\Modules\FuelStation\Domain\Models\FuelNotificationPreference;
use App\Modules\FuelStation\Infrastructure\Services\FuelAlertService;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\UpdateFuelNotificationPreferencesRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Alertes & préférences de notification FuelStation (FUEL-019, #5813).
 *
 * Manager + solution active (fail-closed) + tenant-scoped (404
 * cross-tenant). Canaux désactivables par type d'événement et par station ;
 * alertes dédupliquées ; cycle open → acknowledged → resolved.
 */
class FuelAlertController extends Controller
{
    public function __construct(
        private readonly FuelAlertService $service,
    ) {}

    public function preferences(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', FuelNotificationPreference::class);

        $preferences = FuelNotificationPreference::query()
            ->where('company_id', $actor->company_id)
            ->orderBy('event_type')
            ->orderBy('channel')
            ->get();

        return response()->json([
            'data' => $preferences->map(fn (FuelNotificationPreference $preference): array => [
                'id' => $preference->id,
                'station_id' => $preference->station_id,
                'event_type' => $preference->event_type,
                'channel' => $preference->channel,
                'enabled' => (bool) $preference->enabled,
            ]),
        ]);
    }

    public function updatePreferences(UpdateFuelNotificationPreferencesRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('managePreferences', new FuelNotificationPreference);

        $preferences = $request->input('preferences');

        if (! is_array($preferences)) {
            abort(422);
        }

        $count = $this->service->upsertPreferences($actor->company_id, $preferences);

        return response()->json(['data' => ['updated' => $count]]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', FuelAlert::class);

        $query = FuelAlert::query()->where('company_id', $actor->company_id);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('event_type')) {
            $query->where('event_type', $request->input('event_type'));
        }

        if ($request->filled('station_id')) {
            $query->where('station_id', $request->integer('station_id'));
        }

        $alerts = $query->orderByDesc('created_at')->paginate(max(1, min(100, $request->integer('per_page', 15))));

        return response()->json([
            'data' => collect($alerts->items())->map(fn (FuelAlert $alert): array => $this->payload($alert)),
            'meta' => [
                'current_page' => $alerts->currentPage(),
                'last_page' => $alerts->lastPage(),
                'total' => $alerts->total(),
            ],
        ]);
    }

    public function acknowledge(Request $request, FuelAlert $alert): JsonResponse
    {
        return $this->transition($request, $alert, FuelAlert::STATUS_ACKNOWLEDGED);
    }

    public function resolve(Request $request, FuelAlert $alert): JsonResponse
    {
        return $this->transition($request, $alert, FuelAlert::STATUS_RESOLVED);
    }

    private function transition(Request $request, FuelAlert $alert, string $target): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($alert->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->authorize('update', $alert);

        $alert->update([
            'status' => $target,
            'resolved_at' => $target === FuelAlert::STATUS_RESOLVED ? now() : null,
        ]);

        return response()->json(['data' => $this->payload($alert->refresh())]);
    }

    private function assertSolutionActive(): void
    {
        if (! FeatureFlag::enabled('fuel_station', currentCompany())) {
            throw new FuelSolutionInactiveException;
        }
    }

    /** @return array<string, mixed> */
    private function payload(FuelAlert $alert): array
    {
        return [
            'id' => $alert->id,
            'station_id' => $alert->station_id,
            'event_type' => $alert->event_type,
            'severity' => $alert->severity,
            'alert_key' => $alert->alert_key,
            'payload' => $alert->payload,
            'status' => $alert->status,
            'resolved_at' => $alert->resolved_at?->toISOString(),
            'created_at' => $alert->created_at?->toISOString(),
        ];
    }
}
