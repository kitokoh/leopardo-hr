<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\TravelAgency\Domain\Models\TravelAdvert;

/**
 * TRAVEL-907 (#6110) — Validation/modération d'une annonce payante
 * (permission `travel.manage`, principal/rh).
 *
 * Une annonce n'est visible (published) qu'une fois payée ET validée.
 * Approbation → `paid → published` (published_at + expires_at =
 * maintenant + VALIDITY_DAYS) ; refus → `paid → rejected` (motif tracé).
 * Idempotente : une annonce déjà publiée/rejetée est retournée telle quelle.
 */
final class ValidateTravelAdvertAction
{
    public function execute(TravelAdvert $advert, Employee $actor, bool $approved, ?string $note = null): TravelAdvert
    {
        if (in_array($advert->status, [TravelAdvert::STATUS_PUBLISHED, TravelAdvert::STATUS_REJECTED], true)) {
            return $advert; // Idempotence.
        }

        if ($advert->status !== TravelAdvert::STATUS_PAID) {
            abort(422, 'Une annonce doit etre payee avant validation.');
        }

        if ($approved) {
            $advert->forceFill([
                'status' => TravelAdvert::STATUS_PUBLISHED,
                'validated_by_user_id' => $actor->id,
                'validated_at' => now(),
                'published_at' => $advert->published_at ?? now(),
                'expires_at' => now()->addDays(TravelAdvert::VALIDITY_DAYS),
                'moderation_note' => $note,
            ])->save();
        } else {
            $advert->forceFill([
                'status' => TravelAdvert::STATUS_REJECTED,
                'validated_by_user_id' => $actor->id,
                'validated_at' => now(),
                'moderation_note' => $note,
            ])->save();
        }

        return $advert->refresh();
    }
}
