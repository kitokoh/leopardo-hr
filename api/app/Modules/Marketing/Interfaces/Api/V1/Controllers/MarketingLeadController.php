<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Marketing\Application\Actions\CaptureMarketingLead;
use App\Modules\Marketing\Domain\DTOs\CreateMarketingLeadDTO;
use App\Modules\Marketing\Interfaces\Api\V1\Requests\StoreMarketingLeadRequest;
use App\Modules\Platform\Infrastructure\Services\WebhookEventRegistry;
use App\Shared\Services\InboundWebhookVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
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
 * `EmailBounceWebhookController`. La vitrine envoie
 * `Authorization: Bearer $MARKETING_LEAD_WEBHOOK_TOKEN`
 * (`front/web/src/app/api/forms/_lib/lead-capture.ts:buildForwardHeaders`).
 *
 * #7301 — secret ABSENT ≠ lead refusé : quand le secret partagé n'est pas
 * configuré, l'endpoint ne peut pas authentifier l'appelant (#3888 prévoyait
 * alors un 503) — mais refuser TOUT payload faisait perdre **tous** les leads
 * d'acquisition (#7301, BC-11 CRM), sans trace durable. Le compromis retenu :
 * on **persiste quand même** le lead (la perte de donnée est le pire des deux
 * maux) et on émet une **alerte** (log `critical` + relais optionnel vers
 * `services.marketing_lead_webhook.alert_url`, alimenté par
 * `MARKETING_ALERT_WEBHOOK_URL`) pour que la configuration manquante soit
 * corrigée. Dès que le secret EST configuré, la vérification reste
 * fail-closed (#3888) : secret invalide ou absent → 400, aucune écriture.
 * L'ingestion reste bornée par `throttle:webhooks-inbound`.
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
            // #7301 — secret non configuré : l'appelant n'est pas authentifiable
            // (#3888). Refuser le payload faisait perdre le lead (data-loss BC-11
            // CRM, constat production) : on l'accepte donc en le PERSISTANT, mais
            // l'ingestion non authentifiée est ALERTÉE (jamais silencieuse) pour
            // que la variable MARKETING_LEAD_WEBHOOK_TOKEN soit renseignée.
            $this->alertUnauthenticatedIngest($request);
        } else {
            $providedSecret = (string) $request->header('X-Marketing-Lead-Token', '');

            if (! hash_equals($configuredSecret, $this->extractBearerOrHeader($request, $providedSecret))) {
                Log::warning('Marketing lead ingest: invalid or missing shared secret');

                return new JsonResponse(['error' => 'Invalid signature'], 400);
            }
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

            // #7301 — un échec ici = un lead potentiellement PERDU : alerte
            // (et non une simple ligne de log) pour que la perte soit visible.
            $this->emitAlert('marketing.lead.persist_failed', [
                'endpoint' => 'POST /api/v1/marketing/leads',
                'event_id' => $eventId,
                'error' => $e->getMessage(),
            ]);

            return new JsonResponse(['received' => false, 'error' => 'processing_error'], 500);
        }
    }

    /**
     * #7301 — alerte l'exploitation quand l'ingestion tourne SANS secret
     * partagé : les leads sont persistés (aucune perte), mais l'endpoint est
     * ouvert — la configuration doit être corrigée (MARKETING_LEAD_WEBHOOK_TOKEN).
     */
    private function alertUnauthenticatedIngest(Request $request): void
    {
        $this->emitAlert('marketing.lead.ingest_unauthenticated', [
            'endpoint' => 'POST /api/v1/marketing/leads',
            'cause' => 'services.marketing_lead_webhook.secret (MARKETING_LEAD_WEBHOOK_TOKEN) is not configured',
            'effect' => 'unauthenticated ingest accepted (fail-open) — leads are persisted so none is lost',
            'client_ip' => $request->ip(),
        ]);
    }

    /**
     * Émet une alerte d'exploitation : niveau `critical` dans les logs (les
     * règles d'alerting s'appuient dessus) + relais best-effort vers
     * `MARKETING_ALERT_WEBHOOK_URL` (Slack/CRM/mail) quand il est configuré —
     * même contrat que le volet vitrine de #7301. Le relais ne doit jamais
     * casser la requête appelante.
     *
     * @param  array<string, mixed>  $context
     */
    private function emitAlert(string $event, array $context): void
    {
        $payload = [
            'event' => $event,
            'service' => 'leopardo-api',
            'severity' => 'critical',
            ...$context,
        ];

        Log::critical('Marketing lead ingest alert: '.$event, $payload);

        $url = (string) config('services.marketing_lead_webhook.alert_url', '');

        if ($url === '') {
            return;
        }

        try {
            Http::timeout(2)->acceptJson()->post($url, $payload);
        } catch (\Throwable $e) {
            Log::warning('Marketing lead ingest: alert webhook could not be delivered', [
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
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
