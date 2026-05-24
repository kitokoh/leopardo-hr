<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Webhook\StoreWebhookEndpointRequest;
use App\Http\Requests\Api\V1\Webhook\UpdateWebhookEndpointRequest;
use App\Http\Resources\Api\V1\WebhookEndpointResource;
use App\Models\Employee;
use App\Models\WebhookEndpoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
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

    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->hasManagerRole('principal')) {
            abort(403);
        }

        return WebhookEndpointResource::collection(
            WebhookEndpoint::query()->orderByDesc('created_at')->get()
        );
    }

    public function store(StoreWebhookEndpointRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $webhook = WebhookEndpoint::create([
            'company_id' => $actor->company_id,
            'url' => $request->validated('url'),
            'events' => $request->validated('events'),
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

    public function update(UpdateWebhookEndpointRequest $request, WebhookEndpoint $webhookEndpoint): WebhookEndpointResource
    {
        if ($webhookEndpoint->company_id !== $request->user()->company_id) {
            abort(404);
        }

        $webhookEndpoint->update($request->validated());
        if ($request->validated('active')) {
            $webhookEndpoint->update(['failure_count' => 0]);
        }

        return new WebhookEndpointResource($webhookEndpoint->fresh());
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
