<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Infrastructure\Services;

use App\Modules\TravelAgency\Domain\Models\TravelLoyaltyAccount;
use App\Modules\TravelAgency\Domain\Models\TravelLoyaltyTransaction;
use App\Modules\TravelAgency\Domain\Models\TravelTicket;
use Illuminate\Support\Facades\DB;

/**
 * TRAVEL-811 (#6101) — Points de fidélité voyageur.
 *
 * - Crédit : 1 point par tranche de 100 unités mineures du billet, UNE seule
 *   fois par billet (unique ticket_id) et uniquement si le compte est opt-in
 *   (RGPD explicite).
 * - Récompense : 1 point = 10 unités mineures d'avoir (REDEEM_RATE),
 *   débité du solde, lié à une réservation.
 * - Opt-out : gèle les crédits, le solde reste consultable.
 */
final class LoyaltyPointsService
{
    /** Points crédités par tranche de 100 unités mineures. */
    public const EARN_UNIT = 100;

    /** Valeur d'un point en unités mineures d'avoir. */
    public const REDEEM_RATE = 10;

    public function earnForTicket(TravelTicket $ticket, int $priceMinor): ?TravelLoyaltyTransaction
    {
        $contactId = $ticket->booking?->customer_contact_id;

        if (! is_int($contactId)) {
            return null;
        }

        return DB::transaction(function () use ($ticket, $contactId, $priceMinor): ?TravelLoyaltyTransaction {
            /** @var TravelLoyaltyAccount|null $account */
            $account = TravelLoyaltyAccount::query()
                ->where('contact_id', $contactId)
                ->first();

            if (! $account instanceof TravelLoyaltyAccount || ! $account->isOptedIn()) {
                return null;
            }

            $alreadyCredited = TravelLoyaltyTransaction::query()
                ->where('ticket_id', $ticket->id)
                ->exists();

            if ($alreadyCredited) {
                return null;
            }

            $points = intdiv($priceMinor, self::EARN_UNIT);

            if ($points <= 0) {
                return null;
            }

            $transaction = TravelLoyaltyTransaction::query()->create([
                'account_id' => $account->id,
                'points' => $points,
                'type' => 'earn',
                'reason' => 'Billet '.$ticket->ticket_number,
                'ticket_id' => $ticket->id,
            ]);

            $account->increment('points_balance', $points);

            return $transaction;
        });
    }

    /**
     * @return array{points_balance: int, opted_in: bool}
     */
    public function balance(int $contactId): array
    {
        /** @var TravelLoyaltyAccount|null $account */
        $account = TravelLoyaltyAccount::query()->where('contact_id', $contactId)->first();

        if (! $account instanceof TravelLoyaltyAccount) {
            return ['points_balance' => 0, 'opted_in' => false];
        }

        return [
            'points_balance' => $account->points_balance,
            'opted_in' => $account->isOptedIn(),
        ];
    }

    public function optIn(int $contactId): TravelLoyaltyAccount
    {
        /** @var TravelLoyaltyAccount $account */
        $account = TravelLoyaltyAccount::query()->firstOrCreate(
            ['contact_id' => $contactId],
            ['contact_id' => $contactId, 'points_balance' => 0, 'opt_in_at' => now()],
        );

        if ($account->opt_in_at === null) {
            $account->forceFill(['opt_in_at' => now(), 'opt_out_at' => null])->save();
        }

        return $account->refresh();
    }

    public function optOut(int $contactId): TravelLoyaltyAccount
    {
        /** @var TravelLoyaltyAccount|null $account */
        $account = TravelLoyaltyAccount::query()->where('contact_id', $contactId)->first();

        if (! $account instanceof TravelLoyaltyAccount) {
            /** @var TravelLoyaltyAccount $created */
            $created = TravelLoyaltyAccount::query()->create([
                'contact_id' => $contactId,
                'points_balance' => 0,
                'opt_in_at' => null,
                'opt_out_at' => now(),
            ]);

            return $created;
        }

        $account->forceFill(['opt_out_at' => now()])->save();

        return $account->refresh();
    }

    /**
     * Récompense : convertit des points en avoir (1 point = 10 unités
     * mineures), débité du solde, lié à la réservation.
     *
     * @return array{discount_minor: int, points_burned: int}
     */
    public function redeem(int $contactId, int $points, ?int $bookingId, string $reason = 'Récompense fidélité'): array
    {
        if ($points <= 0) {
            abort(422, 'Points invalides.');
        }

        /** @var TravelLoyaltyAccount $account */
        $account = TravelLoyaltyAccount::query()
            ->where('contact_id', $contactId)
            ->firstOrFail();

        if ($account->points_balance < $points) {
            abort(422, 'Solde de points insuffisant.');
        }

        return DB::transaction(function () use ($account, $points, $bookingId, $reason): array {
            TravelLoyaltyTransaction::query()->create([
                'account_id' => $account->id,
                'points' => -$points,
                'type' => 'burn',
                'reason' => $reason,
                'booking_id' => $bookingId,
            ]);

            $account->decrement('points_balance', $points);

            return [
                'discount_minor' => $points * self::REDEEM_RATE,
                'points_burned' => $points,
            ];
        });
    }
}
