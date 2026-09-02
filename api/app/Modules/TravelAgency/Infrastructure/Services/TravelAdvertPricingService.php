<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Infrastructure\Services;

use App\Modules\TravelAgency\Domain\Models\TravelAdvertPrice;
use App\Modules\TravelAgency\Domain\Models\TravelAdvertType;

/**
 * TRAVEL-906/907 (#6109/#6110) — Calcul du prix d'une annonce.
 *
 * Prix TOUJOURS calculé serveur (jamais accepté du client) : image ×
 * `price_per_image_minor` + caractères × `price_per_character_minor`,
 * en unités mineures, devise du tenant (cohérence grille).
 */
final class TravelAdvertPricingService
{
    /**
     * @return array{price_minor: int, currency: string, character_count: int}
     */
    public function computePrice(
        string $companyId,
        TravelAdvertType $type,
        int $positionId,
        int $characterCount,
        bool $hasImage,
    ): array {
        $characterCount = max(0, min(5000, $characterCount));

        /** @var TravelAdvertPrice|null $price */
        $price = TravelAdvertPrice::query()
            ->where('company_id', $companyId)
            ->where('type_id', $type->id)
            ->where('position_id', $positionId)
            ->first();

        if (! $price instanceof TravelAdvertPrice) {
            abort(422, 'Aucun tarif défini pour ce type/emplacement d\'annonce.');
        }

        $amount = 0;

        if ($hasImage) {
            $amount += (int) $price->price_per_image_minor;
        }

        $amount += (int) $price->price_per_character_minor * $characterCount;

        return [
            'price_minor' => $amount,
            'currency' => (string) $price->currency,
            'character_count' => $characterCount,
        ];
    }
}
