/**
 * Domaine public — verticale Billets : réconciliation de la sélection de sièges.
 *
 * Repris de `front/travel-web/src/lib/seat-selection.ts` (#9628). Le calcul
 * était enfoui dans un `useEffect` React, donc intestable ; il est ici pur et
 * framework-neutre.
 *
 * ## Le problème qu'il résout
 *
 * Le plan de sièges se rafraîchit toutes les 30 s. Entre deux rafraîchissements,
 * un siège choisi peut avoir été vendu. Le comportement attendu : le siège est
 * **retiré** de la sélection (remis à `null`, jamais transmis à la commande) et
 * un message explicite est affiché — le client ne découvre pas le conflit au
 * moment de payer.
 *
 * Aucune E/S, aucun import de framework.
 */

import type { SeatInput } from './seat-layout';

/** Ce dont la réconciliation a besoin de connaître d'un passager. */
export interface SeatSelectionDraft {
  readonly seat_number: number | null;
}

export interface SeatReconciliation<T extends SeatSelectionDraft> {
  /** Le brouillon de passagers tel qu'il doit être persisté. */
  readonly passengers: readonly T[];
  /** Numéros de siège retirés de la sélection (triés, sans doublon). */
  readonly lostSeats: readonly number[];
  /** Vrai si au moins un siège a été retiré (pilote l'affichage du message). */
  readonly changed: boolean;
}

/**
 * Numéros de siège **non disponibles** dans un inventaire frais.
 *
 * Un siège est sélectionnable uniquement s'il est explicitement `free` : un
 * statut inconnu est traité comme indisponible (fail-closed). Considérer « tout
 * ce qui n'est pas vendu » comme libre ferait réintégrer un siège `held`
 * (verrouillé par une réservation en cours) dans la sélection.
 */
export function unavailableSeatNumbers(seats: readonly SeatInput[]): Set<number> {
  return new Set(seats.filter((seat) => seat.status !== 'free').map((seat) => seat.seat_number));
}

/**
 * Retire de la sélection les sièges devenus indisponibles.
 *
 * Ne mute rien et ne renvoie que ce qui change (`lostSeats` vide ⇒ aucune mise
 * à jour d'état, aucun message affiché), afin que l'appelant puisse décider
 * sans re-render inutile.
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
    return { passengers, lostSeats: [], changed: false };
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
