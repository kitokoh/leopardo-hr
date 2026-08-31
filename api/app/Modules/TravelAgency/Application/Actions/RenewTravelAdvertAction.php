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
 * TRAVEL-908 (#6111) — Renouvellement d'une annonce expirée (nouveau
 * paiement). `expired|published → published` avec `expires_at` prolongé de
 * VALIDITY_DAYS. Idempotent : rejeu = même effet, une seule ligne de
 * paiement de renouvellement (clé `advert-{id}-renew` par tenant).
 */
final class RenewTravelAdvertAction
{
    public function execute(TravelAdvert $advert, Employee $actor, string $provider, ?string $providerReference = null): TravelAdvert
    {
        if (! in_array($advert->status, [TravelAdvert::STATUS_EXPIRED, TravelAdvert::STATUS_PUBLISHED], true)) {
            abort(422, 'Seule une annonce expiree ou publiee peut etre renouvelee.');
        }

        $providerEnum = PaymentProvider::tryFrom($provider);

        if ($providerEnum === null) {
            abort(422, 'Fournisseur de paiement inconnu.');
        }

        DB::transaction(function () use ($advert, $actor, $providerEnum, $providerReference): void {
            TravelPayment::query()->create([
                'company_id' => $advert->company_id,
                'booking_id' => null,
                'advert_id' => $advert->id,
                'provider_code' => $providerEnum,
                'amount_minor' => $advert->total_minor,
                'currency' => $advert->currency,
                'status' => PaymentStatus::CONFIRMED,
                'provider_reference' => $providerReference,
                'idempotency_key' => 'advert-'.$advert->id.'-renew',
            ]);

            // Requalification payée : repasse publiée avec une nouvelle
            // fenêtre de validité (l'annonce redevient visible).
            $advert->forceFill([
                'status' => TravelAdvert::STATUS_PUBLISHED,
                'paid_at' => now(),
                'validated_by_user_id' => $actor->id,
                'validated_at' => now(),
                'published_at' => now(),
                'expires_at' => now()->addDays(TravelAdvert::VALIDITY_DAYS),
            ])->save();
        });
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
