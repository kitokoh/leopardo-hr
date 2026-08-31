<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Infrastructure\Services;

use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelLoyaltyAccount;
use App\Modules\TravelAgency\Domain\Models\TravelLoyaltyEntry;
use App\Modules\TravelAgency\Domain\Models\TravelLoyaltyReward;
use App\Modules\TravelAgency\Domain\Models\TravelPassenger;
use App\Modules\TravelAgency\Domain\Models\TravelTicket;
use Illuminate\Support\Facades\DB;

/**
 * TRAVEL-811 (#6101) — Programme de fidélité voyageur.
 *
 * - Crédit : à l'émission d'un billet, points selon la classe du passager
 *   (règle configurable par code de classe), UNE seule fois par billet
 *   (unique company+ticket) et uniquement si l'opt-in RGPD est actif
 *   (critère d'acceptation).
 * - Débit : échange de points contre une récompense, idempotent par
 *   réservation (unique company+booking+type).
 * - Solde : maintenu transactionnellement (jamais dérivé).
 */
final class TravelLoyaltyService
{
    /**
     * Crédite les points d'un billet émis (no-op si pas d'opt-in ou billet
     * déjà crédité). Retourne le nombre de points crédités (0 sinon).
     */
    public function creditForTicket(TravelTicket $ticket): int
    {
        $ticket->loadMissing('booking.passengers', 'passenger');

        $booking = $ticket->booking;
        if (! $booking instanceof TravelBooking) {
            return 0;
        }

        $contact = $this->contactFor($booking);

        if ($contact === null) {
            return 0;
        }

        $points = $this->pointsFor($ticket->passenger);

        if ($points <= 0) {
            return 0;
        }

        // Compte inexistant ou sans opt-in → aucun crédit (RGPD) et aucune
        // création de compte : le compte n'existe qu'à l'opt-in explicite.
        $account = TravelLoyaltyAccount::query()
            ->where('company_id', $booking->company_id)
            ->where('contact_identifier', $contact)
            ->first();

        if (! $account instanceof TravelLoyaltyAccount || ! $account->opt_in) {
            return 0;
        }

        // Idempotence par pré-vérification (pas de catch dans la transaction
        // PostgreSQL — un UniqueConstraintViolation y abandonnerait toute la
        // transaction, pattern #4978) : un billet ne crédite jamais deux fois.
        $alreadyCredited = TravelLoyaltyEntry::query()
            ->where('company_id', $booking->company_id)
            ->where('ticket_id', $ticket->id)
            ->exists();

        if ($alreadyCredited) {
            return 0;
        }

        return DB::transaction(function () use ($booking, $account, $ticket, $points): int {
            TravelLoyaltyEntry::query()->create([
                'company_id' => $booking->company_id,
                'account_id' => $account->id,
                'booking_id' => $booking->id,
                'ticket_id' => $ticket->id,
                'points' => $points,
                'type' => TravelLoyaltyEntry::TYPE_EARNED,
                'reason' => 'Billet '.$ticket->ticket_number,
            ]);

            $account->increment('points_balance', $points);

            return $points;
        });
    }

    public function optIn(string $companyId, string $contactIdentifier): TravelLoyaltyAccount
    {
        /** @var TravelLoyaltyAccount $account */
        $account = TravelLoyaltyAccount::query()->updateOrCreate(
            [
                'company_id' => $companyId,
                'contact_identifier' => $this->normalize($contactIdentifier),
            ],
            [
                'opt_in' => true,
                'opt_in_at' => now(),
                'opt_out_at' => null,
            ],
        );

        return $account;
    }

    public function optOut(string $companyId, string $contactIdentifier): TravelLoyaltyAccount
    {
        /** @var TravelLoyaltyAccount $account */
        $account = TravelLoyaltyAccount::query()->updateOrCreate(
            [
                'company_id' => $companyId,
                'contact_identifier' => $this->normalize($contactIdentifier),
            ],
            [
                'opt_in' => false,
                'opt_out_at' => now(),
            ],
        );

        return $account;
    }

