<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Application\Services;

use App\Modules\TravelAgency\Domain\Models\TravelAdvertPrice;

/**
 * TRAVEL-906/907 (#6109/#6110) — Tarification serveur des annonces.
 *
 * Le prix est TOUJOURS calculé côté serveur à partir de la grille du tenant
 * (type × position, devise du tenant, unités mineures) — jamais accepté du
 * client : total = prix image + (nombre de caractères × prix caractère).
 *
 * @return array{character_count: int, price_image_minor: int, price_character_minor: int, total_minor: int, currency: string}
 */
final class TravelAdvertPricingService
{
    /**
     * @return array{character_count: int, price_image_minor: int, price_character_minor: int, total_minor: int, currency: string}
     */
    public function quote(string $companyId, int $typeId, int $positionId, string $body, string $tenantCurrency): array
    {
        $price = TravelAdvertPrice::query()
            ->where('company_id', $companyId)
            ->where('advert_type_id', $typeId)
            ->where('advert_position_id', $positionId)
            ->where('currency', strtoupper($tenantCurrency))
            ->first();

        if (! $price instanceof TravelAdvertPrice) {
            abort(422, 'Aucun tarif configure pour ce type/position dans la devise du tenant.');
        }

        $characterCount = mb_strlen($body);
        $imageMinor = (int) $price->price_image_minor;
        $characterMinor = (int) $price->price_character_minor;
        $totalMinor = $imageMinor + ($characterCount * $characterMinor);

        return [
            'character_count' => $characterCount,
            'price_image_minor' => $imageMinor,
            'price_character_minor' => $characterMinor,
            'total_minor' => $totalMinor,
            'currency' => (string) $price->currency,
        ];
    }
}
