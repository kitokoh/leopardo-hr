<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\StripeService;
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

            // Return 200 to prevent Stripe from retrying on app errors
            return new JsonResponse(['received' => true, 'error' => 'processing_error'], 200);
        }

        return new JsonResponse(['received' => true]);
    }
}
