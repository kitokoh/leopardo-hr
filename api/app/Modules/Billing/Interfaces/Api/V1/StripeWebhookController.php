<?php

declare(strict_types=1);

namespace App\Modules\Billing\Interfaces\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Infrastructure\Services\StripeService;
use App\Modules\Platform\Infrastructure\Services\WebhookEventRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Stripe Webhook Controller.
 *
 * Receives and processes Stripe webhook events.
 * This endpoint is public but protected by Stripe signature verification.
 *
 * #5444 : idempotence persistée — le premier traitement réserve l'événement
 * (`webhook_events`, unique source+event_id) ; une redelivrance est rejouée
 * avec la réponse mémorisée (zéro effet double). Un échec de traitement
 * libère la réservation → Stripe re-tente (sémantique 500, #2668).
 */
class StripeWebhookController extends Controller
{
    public function __construct(
        private readonly StripeService $stripeService,
        private readonly WebhookEventRegistry $registry,
    ) {}

    /**
     * POST /api/v1/webhooks/stripe
     */
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature', '');

        /** @var array<string, mixed> $event */
        $event = $this->stripeService->verifyWebhookSignature($payload, $sigHeader);

        if (! $event) {
            Log::warning('Stripe Webhook: Invalid signature or expired event');

            return new JsonResponse(['error' => 'Invalid signature'], 400);
        }

        $eventId = $this->registry->eventId($payload, is_string($event['id'] ?? null) ? $event['id'] : null);
        $replay = $this->registry->begin('stripe', $eventId, hash('sha256', $payload));

        if ($replay !== null) {
            $this->registry->logReplay('stripe', $eventId, $replay['code']);

            return new JsonResponse(
                $this->registry->replayBody($replay['body'], ['received' => true, 'replayed' => true]),
                $replay['code'],
            );
        }

        try {
            $this->stripeService->handleEvent($event);

            $this->registry->complete('stripe', $eventId, 200, json_encode(['received' => true]) ?: null);

            return new JsonResponse(['received' => true]);
        } catch (\Throwable $e) {
            $this->registry->release('stripe', $eventId);
            Log::error('Stripe Webhook: Error handling event', [
                'type' => is_string($event['type'] ?? null) ? $event['type'] : null,
                'error' => $e->getMessage(),
            ]);

            // Issue #2668 (QA 2026-08-15) — une erreur de TRAITEMENT doit être
            // signalée par un 500 pour que Stripe retente l'événement : un 200
            // (« prevent Stripe from retrying ») faisait disparaître les
            // événements de paiement en silence (perte de données). Seule une
            // signature invalide (400 ci-dessus) ne doit pas être retentée.
            return new JsonResponse(['received' => false, 'error' => 'processing_error'], 500);
        }
    }
}
