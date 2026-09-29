<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\TravelAgency\Domain\Enums\AdvertStatus;
use App\Modules\TravelAgency\Domain\Models\TravelAdvert;

/**
 * TRAVEL-907 (#6110) — Rejet d'une annonce (motif conservé).
 *
 * paid/submitted/validated → rejected. Réservé à l'ability `travel.manage`
 * (tranché par la Policy du controller).
 */
final class RejectTravelAdvertAction
{
    public function execute(TravelAdvert $advert, Employee $actor, string $reason): TravelAdvert
    {
        if (! in_array($advert->status, [AdvertStatus::PAID, AdvertStatus::SUBMITTED, AdvertStatus::VALIDATED], true)) {
            abort(422, 'Cette annonce ne peut pas être rejetée dans son état actuel.');
        }

        $advert->forceFill([
            'status' => AdvertStatus::REJECTED,
            'validated_by_user_id' => $actor->id,
            'validated_at' => now(),
            'rejected_reason' => mb_substr($reason, 0, 500),
        ])->save();

        return $advert->refresh();
    }
}
