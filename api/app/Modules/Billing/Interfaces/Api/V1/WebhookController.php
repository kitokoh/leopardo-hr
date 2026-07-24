<?php

declare(strict_types=1);

namespace App\Modules\Billing\Interfaces\Api\V1;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\WebhookDeliveryResource;
use App\Http\Resources\Api\V1\WebhookEndpointResource;
use App\Jobs\DispatchWebhook;
use App\Modules\Billing\Domain\Models\WebhookDelivery;
use App\Modules\Billing\Domain\Models\WebhookEndpoint;
use App\Modules\Billing\Interfaces\Api\V1\Requests\StoreWebhookEndpointRequest;
use App\Modules\Billing\Interfaces\Api\V1\Requests\UpdateWebhookEndpointRequest;
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

        return (new WebhookEndpointResource($webhook))
            ->response()
            ->setStatusCode(201);
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

        return (new WebhookEndpointResource(
            $webhookEndpoint->load(['deliveries' => fn ($q) => $q->orderByDesc('delivered_at')->limit(20)])
        ))->response();
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

    /**
     * GET /webhooks/{webhookEndpoint}/dead-letters
     *
     * Lists delivery attempts that permanently exhausted all retries (see
     * `DispatchWebhook::failed()`), so a partner admin can inspect and
     * replay them without shell/DB access. PA2-API-006.
     */
    public function deadLetters(Request $request, WebhookEndpoint $webhookEndpoint): AnonymousResourceCollection
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->hasManagerRole('principal')) {
            abort(403);
        }
        if ($webhookEndpoint->company_id !== $actor->company_id) {
            abort(404);
        }

        return WebhookDeliveryResource::collection(
            $webhookEndpoint->deliveries()
                ->deadLettered()
                ->orderByDesc('delivered_at')
                ->paginate((int) $request->integer('per_page', 20))
        );
    }

    /**
     * POST /webhooks/{webhookEndpoint}/dead-letters/{delivery}/replay
     *
     * Re-dispatches the exact same event/payload behind a dead-lettered
     * delivery. This does not re-run the business action that originally
     * emitted the event, so the partner receives the same `event`/`data`
     * again — idempotent from a functional standpoint, matching the
     * "webhook rejoue sans doublon fonctionnel" requirement in
     * docs/GUIDES/GUIDE_INTEGRATION_PARTENAIRES.md. The endpoint's
     * `failure_count` is reset so a manual replay gets a fresh run of
     * retries instead of immediately tripping the auto-disable threshold.
     */
    public function replayDeadLetter(Request $request, WebhookEndpoint $webhookEndpoint, WebhookDelivery $delivery): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->hasManagerRole('principal')) {
            abort(403);
        }
        if ($webhookEndpoint->company_id !== $actor->company_id) {
            abort(404);
        }
        if ($delivery->webhook_endpoint_id !== $webhookEndpoint->id || $delivery->dead_lettered_at === null) {
            abort(404);
        }

        $webhookEndpoint->update(['failure_count' => 0, 'active' => true]);

        /** @var array<string, mixed> $payload */
        $payload = $delivery->payload['data'] ?? [];
        $event = (string) $delivery->event;

        DispatchWebhook::dispatch($webhookEndpoint, $event, $payload);

        return response()->json(['message' => 'Webhook delivery re-queued.'], 202);
    }
}
