<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Interfaces\Api\V1\Controllers;

use App\Modules\Delivery\Infrastructure\Services\DeliveryTrackingShareService;
use Illuminate\Http\JsonResponse;

/**
 * Suivi public d'une livraison par lien borné (DELIVERY-204, issue #6288).
 *
 * Endpoint PUBLIC : le token (64 chars, expirant) EST la credential — pas
 * d'auth Sanctum (pattern AccountingDocumentShare #5225 / CabinetShare
 * #1817). Résolution O(1) sans scope tenant. Champs strictement limités au
 * suivi (RGPD) : statut, événements, adresse de destination, fenêtre.
 */
final class PublicDeliveryTrackingController
{
    public function __construct(private readonly DeliveryTrackingShareService $shares) {}

    public function show(string $token): JsonResponse
    {
        $share = $this->shares->resolve($token);

        if ($share === null) {
            abort(404, 'TRACKING_LINK_NOT_FOUND');
        }

        $delivery = $share->delivery()->withoutGlobalScope('company')
            ->where('company_id', $share->company_id)
            ->with('events')
            ->first();

        if ($delivery === null) {
            abort(404, 'TRACKING_LINK_NOT_FOUND');
        }

        return response()->json([
            'data' => [
                'reference' => $delivery->reference,
                'status' => $delivery->status,
                'dropoff_address' => $delivery->dropoff_address,
                'dropoff_contact' => $delivery->dropoff_contact,
                'window_from' => $delivery->window_from?->toIso8601String(),
                'window_to' => $delivery->window_to?->toIso8601String(),
                'delivered_at' => $delivery->delivered_at?->toIso8601String(),
                'expires_at' => $share->expires_at?->toIso8601String(),
                'events' => $delivery->events()
                    ->orderByDesc('event_at')
                    // BC-26-D10 (#6296) : timeline publique bornée (50 max)
                    // — pas de get()/all() non borné (garde MAT-014).
                    ->limit(50)
                    ->get()
                    ->map(fn ($event): array => [
                        'type' => $event->type,
                        'event_at' => $event->event_at->toIso8601String(),
                    ])
                    ->values()
                    ->all(),
            ],
        ])->header('Referrer-Policy', 'no-referrer');
    }
}
