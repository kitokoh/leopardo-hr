/**
 * Mission Leopardo — Travel OS : réconciliation de la sélection de sièges
 * après un rafraîchissement d'inventaire (#8676).
 *
 * `booking-flow.tsx` rafraîchit le plan toutes les 30 s (#8655). Entre deux
 * rafraîchissements, un siège qu'un passager a choisi peut avoir été vendu par
 * quelqu'un d'autre. Le comportement attendu : le siège est **retiré** de la
 * sélection (remis à `null`, jamais transmis à la commande) et un message
 * explicite est affiché — le client ne découvre pas le conflit au moment de
 * payer.
 *
 * Ce calcul était auparavant enfoui dans un `useEffect` du composant React,
 * donc intestable : la seule couverture existante était `seat-layout.test.ts`,
 * qui porte sur la géométrie du plan, pas sur cette réconciliation.
 *
 * La logique est extraite ici, **pure et framework-neutre**, pour deux raisons :
 *  - elle devient testable sans navigateur ni rendu React ;
 *  - elle peut être réutilisée par le plan de sièges du back-office.
 *
 * Choix de contrat : la fonction ne mute rien et ne renvoie que ce qui change
 * (`lostSeats` vide ⇒ aucune mise à jour d'état, aucun message affiché), afin
 * que l'appelant puisse décider sans re-render inutile.
 */

import type { SeatInput } from "./seat-layout";

/** Ce dont la réconciliation a besoin de connaître d'un passager. */
export interface SeatSelectionDraft {
  seat_number: number | null;
}

export interface SeatReconciliation<T extends SeatSelectionDraft> {
  /** Le brouillon de passagers tel qu'il doit être persisté. */
  passengers: T[];
  /** Numéros de siège retirés de la sélection (triés, sans doublon). */
  lostSeats: number[];
  /** Vrai si au moins un siège a été retiré (pilote l'affichage du message). */
  changed: boolean;
}

/**
 * Numéros de siège **non disponibles** dans un inventaire frais.
 *
 * Un siège est sélectionnable uniquement s'il est explicitement `free` : un
 * statut inconnu est traité comme indisponible (fail-closed). Se fier à
 * `status !== "free"` inversé — c'est-à-dire considérer « tout ce qui n'est
 * pas vendu » comme libre — ferait réintégrer un siège `held` (verrouillé par
 * une réservation en cours) dans la sélection.
 */
export function unavailableSeatNumbers(seats: readonly SeatInput[]): Set<number> {
  return new Set(
    seats.filter((seat) => seat.status !== "free").map((seat) => seat.seat_number),
  );
}

/**
 * Retire de la sélection les sièges devenus indisponibles.
 *
 * @param passengers sélection courante (brouillon de passagers)
 * @param freshSeats inventaire fraîchement rechargé depuis l'API
 */
export function reconcileSeatSelection<T extends SeatSelectionDraft>(
  passengers: readonly T[],
  freshSeats: readonly SeatInput[],
): SeatReconciliation<T> {
  const unavailable = unavailableSeatNumbers(freshSeats);

  const lostSeats = [
    ...new Set(
      passengers
        .map((passenger) => passenger.seat_number)
        .filter(
          (seatNumber): seatNumber is number =>
            seatNumber !== null && unavailable.has(seatNumber),
        ),
    ),
  ].sort((a, b) => a - b);

  if (lostSeats.length === 0) {
    return {
      passengers: passengers as T[],
      lostSeats: [],
      changed: false,
    };
  }

  return {
    passengers: passengers.map((passenger) =>
      passenger.seat_number !== null && unavailable.has(passenger.seat_number)
        ? { ...passenger, seat_number: null }
        : passenger,
    ),
    lostSeats,
    changed: true,
  };
}

/**
 * Sièges effectivement transmis à la commande.
 *
 * Garde-fou explicite du critère « aucun `seat_number` fantôme » : un siège
 * `null` ne doit jamais apparaître dans le payload de réservation.
 */
export function submittableSeatNumbers(
  passengers: readonly SeatSelectionDraft[],
): number[] {
  return passengers
    .map((passenger) => passenger.seat_number)
    .filter((seatNumber): seatNumber is number => seatNumber !== null);
}
