<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Employee;
use App\Http\Controllers\Controller;
use App\Models\WebhookEndpoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WebhookController extends Controller
{
    public const AVAILABLE_EVENTS = [
        'employee.created',
        'employee.updated',
        'employee.archived',
        'attendance.checked_in',
        'attendance.checked_out',
        'absence.requested',
        'absence.approved',
        'absence.rejected',
        'contract.created',
        'contract.activated',
        'contract.terminated',
        'payroll.validated',
        'loan.approved',
        'loan.disbursed',
        'expense.submitted',
        'expense.approved',
    ];

    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->hasManagerRole('principal')) {
            abort(403);
        }

        $webhooks = WebhookEndpoint::query()
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (WebhookEndpoint $w) => [
                'id' => $w->id,
                'url' => $w->url,
                'events' => $w->events,
                'active' => $w->active,
                'failure_count' => $w->failure_count,
                'last_triggered_at' => $w->last_triggered_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $webhooks]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->hasManagerRole('principal')) {
            abort(403);
        }

        $validated = $request->validate([
            'url' => 'required|url|max:500',
            'events' => 'required|array|min:1',
            'events.*' => 'string|in:'.implode(',', self::AVAILABLE_EVENTS),
        ]);

        $webhook = WebhookEndpoint::create([
            'company_id' => $actor->company_id,
            'url' => $validated['url'],
            'events' => $validated['events'],
            'secret' => Str::random(40),
            'active' => true,
        ]);

        return response()->json([
            'data' => [
                'id' => $webhook->id,
                'url' => $webhook->url,
                'events' => $webhook->events,
                'secret' => $webhook->secret,
                'active' => $webhook->active,
            ],
        ], 201);
    }

    public function show(Request $request, WebhookEndpoint $webhookEndpoint): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->hasManagerRole('principal')) {
            abort(403);
        }
        if ($webhookEndpoint->company_id !== $actor->company_id) {
            abort(404);
        }

        return response()->json([
            'data' => [
                'id' => $webhookEndpoint->id,
                'url' => $webhookEndpoint->url,
                'events' => $webhookEndpoint->events,
                'active' => $webhookEndpoint->active,
                'failure_count' => $webhookEndpoint->failure_count,
                'last_triggered_at' => $webhookEndpoint->last_triggered_at?->toIso8601String(),
                'recent_deliveries' => $webhookEndpoint->deliveries()
                    ->orderByDesc('delivered_at')
                    ->limit(20)
                    ->get(['id', 'event', 'response_code', 'duration_ms', 'delivered_at']),
            ],
        ]);
    }

    public function update(Request $request, WebhookEndpoint $webhookEndpoint): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->hasManagerRole('principal')) {
            abort(403);
        }
        if ($webhookEndpoint->company_id !== $actor->company_id) {
            abort(404);
        }

        $validated = $request->validate([
            'url' => 'sometimes|url|max:500',
            'events' => 'sometimes|array|min:1',
            'events.*' => 'string|in:'.implode(',', self::AVAILABLE_EVENTS),
            'active' => 'boolean',
        ]);

        $webhookEndpoint->update($validated);
        if (isset($validated['active']) && $validated['active']) {
            $webhookEndpoint->update(['failure_count' => 0]);
        }

        return response()->json(['data' => $webhookEndpoint->fresh()]);
    }

    public function destroy(Request $request, WebhookEndpoint $webhookEndpoint): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->hasManagerRole('principal')) {
            abort(403);
        }
        if ($webhookEndpoint->company_id !== $actor->company_id) {
            abort(404);
        }

        $webhookEndpoint->delete();

        return response()->json(null, 204);
    }

    public function events(): JsonResponse
    {
        return response()->json(['data' => self::AVAILABLE_EVENTS]);
    }
}
