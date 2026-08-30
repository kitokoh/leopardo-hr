<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Infrastructure\Services;

use App\Modules\Delivery\Domain\Models\Delivery;
use App\Modules\Delivery\Domain\Models\DeliveryTrackingShare;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Liens de suivi publics bornés (DELIVERY-204, issue #6288).
 *
 * Génère un token aléatoire unique + expiration pour une livraison.
 * L'accès est strictement limité au suivi de la livraison (RGPD) : la
 * résolution ne retourne JAMAIS la livraison sans token valide et non expiré
 * (pattern AccountingDocumentShare #5225 / CabinetShare #1817).
 */
final class DeliveryTrackingShareService
{
    public const DEFAULT_TTL_HOURS = 168; // 7 jours

    public function createShare(
        Delivery $delivery,
        ?Carbon $expiresAt = null,
    ): DeliveryTrackingShare {
        $expiresAt ??= now()->addHours(self::DEFAULT_TTL_HOURS);

        /** @var DeliveryTrackingShare $share */
        $share = DeliveryTrackingShare::query()->create([
            'company_id' => $delivery->company_id,
            'delivery_id' => $delivery->id,
            'share_token' => $this->uniqueToken(),
            'expires_at' => $expiresAt,
        ]);

        return $share;
    }

    /**
     * Résolution O(1) du token — sans scope tenant : le search_path par
     * défaut (`shared_tenants,public`) couvre tous les tenants à schéma
     * partagé ; une seule requête, aucun oracle de timing.
     */
    public function resolve(string $token): ?DeliveryTrackingShare
    {
        /** @var DeliveryTrackingShare|null $share */
        $share = DeliveryTrackingShare::query()
            ->withoutGlobalScope('company')
            ->where('share_token', $token)
            ->first();

        if ($share === null || $share->isExpired()) {
            return null;
        }

        return $share;
    }

    public function trackingUrl(DeliveryTrackingShare $share): string
    {
        return url('/api/v1/deliveries/tracking/'.$share->share_token);
    }

    private function uniqueToken(): string
    {
        do {
            $token = Str::random(64);
        } while (DeliveryTrackingShare::query()->where('share_token', $token)->exists());

        return $token;
    }
}
