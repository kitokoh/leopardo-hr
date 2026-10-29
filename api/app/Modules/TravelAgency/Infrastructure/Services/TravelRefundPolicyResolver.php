<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Infrastructure\Services;

use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use Carbon\CarbonImmutable;

/**
 * TRAVEL-808 (#6098) / TRAVEL-813 (#6103) — Pénalité de remboursement pour une
 * réservation, exprimée en pourcentage du prix unitaire du passager.
 *
 * **Une seule source de vérité.** Ce résolveur réimplémentait la résolution de
 * politique (ciblage trajet/classe, seuil `hours_before_departure`, drapeau
 * `refundable`) en parallèle de `TravelCancellationPolicyService`. Les deux ont
 * divergé : la table porte `cancel_before_hours` ET un `hours_before_departure`
 * ajouté par une migration de rattrapage, le service canonique lit le premier,
 * ce résolveur lisait le second — resté à sa valeur par défaut de `0`, donc
 * jamais applicable (`hoursBefore < 0` n'est vrai qu'après le départ). Un client
 * configurant « pénalité 25 % sans fenêtre » voyait 0 % appliqué et un
 * remboursement au prix plein.
 *
 * Le calcul délègue désormais à `TravelCancellationPolicyService` ; ce résolveur
 * ne conserve que l'adaptation dont les appelants ont besoin (un pourcentage
 * entier, 100 signifiant « non remboursable »).
 */
final class TravelRefundPolicyResolver
{
    public function __construct(
        private readonly TravelCancellationPolicyService $policies,
    ) {}

    /**
     * Pourcentage de pénalité applicable (0..100).
     *
     * `100` couvre deux refus distincts — politique non remboursable, et
     * politique de pénalité totale — que l'appelant traite de la même façon :
     * on n'accepte pas le remboursement plutôt que de rendre 0 sans rien dire.
     */
    public function penaltyPercent(TravelBooking $booking, int $classId): int
    {
        $trip = $booking->trip;

        if (! $trip instanceof TravelTrip) {
            return 0;
        }

        return $this->penaltyPercentForTrip($trip, $classId);
    }

    public function penaltyPercentForTrip(TravelTrip $trip, ?int $classId): int
    {
        $departure = CarbonImmutable::parse(
            $trip->departure_date->toDateString().' '.($trip->departure_time ?? '00:00'),
        );

        $resolution = $this->policies->penaltyFor($trip, $classId, $departure);

        $policy = $resolution['policy'];

        if ($policy === null) {
            // Aucune politique configurée : règle d'élasticité par défaut
            // (documentée par TRAVEL-813 comme la base que les politiques
            // surclassent). Déléguer au service sans ce repli supprimerait la
            // pénalité de dernier moment sur tout tenant n'ayant rien configuré —
            // c'est-à-dire la majorité, et précisément les cas où le risque
            // commercial est le plus fort.
            $hours = CarbonImmutable::now()->diffInHours($departure, false);

            if ($hours >= 24) {
                return 0;
            }

            return $hours >= 12 ? 10 : 25;
        }

        // `refundable = false` prime sur le pourcentage : un billet non
        // remboursable ne se rembourse pas, même à 100 % de pénalité.
        if (! $policy->refundable) {
            return 100;
        }

        $percent = (int) $resolution['penalty_percent'];

        return max(0, min(100, $percent));
    }
}
