<?php

declare(strict_types=1);

namespace App\Modules\Billing\Interfaces\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Infrastructure\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Stripe Webhook Controller.
 *
 * Receives and processes Stripe webhook events.
 * This endpoint is public but protected by Stripe signature verification.
 */
class StripeWebhookController extends Controller
{
    public function __construct(
        private readonly StripeService $stripeService,
    ) {}

    /**
     * POST /api/v1/webhooks/stripe
     */
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature', '');

        $event = $this->stripeService->verifyWebhookSignature($payload, $sigHeader);

        if (! $event) {
            Log::warning('Stripe Webhook: Invalid signature or expired event');

            return new JsonResponse(['error' => 'Invalid signature'], 400);
        }

        try {
            $this->stripeService->handleEvent($event);
        } catch (\Throwable $e) {
            Log::error('Stripe Webhook: Error handling event', [
                'type' => $event['type'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            // Issue #2668 (QA 2026-08-15) — une erreur de TRAITEMENT doit être
            // signalée par un 500 pour que Stripe retente l'événement : un 200
            // (« prevent Stripe from retrying ») faisait disparaître les
            // événements de paiement en silence (perte de données). Seule une
            // signature invalide (400 ci-dessus) ne doit pas être retentée.
            return new JsonResponse(['received' => false, 'error' => 'processing_error'], 500);
        }

        return new JsonResponse(['received' => true]);
    }
}
