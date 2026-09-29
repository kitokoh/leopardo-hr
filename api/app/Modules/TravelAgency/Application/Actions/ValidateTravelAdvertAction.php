<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\TravelAgency\Domain\Enums\AdvertStatus;
use App\Modules\TravelAgency\Domain\Models\TravelAdvert;

/**
 * TRAVEL-907 (#6110) — Validation d'une annonce payée.
 *
 * paid → validated (devient visible, `expires_at` = now + validity_days).
 * Réservé à l'ability `travel.manage` (tranché par la Policy du controller).
 */
final class ValidateTravelAdvertAction
{
    public function execute(TravelAdvert $advert, Employee $actor): TravelAdvert
    {
        if ($advert->status !== AdvertStatus::PAID) {
            abort(422, 'Seule une annonce payée peut être validée.');
        }

        $advert->forceFill([
            'status' => AdvertStatus::VALIDATED,
            'validated_by_user_id' => $actor->id,
            'validated_at' => now(),
            'starts_at' => $advert->starts_at ?? now(),
            'expires_at' => now()->addDays((int) $advert->validity_days),
        ])->save();

        return $advert->refresh();
    }
}
