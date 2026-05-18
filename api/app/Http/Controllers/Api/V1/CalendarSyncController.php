<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CalendarConnection;
use App\Models\Employee;
use App\Services\CalendarSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CalendarSyncController extends Controller
{
    public function __construct(
        private readonly CalendarSyncService $syncService,
    ) {}

    public function connections(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();

        $connections = CalendarConnection::query()
            ->where('employee_id', $user->id)
            ->get()
            ->map(fn (CalendarConnection $c) => [
                'id' => $c->id,
                'provider' => $c->provider,
                'calendar_id' => $c->calendar_id,
                'sync_leaves' => $c->sync_leaves,
                'sync_training' => $c->sync_training,
                'is_active' => $c->is_active,
                'last_synced_at' => $c->last_synced_at?->toIso8601String(),
                'token_expired' => $c->isTokenExpired(),
            ]);

        return new JsonResponse(['data' => $connections]);
    }

    public function connect(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider' => ['required', 'in:google,outlook,caldav'],
            'access_token' => ['required', 'string'],
            'refresh_token' => ['nullable', 'string'],
            'calendar_id' => ['nullable', 'string', 'max:255'],
            'expires_in' => ['nullable', 'integer'],
            'sync_leaves' => ['nullable', 'boolean'],
            'sync_training' => ['nullable', 'boolean'],
        ]);

        /** @var Employee $user */
        $user = $request->user();

        $expiresAt = isset($validated['expires_in'])
            ? now()->addSeconds($validated['expires_in'])
            : null;

        $connection = $this->syncService->connect(
            $user,
            $validated['provider'],
            $validated['access_token'],
            $validated['refresh_token'] ?? null,
            $validated['calendar_id'] ?? null,
            $expiresAt
        );

        if (isset($validated['sync_leaves'])) {
            $connection->update(['sync_leaves' => $validated['sync_leaves']]);
        }
        if (isset($validated['sync_training'])) {
            $connection->update(['sync_training' => $validated['sync_training']]);
        }

        return new JsonResponse([
            'data' => [
                'id' => $connection->id,
                'provider' => $connection->provider,
                'is_active' => $connection->is_active,
            ],
        ], 201);
    }

    public function disconnect(Request $request, string $provider): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();

        $this->syncService->disconnect($user, $provider);

        return new JsonResponse(['message' => 'Calendar disconnected.']);
    }

    public function sync(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();

        $leavesSynced = $this->syncService->syncLeaves($user);
        $trainingSynced = $this->syncService->syncTraining($user);

        return new JsonResponse([
            'data' => [
                'leaves_synced' => $leavesSynced,
                'training_synced' => $trainingSynced,
            ],
        ]);
    }

    public function events(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        /** @var Employee $user */
        $user = $request->user();

        $events = $this->syncService->getEvents(
            $user,
            $validated['from'],
            $validated['to']
        );

        return new JsonResponse(['data' => $events]);
    }
}
