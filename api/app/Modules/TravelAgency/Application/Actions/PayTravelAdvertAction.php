<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\TravelAgency\Domain\Enums\PaymentProvider;
use App\Modules\TravelAgency\Domain\Enums\PaymentStatus;
use App\Modules\TravelAgency\Domain\Models\TravelAdvert;
use App\Modules\TravelAgency\Domain\Models\TravelPayment;
use Illuminate\Support\Facades\DB;

/**
 * TRAVEL-907 (#6110) — Paiement d'une annonce payante.
 *
 * Crée la ligne `travel_payments` (contrat #6023 réutilisé — booking_id
 * nullable, advert_id renseigné), confirme le paiement (cash ou provider)
 * et passe l'annonce `draft|pending_payment → paid`. Idempotent : une
 * annonce déjà payée est retournée telle quelle (rejeu sans doublon —
 * clé d'idempotence `advert-{id}-pay` par tenant).
 */
final class PayTravelAdvertAction
{
    public function execute(TravelAdvert $advert, Employee $actor, string $provider, ?string $providerReference = null): TravelAdvert
    {
        if ($advert->status === TravelAdvert::STATUS_PAID) {
            return $advert; // Idempotence.
        }

        if (! in_array($advert->status, [TravelAdvert::STATUS_DRAFT, TravelAdvert::STATUS_PENDING_PAYMENT], true)) {
            abort(422, 'Seule une annonce en brouillon peut etre payee.');
        }

        $providerEnum = PaymentProvider::tryFrom($provider);

        if ($providerEnum === null) {
            abort(422, 'Fournisseur de paiement inconnu.');
        }

        DB::transaction(function () use ($advert, $actor, $providerEnum, $providerReference): void {
            $payment = TravelPayment::query()->create([
                'company_id' => $advert->company_id,
                'booking_id' => null,
                'advert_id' => $advert->id,
                'provider_code' => $providerEnum,
                'amount_minor' => $advert->total_minor,
                'currency' => $advert->currency,
                'status' => PaymentStatus::CONFIRMED,
                'provider_reference' => $providerReference,
                'idempotency_key' => 'advert-'.$advert->id.'-pay',
            ]);

            $advert->forceFill([
                'status' => TravelAdvert::STATUS_PAID,
                'payment_id' => $payment->id,
                'paid_at' => now(),
            ])->save();
        });

        return $advert->refresh();
    }
}
