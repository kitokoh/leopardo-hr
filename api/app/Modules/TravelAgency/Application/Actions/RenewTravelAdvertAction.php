<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Application\Actions;

use App\Modules\TravelAgency\Domain\Enums\AdvertStatus;
use App\Modules\TravelAgency\Domain\Models\TravelAdvert;
use Illuminate\Support\Str;

/**
 * TRAVEL-908 (#6111) — Renouvellement d'une annonce.
 *
 * validated|expired → paid (nouveau paiement) puis l'annonce peut être
 * re-validée ; `expires_at` est prolongé de `validity_days`. Idempotent :
 * un renouvellement déjà en cours (paid sans validation) n'est pas
 * dupliqué.
 */
final class RenewTravelAdvertAction
{
    public function execute(TravelAdvert $advert): TravelAdvert
    {
        if ($advert->status === AdvertStatus::PAID) {
            return $advert;
        }

        if (! in_array($advert->status, [AdvertStatus::VALIDATED, AdvertStatus::EXPIRED], true)) {
            abort(422, 'Seule une annonce validée ou expirée peut être renouvelée.');
        }

        $advert->forceFill([
            'status' => AdvertStatus::PAID,
            'payment_reference' => 'ADV-'.strtoupper(Str::random(10)),
            'paid_at' => now(),
            'expires_at' => now()->addDays((int) $advert->validity_days),
        ])->save();

        return $advert->refresh();
    }
}
