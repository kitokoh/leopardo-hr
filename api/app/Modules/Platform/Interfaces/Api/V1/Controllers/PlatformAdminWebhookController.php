<?php

declare(strict_types=1);

namespace App\Modules\Platform\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\WebhookDeliveryResource;
use App\Http\Resources\Api\V1\WebhookEndpointResource;
use App\Jobs\DispatchWebhook;
use App\Modules\Billing\Domain\Models\WebhookDelivery;
use App\Modules\Billing\Domain\Models\WebhookEndpoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

/**
 * Webhooks — vue cross-tenant super-admin (contrat SPA front/admin-dashboard,
 * issue #2634 : WebhooksView appelait /v1/webhooks* scopées tenant → 401).
 *
 * Lecture/actions cross-tenant (console plateforme) : la mutation reste
 * cantonnée aux endpoints webhook eux-mêmes (url/events/active/test/replay).
 */
class PlatformAdminWebhookController extends Controller
{
    /**
     * GET /admin/webhooks — tous les endpoints webhook, tous tenants.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $webhooks = WebhookEndpoint::query()
            ->leftJoin('companies', 'companies.id', '=', 'webhook_endpoints.company_id')
            ->select('webhook_endpoints.*', 'companies.name as company_name')
            ->orderByDesc('webhook_endpoints.created_at')
            ->paginate(min(100, max(1, $request->integer('per_page', 20))));

        return WebhookEndpointResource::collection($webhooks);
    }

    /**
     * GET /admin/webhooks/events — liste des événements disponibles.
     */
    public function events(): JsonResponse
    {
        return response()->json(['data' => $this->availableEvents()]);
    }

    /**
     * GET /admin/webhooks/{webhookEndpoint} — détail + dernières livraisons.
     */
    public function show(WebhookEndpoint $webhookEndpoint): JsonResponse
    {
        return (new WebhookEndpointResource(
            $webhookEndpoint->load(['deliveries' => fn ($q) => $q->orderByDesc('delivered_at')->limit(20)])
        ))->response();
    }

    /**
     * POST /admin/webhooks — création cross-tenant (console plateforme).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company_id' => ['required', 'uuid', 'exists:companies,id'],
            'url' => ['required', 'url', 'max:500'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['string', 'in:'.implode(',', $this->availableEvents())],
            'active' => ['sometimes', 'boolean'],
        ]);

        $webhook = WebhookEndpoint::create([
            'company_id' => $validated['company_id'],
            'url' => $validated['url'],
            'events' => $validated['events'],
            'secret' => Str::random(40),
            'active' => $validated['active'] ?? true,
        ]);

        return (new WebhookEndpointResource($webhook))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * PUT/PATCH /admin/webhooks/{webhookEndpoint} — mise à jour.
     */
    public function update(Request $request, WebhookEndpoint $webhookEndpoint): WebhookEndpointResource
    {
        $validated = $request->validate([
            'url' => ['sometimes', 'url', 'max:500'],
            'events' => ['sometimes', 'array', 'min:1'],
            'events.*' => ['string', 'in:'.implode(',', $this->availableEvents())],
            'active' => ['sometimes', 'boolean'],
        ]);

        $webhookEndpoint->update($validated);
        if (! empty($validated['active'])) {
            $webhookEndpoint->update(['failure_count' => 0]);
        }

        return new WebhookEndpointResource($webhookEndpoint->fresh());
    }

    /**
     * DELETE /admin/webhooks/{webhookEndpoint} — suppression.
     */
    public function destroy(WebhookEndpoint $webhookEndpoint): JsonResponse
    {
        $webhookEndpoint->delete();

        return response()->json(null, 204);
    }

    /**
     * POST /admin/webhooks/{webhookEndpoint}/test — événement de test.
     */
    public function test(WebhookEndpoint $webhookEndpoint): JsonResponse
    {
        DispatchWebhook::dispatch($webhookEndpoint, 'test', [
            'message' => 'Webhook de test Leopardo RH (console plateforme)',
            'timestamp' => now()->toIso8601String(),
        ]);

        return response()->json(['message' => 'Webhook test event dispatched.']);
    }

    /**
     * GET /admin/webhooks/{webhookEndpoint}/dead-letters — livraisons échouées.
     */
    public function deadLetters(Request $request, WebhookEndpoint $webhookEndpoint): AnonymousResourceCollection
    {
        return WebhookDeliveryResource::collection(
            $webhookEndpoint->deliveries()
                ->deadLettered()
                ->orderByDesc('delivered_at')
                ->paginate((int) $request->integer('per_page', 20))
        );
    }

    /**
     * POST /admin/webhooks/{webhookEndpoint}/dead-letters/{delivery}/replay.
     */
    public function replayDeadLetter(WebhookEndpoint $webhookEndpoint, WebhookDelivery $delivery): JsonResponse
    {
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

    /**
     * @return array<int, string>
     */
    private function availableEvents(): array
    {
        return [
            'employee.created',
            'employee.updated',
            'leave.approved',
            'leave.rejected',
            'attendance.synced',
            'payroll.processed',
            'payroll.validated',
            'loan.disbursed',
            'expense.submitted',
            'expense.approved',
            'webhook.test',
        ];
    }
}
