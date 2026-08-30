<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Feature\Infrastructure\Services\FeatureFlag;
use App\Http\Controllers\Controller;
use App\Modules\FuelStation\Domain\Exceptions\FuelSolutionInactiveException;
use App\Modules\FuelStation\Domain\Models\FuelOutboxEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Outbox FuelStation — audit & rejeu (FUEL-015, #5809).
 *
 * Manager + solution active (fail-closed) + tenant-scoped. Le rejeu
 * manuel repasse un événement `failed` (dead-letter) en `pending` avec
 * `available_at = now` (budget de tentatives réinitialisé).
 */
class FuelOutboxController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', FuelOutboxEvent::class);

        $query = FuelOutboxEvent::query()->where('company_id', $actor->company_id);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('event_type')) {
            $query->where('event_type', $request->input('event_type'));
        }

        $events = $query->orderByDesc('id')->paginate(max(1, min(100, $request->integer('per_page', 15))));

        return response()->json([
            'data' => collect($events->items())->map(fn (FuelOutboxEvent $event): array => $this->payload($event)),
            'meta' => [
                'current_page' => $events->currentPage(),
                'last_page' => $events->lastPage(),
                'total' => $events->total(),
            ],
        ]);
    }

    public function retry(Request $request, FuelOutboxEvent $event): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($event->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->authorize('retry', $event);

        if ($event->status !== FuelOutboxEvent::STATUS_FAILED) {
            return response()->json([
                'error' => 'FUEL_OUTBOX_NOT_FAILED',
                'message' => 'Seul un événement dead-letter (failed) peut être rejoué.',
            ], 422);
        }

        $event->forceFill([
            'status' => FuelOutboxEvent::STATUS_PENDING,
            'attempts' => 0,
            'available_at' => now(),
            'last_error' => null,
        ])->save();

        return response()->json(['data' => $this->payload($event->refresh())]);
    }

    private function assertSolutionActive(): void
    {
        if (! FeatureFlag::enabled('fuel_station', currentCompany())) {
            throw new FuelSolutionInactiveException;
        }
    }

    /** @return array<string, mixed> */
    private function payload(FuelOutboxEvent $event): array
    {
        return [
            'id' => $event->id,
            'event_type' => $event->event_type,
            'aggregate_type' => $event->aggregate_type,
            'aggregate_id' => $event->aggregate_id,
            'status' => $event->status,
            'attempts' => $event->attempts,
            'available_at' => $event->available_at?->toISOString(),
            'last_error' => $event->last_error,
            'processed_at' => $event->processed_at?->toISOString(),
            'created_at' => $event->created_at?->toISOString(),
        ];
    }
}
