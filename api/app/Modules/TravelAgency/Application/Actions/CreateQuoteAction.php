<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\TravelAgency\Domain\Enums\AgeCategory;
use App\Modules\TravelAgency\Domain\Enums\QuoteStatus;
use App\Modules\TravelAgency\Domain\Models\TravelQuote;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Domain\Models\TravelTripPrice;
use App\Modules\TravelAgency\Infrastructure\Services\TravelOutboxPublisher;

/**
 * TRAVEL-803 (#6094) — Création d'un devis de groupe.
 *
 * Le total est figé côté serveur depuis les tarifs du trajet (unités
 * mineures, adulte/enfant par classe) — jamais accepté du client. Un groupe
 * doit contenir au moins {@see TravelQuote::MIN_GROUP_SIZE} passagers.
 *
 * @phpstan-type QuotePassengerInput array{
 *     age_category: string,
 *     class_id: int
 * }
 */
final class CreateQuoteAction
{
    public function __construct(private readonly TravelOutboxPublisher $outbox) {}

    /**
     * @param  list<QuotePassengerInput>  $passengers
     */
    public function execute(
        TravelTrip $trip,
        array $passengers,
        Employee $actor,
        string $idempotencyKey,
        ?int $customerContactId = null,
    ): TravelQuote {
        $existing = TravelQuote::query()
            ->where('trip_id', $trip->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing instanceof TravelQuote) {
            return $existing;
        }

        if (count($passengers) < TravelQuote::MIN_GROUP_SIZE) {
            abort(422, 'Un devis de groupe requiert au moins '.TravelQuote::MIN_GROUP_SIZE.' passagers.');
        }

        $total = 0;
        foreach ($passengers as $passengerData) {
            $total += $this->unitPriceFor($trip, $passengerData);
        }

        $quote = TravelQuote::query()->create([
            'trip_id' => $trip->id,
            'status' => QuoteStatus::DRAFT,
            'customer_contact_id' => $customerContactId,
            'passenger_count' => count($passengers),
            'passengers_json' => $passengers,
            'total_amount_minor' => $total,
            'currency' => $this->resolveCurrency($trip),
            'expires_at' => now()->addDays(7),
            'idempotency_key' => $idempotencyKey,
            'created_by_user_id' => $actor->id,
        ]);

        $this->outbox->publish($quote->company_id, 'travel.quote.created.v1', [
            'quote_reference' => $quote->reference,
            'trip_id' => $quote->trip_id,
            'passenger_count' => $quote->passenger_count,
            'total_amount_minor' => $quote->total_amount_minor,
            'currency' => $quote->currency,
            'expires_at' => $quote->expires_at?->toIso8601String(),
        ]);

        return $quote;
    }

    private function resolveCurrency(TravelTrip $trip): string
    {
        $price = $trip->prices()->first();

        if ($price instanceof TravelTripPrice) {
            return $price->currency;
        }

        return currentCompany()->currency;
    }

    /**
     * @param  QuotePassengerInput  $passengerData
     */
    private function unitPriceFor(TravelTrip $trip, array $passengerData): int
    {
        $price = $trip->prices()
            ->where('class_id', $passengerData['class_id'])
            ->first();

        if (! $price instanceof TravelTripPrice) {
            abort(422, 'Aucun tarif defini pour cette classe sur ce trajet.');
        }

        $isChild = AgeCategory::from($passengerData['age_category']) !== AgeCategory::ADULT;

        return $isChild
            ? ($price->child_price_minor ?? $price->adult_price_minor)
            : $price->adult_price_minor;
    }
}