    public function balance(string $companyId, string $contactIdentifier): int
    {
        $account = TravelLoyaltyAccount::query()
            ->where('company_id', $companyId)
            ->where('contact_identifier', $this->normalize($contactIdentifier))
            ->first();

        return $account instanceof TravelLoyaltyAccount ? $account->points_balance : 0;
    }

    /**
     * @return list<TravelLoyaltyEntry>
     */
    public function entries(string $companyId, string $contactIdentifier, int $limit = 50): array
    {
        $account = TravelLoyaltyAccount::query()
            ->where('company_id', $companyId)
            ->where('contact_identifier', $this->normalize($contactIdentifier))
            ->first();

        if (! $account instanceof TravelLoyaltyAccount) {
            return [];
        }

        /** @var list<TravelLoyaltyEntry> $entries */
        $entries = $account->entries()
            ->orderByDesc('created_at')
            ->limit(max(1, min(200, $limit)))
            ->get()
            ->all();

        return $entries;
    }

    /**
     * Échange de points contre une récompense (débit idempotent par
     * réservation).
     */
    public function redeem(
        string $companyId,
        string $contactIdentifier,
        int $rewardId,
        int $bookingId,
    ): TravelLoyaltyEntry {
        $reward = TravelLoyaltyReward::query()
            ->where('company_id', $companyId)
            ->whereKey($rewardId)
            ->where('active', true)
            ->firstOrFail();

        $account = TravelLoyaltyAccount::query()
            ->where('company_id', $companyId)
            ->where('contact_identifier', $this->normalize($contactIdentifier))
            ->first();

        if (! $account instanceof TravelLoyaltyAccount || ! $account->opt_in) {
            abort(422, 'Compte de fidélité inactif (opt-in requis).');
        }

        if ($account->points_balance < $reward->points_cost) {
            abort(422, 'Solde de points insuffisant.');
        }

        // Idempotence par pré-vérification (pattern #4978 — jamais de catch
        // de contrainte unique à l'intérieur d'une transaction PostgreSQL).
        $alreadyRedeemed = TravelLoyaltyEntry::query()
            ->where('company_id', $companyId)
            ->where('booking_id', $bookingId)
            ->where('type', TravelLoyaltyEntry::TYPE_REDEEMED)
            ->exists();

        if ($alreadyRedeemed) {
            abort(422, 'Récompense déjà utilisée pour cette réservation.');
        }

        return DB::transaction(function () use ($companyId, $account, $reward, $bookingId): TravelLoyaltyEntry {
            $entry = TravelLoyaltyEntry::query()->create([
                'company_id' => $companyId,
                'account_id' => $account->id,
                'booking_id' => $bookingId,
                'ticket_id' => null,
                'points' => -$reward->points_cost,
                'type' => TravelLoyaltyEntry::TYPE_REDEEMED,
                'reason' => 'Récompense : '.$reward->name,
            ]);

            $account->decrement('points_balance', $reward->points_cost);

            return $entry;
        });
    }

    /**
     * Points d'un passager selon la classe (règle config, repli défaut).
     */
    private function pointsFor(?TravelPassenger $passenger): int
    {
        if (! $passenger instanceof TravelPassenger) {
            return 0;
        }

        $default = (int) config('travel.loyalty.default_points_per_trip', 10);
        $rules = (array) config('travel.loyalty.points_per_class', []);

        $classCode = $passenger->travelClass?->code;

        if ($classCode !== null && isset($rules[$classCode])) {
            return (int) $rules[$classCode];
        }

        return $default;
    }

    /**
     * Identifiant de contact d'une réservation (email prioritaire).
     */
    private function contactFor(TravelBooking $booking): ?string
    {
        if (is_string($booking->contact_email) && trim($booking->contact_email) !== '') {
            return $this->normalize($booking->contact_email);
        }

        if (is_string($booking->contact_phone) && trim($booking->contact_phone) !== '') {
            return $this->normalize($booking->contact_phone);
        }

        return null;
    }

    private function normalize(string $identifier): string
    {
        $identifier = trim($identifier);

        return str_contains($identifier, '@')
            ? strtolower($identifier)
            : preg_replace('/\s+/', '', $identifier) ?? $identifier;
    }
}
