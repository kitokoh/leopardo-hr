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
use App\Modules\Billing\Infrastructure\Services\WebhookEnvelopeBuilder;
use App\Modules\Billing\Interfaces\Api\V1\Requests\StoreWebhookEndpointRequest;
use App\Modules\Billing\Interfaces\Api\V1\Requests\UpdateWebhookEndpointRequest;
use App\Rules\NotPrivateUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class WebhookController extends Controller
{
    public const AVAILABLE_EVENTS = [
        'employee.created',
        'employee.updated',
        'employee.archived',
        'employee.departed',
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
        'webhook.test',
    ];

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', WebhookEndpoint::class);

        // #3948 : pagination alignée sur le contrat des autres listes.
        return WebhookEndpointResource::collection(
            WebhookEndpoint::query()
                ->orderByDesc('created_at')
                ->paginate((int) ($request->input('per_page', 20)))
        );
    }

    public function store(StoreWebhookEndpointRequest $request): JsonResponse
    {
        $this->authorize('create', WebhookEndpoint::class);

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
        $this->authorize('view', $webhookEndpoint);

        return (new WebhookEndpointResource(
            $webhookEndpoint->load(['deliveries' => fn ($q) => $q->orderByDesc('delivered_at')->limit(20)])
        ))->response();
    }

    public function update(UpdateWebhookEndpointRequest $request, WebhookEndpoint $webhookEndpoint): WebhookEndpointResource
    {
        $this->authorize('update', $webhookEndpoint);

        $webhookEndpoint->update($request->validated());
        if ($request->validated('active')) {
            $webhookEndpoint->update(['failure_count' => 0]);
        }

        return new WebhookEndpointResource($webhookEndpoint->fresh());
    }

    public function destroy(Request $request, WebhookEndpoint $webhookEndpoint): JsonResponse
    {
        $this->authorize('delete', $webhookEndpoint);

        $webhookEndpoint->delete();

        return response()->json(null, 204);
    }

    /**
     * Issue #2225 — envoie un événement de test au webhook (le client vérifie
     * que son endpoint reçoit bien les payloads Leopardo).
     */
    /**
     * POST /webhooks/{webhookEndpoint}/test
     *
     * Sends a synchronous test payload to the endpoint URL (same signature
     * headers and anti-SSRF guard as `DispatchWebhook`) and records a traced
     * `webhook_deliveries` row with event `test`. QA wave 2026-08-14 — T002
     * (#2227): the admin SPA "Tester" button used to fail with a 404.
     *
     * Issue #2548/#2572 : restaure l'implémentation synchrone d'origine
     * (#2353) — la version « dispatch async » ne remonte pas status/http_status/
     * duration_ms/delivery et WebhookTestEndpointTest échoue.
     *
     * @return JsonResponse 200 with {status, http_status, duration_ms, delivery}
     *                      or 422 when the URL is invalid/blocked.
     */
    public function test(Request $request, WebhookEndpoint $webhookEndpoint): JsonResponse
    {
        // #2654/#3949 : isolation tenant — un endpoint d'un autre tenant répond
        // 404 (pas 403) pour ne pas révéler l'existence de ressources tierces,
        // aligné sur deadLetters()/replayDeadLetter() du même contrôleur.
        /** @var Employee $actor */
        $actor = $request->user();
        if ($webhookEndpoint->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->authorize('test', $webhookEndpoint);

        // Anti-SSRF defence-in-depth: same guard as DispatchWebhook::handle().
        // `url` est nullable en base → on normalise avant tout usage (PHPStan strict).
        $url = $webhookEndpoint->url;
        $host = is_string($url) ? parse_url($url, PHP_URL_HOST) : null;
        if (! is_string($url) || ! str_starts_with($url, 'https://') || ! is_string($host) || ! NotPrivateUrl::isPublicHost($host)) {
            return response()->json([
                'message' => 'Webhook URL rejected: must be a public https URL.',
                'status' => 'blocked',
                'http_status' => 0,
                'duration_ms' => 0,
            ], 422);
        }

        // Issue #5744 : enveloppe canonique identique à DispatchWebhook
        // (event_version, company_id, correlation_id, occurred_at) pour que le
        // test endpoint reflète exactement la forme des livraisons réelles.
        $body = WebhookEnvelopeBuilder::build(
            'test',
            WebhookEnvelopeBuilder::CURRENT_VERSION,
            $webhookEndpoint->company_id,
            (string) Str::uuid(),
            now()->toIso8601String(),
            [
                'test' => true,
                'message' => 'Leopardo HR webhook test',
            ],
        );

        $timestamp = time();

        $start = microtime(true);

        try {
            $response = Http::timeout(10)
                ->withHeaders(WebhookEnvelopeBuilder::headers(
                    'test',
                    (string) WebhookEnvelopeBuilder::CURRENT_VERSION,
                    (string) $webhookEndpoint->secret,
                    $body,
                    $timestamp,
                ))
                ->post($url, $body);

            $durationMs = (int) ((microtime(true) - $start) * 1000);

            $delivery = WebhookDelivery::create([
                'webhook_endpoint_id' => $webhookEndpoint->id,
                'event' => 'test',
                'payload' => $body,
                'response_code' => $response->status(),
                'response_body' => mb_substr($response->body(), 0, 2000),
                'duration_ms' => $durationMs,
            ]);

            return response()->json([
                'message' => $response->successful()
                    ? 'Webhook delivered successfully.'
                    : 'Webhook delivered but the endpoint returned an error status.',
                'status' => $response->successful() ? 'success' : 'error',
                'http_status' => $response->status(),
                'duration_ms' => $durationMs,
                'delivery' => (new WebhookDeliveryResource($delivery))->resolve(),
            ]);
        } catch (Throwable $e) {
            $durationMs = (int) ((microtime(true) - $start) * 1000);

            WebhookDelivery::create([
                'webhook_endpoint_id' => $webhookEndpoint->id,
                'event' => 'test',
                'payload' => $body,
                'response_code' => 0,
                'response_body' => mb_substr($e->getMessage(), 0, 2000),
                'duration_ms' => $durationMs,
            ]);

            Log::error('billing.webhook.delivery_failed', [
                'webhook_endpoint_id' => $webhookEndpoint->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Webhook delivery failed.',
                'error' => 'WEBHOOK_DELIVERY_FAILED',
                'status' => 'error',
                'http_status' => 0,
                'duration_ms' => $durationMs,
            ], 422);
        }
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
                ->paginate(max(1, min(100, (int) $request->integer('per_page', 20))))
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
