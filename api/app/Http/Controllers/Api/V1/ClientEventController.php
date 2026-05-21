<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreClientEventRequest;
use App\Models\ClientEvent;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;

class ClientEventController extends Controller
{
    public function store(StoreClientEventRequest $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();
        if (! $employee->company_id) {
            return response()->json([
                'message' => 'Client event requires an attached company.',
            ], 403);
        }

        $validated = $request->validated();

        $event = ClientEvent::create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'event_name' => $validated['name'],
            'surface' => $validated['surface'] ?? 'web',
            'session_id' => $validated['session_id'] ?? null,
            'duration_ms' => $validated['duration_ms'] ?? $this->durationFromProperties($validated['properties'] ?? []),
            'properties' => $this->sanitizeProperties($validated['properties'] ?? []),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'occurred_at' => $validated['occurred_at'] ?? now(),
        ]);

        return response()->json([
            'data' => [
                'id' => $event->id,
                'name' => $event->event_name,
                'stored' => true,
            ],
        ], 201);
    }

    /**
     * @param  array<mixed>  $properties
     * @return array<string, scalar|null>
     */
    private function sanitizeProperties(array $properties): array
    {
        $allowed = [
            'role',
            'manager_role',
            'locale',
            'target',
            'company_id',
            'surface',
            'module',
            'reason',
            'state',
            'active_modules',
            'locked_modules',
            'country',
            'email_domain',
            'duration_ms',
            'status',
        ];

        $sanitized = [];
        foreach ($allowed as $key) {
            if (! array_key_exists($key, $properties)) {
                continue;
            }

            $value = $properties[$key];
            if (is_scalar($value) || $value === null) {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * @param  array<mixed>  $properties
     */
    private function durationFromProperties(array $properties): ?int
    {
        $duration = $properties['duration_ms'] ?? null;

        return is_numeric($duration) ? max(0, min(600000, (int) $duration)) : null;
    }
}
