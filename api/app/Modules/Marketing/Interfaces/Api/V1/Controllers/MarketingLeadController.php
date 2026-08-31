<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Marketing\Application\Actions\CaptureMarketingLead;
use App\Modules\Marketing\Application\DTOs\CreateMarketingLeadDTO;
use App\Modules\Marketing\Interfaces\Api\V1\Requests\StoreMarketingLeadRequest;
use App\Modules\Platform\Infrastructure\Services\WebhookEventRegistry;
use App\Shared\Services\InboundWebhookVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * PA2-MKT-007 — Funnel CRM marketing.
 *
 * `store()` is called server-to-server by the public vitrine's Next.js API
 * routes (`front/web/src/app/api/forms/{signup,demo,contact,newsletter}`)
 * right after `captureMarketingLead()` logs the event and best-effort
 * forwards it to the external CRM/email webhooks — it persists the same
 * lead durably so nothing is lost if the external forwarders are down, and
 * so the platform CRM pipeline (PA2-ADM-004) has a real data source
 * instead of only `company_requests` (which only covers `signup`, not
 * demo/contact/newsletter).
 *
 * Public and unauthenticated by nature (called before any tenant exists),
 * protected by the same shared secret already documented for
 * `MARKETING_LEAD_WEBHOOK_TOKEN` in
 * `docs/validation/LAUNCH_OBSERVABILITY_DASHBOARD.md` — mirrors
 * `EmailBounceWebhookController`. Fail-closed (#3888) : secret absent → 503,
 * la vitrine envoie déjà `Authorization: Bearer $MARKETING_LEAD_WEBHOOK_TOKEN`
 * (`front/web/src/app/api/forms/_lib/lead-capture.ts:buildForwardHeaders`).
 *
 * The admin-facing listing/status endpoints for the platform CRM pipeline
 * live in PA2-ADM-004 (`PlatformCrmPipelineController` /
 * `PlatformMarketingLeadController`), which depends on this ticket.
 */
class MarketingLeadController extends Controller
{
    public function __construct(
        private readonly CaptureMarketingLead $captureMarketingLead,
        private readonly WebhookEventRegistry $registry,
    ) {}

    public function store(StoreMarketingLeadRequest $request): JsonResponse
    {
        $configuredSecret = (string) config('services.marketing_lead_webhook.secret', '');

        if ($configuredSecret === '') {
            // #3888 fail-closed : secret non configuré = endpoint non
            // authentifiable = on REFUSE (503). Un endpoint public sans
            // secret ne doit jamais ingérer un payload (fail-open =
            // poisonning de la base leads CRM). Miroir d'EmailBounceWebhookController (#2616).
            Log::error('Marketing lead ingest: secret not configured — endpoint REJECTED (fail-closed). Set services.marketing_lead_webhook.secret.');

            abort(503, 'MARKETING_WEBHOOK_NOT_CONFIGURED');
        }

        $providedSecret = (string) $request->header('X-Marketing-Lead-Token', '');

        if (! hash_equals($configuredSecret, $this->extractBearerOrHeader($request, $providedSecret))) {
            Log::warning('Marketing lead ingest: invalid or missing shared secret');

            return new JsonResponse(['error' => 'Invalid signature'], 400);
        }

        // #5444 : idempotence persistée — pas d'identifiant d'événement dans
        // #5740 — frontière hostile : bornes d'entrée AVANT le registre
        // d'idempotence (taille max, JSON valide, fenêtre de rejeu
        // optionnelle si l'en-tête est présent). La clé reste le hash du
        // payload brut : une redelivrance identique (retry vitrine/réseau)
        // ne crée pas de lead en double.
        $payload = $request->getContent();

        if (! InboundWebhookVerifier::payloadWithinLimit($payload)) {
            Log::warning('Marketing lead ingest: payload too large', ['bytes' => strlen($payload)]);

            return new JsonResponse(['error' => 'Payload too large'], 413);
        }

        if ($request->isJson() && ! InboundWebhookVerifier::isJsonPayload($payload)) {
            Log::warning('Marketing lead ingest: invalid JSON payload');

            return new JsonResponse(['error' => 'Invalid JSON'], 400);
        }

        $timestamp = InboundWebhookVerifier::timestampFromHeader($request->header('X-Webhook-Timestamp'));

        if ($timestamp !== null && ! InboundWebhookVerifier::timestampIsFresh($timestamp)) {
            Log::warning('Marketing lead ingest: expired or skewed timestamp', ['timestamp' => $timestamp]);

            return new JsonResponse(['error' => 'Expired timestamp'], 400);
        }

        $eventId = $this->registry->eventId($payload);
        $replay = $this->registry->begin('marketing-lead', $eventId, hash('sha256', $payload));

        if ($replay !== null) {
            $this->registry->logReplay('marketing-lead', $eventId, $replay['code']);

            return new JsonResponse(
                $this->registry->replayBody($replay['body'], ['received' => true, 'replayed' => true]),
                $replay['code'],
            );
        }

        try {
            $dto = CreateMarketingLeadDTO::fromArray($request->validated());
            $lead = $this->captureMarketingLead->execute($dto);

            $body = json_encode([
                'data' => [
                    'id' => $lead->id,
                    'external_id' => $lead->external_id,
                    'status' => $lead->status,
                ],
            ]) ?: '';

            $this->registry->complete('marketing-lead', $eventId, 201, $body);

            return new JsonResponse([
                'data' => [
                    'id' => $lead->id,
                    'external_id' => $lead->external_id,
                    'status' => $lead->status,
                ],
            ], 201);
        } catch (\Throwable $e) {
            $this->registry->release('marketing-lead', $eventId);
            Log::error('Marketing lead ingest: error handling webhook', [
                'error' => $e->getMessage(),
            ]);

            return new JsonResponse(['received' => false, 'error' => 'processing_error'], 500);
        }
    }

    private function extractBearerOrHeader(Request $request, string $headerValue): string
    {
        if ($headerValue !== '') {
            return $headerValue;
        }

        $authorizationHeader = $request->header('Authorization');
        $authorization = is_string($authorizationHeader) ? $authorizationHeader : '';

        return str_starts_with($authorization, 'Bearer ') ? substr($authorization, 7) : '';
    }
}
