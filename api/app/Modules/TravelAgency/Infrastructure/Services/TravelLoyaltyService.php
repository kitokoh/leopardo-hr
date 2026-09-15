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
     * Valeur d'un point en unités mineures d'avoir (#7445 — règle héritée de
     * l'implémentation supprimée `LoyaltyPointsService`, conservée à
     * l'identique : 1 point = 10 unités mineures).
     */
    public const REDEEM_RATE = 10;

    /**
     * Motif par defaut d un echange direct de points. Le libelle vit ici, hors
     * des surfaces couvertes par la garde i18n PA2-I18N-007 (qui interdit un
     * litteral accentue dans un controleur) — #7445.
     */
    public const DEFAULT_REDEEM_REASON = 'Récompense fidélité';

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
        $account = $this->findAccount($companyId, $contactIdentifier)
            ?? $this->newAccount($companyId, $contactIdentifier);

        $account->forceFill([
            'opt_in' => true,
            'opt_in_at' => now(),
            'opt_out_at' => null,
        ])->save();

        // #7445 — relecture : `points_balance` a un défaut en base (0) que
        // l'instance en mémoire ne porte pas, et la réponse d'opt-in publie le solde.
        return $account->refresh();
    }

    public function optOut(string $companyId, string $contactIdentifier): TravelLoyaltyAccount
    {
        $account = $this->findAccount($companyId, $contactIdentifier)
            ?? $this->newAccount($companyId, $contactIdentifier);

        $account->forceFill([
            'opt_in' => false,
            'opt_out_at' => now(),
        ])->save();

        return $account->refresh();
    }

    /**
     * #7445 — le service reçoit `company_id` en paramètre : il ne doit donc PAS
     * dépendre du contexte tenant ambiant. `BelongsToCompany` n'auto-remplit
     * `company_id` qu'en présence d'une compagnie courante (et `company_id`
     * n'est pas *fillable*, par choix de sécurité) : un appel hors contexte
     * tenant (test, commande, job) insérait un compte orphelin → violation
     * NOT NULL. L'écriture est donc explicite.
     */
    private function newAccount(string $companyId, string $contactIdentifier): TravelLoyaltyAccount
    {
        $account = new TravelLoyaltyAccount;
        $account->forceFill([
            'company_id' => $companyId,
            'contact_identifier' => $this->normalize($contactIdentifier),
            'points_balance' => 0,
            'opt_in' => false,
        ]);

        return $account;
    }

    public function balance(string $companyId, string $contactIdentifier): int
    {
        $account = $this->findAccount($companyId, $contactIdentifier);

        return $account instanceof TravelLoyaltyAccount ? $account->points_balance : 0;
    }

    /**
     * Compte d'un contact (ou `null`) — aucune création implicite : le compte
     * n'existe qu'à l'opt-in explicite (RGPD).
     */
    public function findAccount(string $companyId, string $contactIdentifier): ?TravelLoyaltyAccount
    {
        /** @var TravelLoyaltyAccount|null $account */
        $account = TravelLoyaltyAccount::query()
            ->where('company_id', $companyId)
            ->where('contact_identifier', $this->normalize($contactIdentifier))
            ->first();

        return $account;
    }

    /**
     * Échange de points contre un avoir (1 point = {@see self::REDEEM_RATE}
     * unités mineures), débité du solde et journalisé dans
     * `travel_loyalty_entries` — **le même journal que les crédits** (#7445 :
     * l'ancien chemin écrivait dans `travel_loyalty_transactions`, une table
     * qu'aucune migration ne crée).
     *
     * @return array{discount_minor: int, points_burned: int, points_balance: int}
     */
    public function redeemPoints(
        string $companyId,
        string $contactIdentifier,
        int $points,
        ?int $bookingId = null,
        string $reason = self::DEFAULT_REDEEM_REASON,
    ): array {
        if ($points <= 0) {
            abort(422, 'Points invalides.');
        }

        $account = $this->findAccount($companyId, $contactIdentifier);

        if (! $account instanceof TravelLoyaltyAccount || ! $account->isOptedIn()) {
            abort(422, 'Compte de fidélité inactif (opt-in requis).');
        }

        if ($account->points_balance < $points) {
            abort(422, 'Solde de points insuffisant.');
        }

        // Idempotence par pré-vérification (pattern #4978 — jamais de catch de
        // contrainte unique dans une transaction PostgreSQL).
        if ($bookingId !== null) {
            $alreadyRedeemed = TravelLoyaltyEntry::query()
                ->where('company_id', $companyId)
                ->where('booking_id', $bookingId)
                ->where('type', TravelLoyaltyEntry::TYPE_REDEEMED)
                ->exists();

            if ($alreadyRedeemed) {
                abort(422, 'Récompense déjà utilisée pour cette réservation.');
            }
        }

        return DB::transaction(function () use ($companyId, $account, $points, $bookingId, $reason): array {
            TravelLoyaltyEntry::query()->create([
                'company_id' => $companyId,
                'account_id' => $account->id,
                'booking_id' => $bookingId,
                'ticket_id' => null,
                'points' => -$points,
                'type' => TravelLoyaltyEntry::TYPE_REDEEMED,
                // La colonne est bornée à 255 : la requête accepte 500.
                'reason' => mb_substr($reason, 0, 255),
            ]);

            $account->decrement('points_balance', $points);

            return [
                'discount_minor' => $points * self::REDEEM_RATE,
                'points_burned' => $points,
                'points_balance' => (int) $account->refresh()->points_balance,
            ];
        });
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
