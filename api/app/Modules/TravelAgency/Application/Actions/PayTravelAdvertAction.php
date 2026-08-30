<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Application\Actions;

use App\Modules\TravelAgency\Domain\Enums\AdvertStatus;
use App\Modules\TravelAgency\Domain\Models\TravelAdvert;
use Illuminate\Support\Str;

/**
 * TRAVEL-907 (#6110) — Paiement d'une annonce.
 *
 * submitted → paid (référence de paiement générée, horodatage). Idempotent :
 * une annonce déjà payée est retournée sans double effet. Le paiement réel
 * (contrat travel_payments) est branché par le lot paiements ; ici le
 * passage à `paid` matérialise la confirmation d'encaissement.
 */
final class PayTravelAdvertAction
{
    public function execute(TravelAdvert $advert): TravelAdvert
    {
        if ($advert->status === AdvertStatus::PAID || $advert->status === AdvertStatus::VALIDATED) {
            return $advert;
        }

        if (! in_array($advert->status, [AdvertStatus::SUBMITTED, AdvertStatus::DRAFT], true)) {
            abort(422, 'Cette annonce ne peut pas être payée dans son état actuel.');
        }

        $advert->forceFill([
            'status' => AdvertStatus::PAID,
            'payment_reference' => 'ADV-'.strtoupper(Str::random(10)),
            'paid_at' => now(),
        ])->save();

        return $advert->refresh();
    }
}
