<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\TravelAgency\Domain\Enums\AdvertStatus;
use App\Modules\TravelAgency\Domain\Models\TravelAdvert;
use App\Modules\TravelAgency\Domain\Models\TravelAdvertPrice;
use Illuminate\Support\Facades\DB;

/**
 * TRAVEL-907 (#6110) — Soumission d'une annonce.
 *
 * Le prix est calculé SERVEUR depuis la grille `travel_advert_prices`
 * (prix/image + prix/caractère × longueur du contenu) — jamais accepté du
 * client. L'annonce passe en `submitted` en attendant le paiement.
 */
final class SubmitTravelAdvertAction
{
    public function execute(
        Employee $actor,
        int $advertTypeId,
        int $advertPositionId,
        string $title,
        string $content,
        ?int $imageAssetId,
        int $validityDays,
    ): TravelAdvert {
        return DB::transaction(function () use ($actor, $advertTypeId, $advertPositionId, $title, $content, $imageAssetId, $validityDays): TravelAdvert {
            $price = TravelAdvertPrice::query()
                ->where('company_id', $actor->company_id)
                ->where('advert_type_id', $advertTypeId)
                ->where('advert_position_id', $advertPositionId)
                ->first();

            if (! $price instanceof TravelAdvertPrice) {
                abort(422, 'Aucune grille tarifaire pour ce type/position.');
            }

            $priceMinor = $price->price_per_image_minor * ($imageAssetId !== null ? 1 : 0)
                + $price->price_per_character_minor * mb_strlen($content);

            if ($priceMinor <= 0) {
                abort(422, 'Le contenu de l\'annonce est trop court pour un tarif valide.');
            }

            $validity = max(1, min(365, $validityDays));

            return TravelAdvert::query()->create([
                'company_id' => $actor->company_id,
                'advert_type_id' => $advertTypeId,
                'advert_position_id' => $advertPositionId,
                'title' => $title,
                'content_redacted' => $content,
                'image_asset_id' => $imageAssetId,
                'price_minor' => $priceMinor,
                'currency' => $price->currency,
                'status' => AdvertStatus::SUBMITTED,
                'validity_days' => $validity,
                'created_by_user_id' => $actor->id,
            ]);
        });
    }
}
