/**
 * Mission Leopardo — Travel OS (#8602) : disposition du plan de sièges.
 *
 * Logique PURE et framework-neutre, partagée par le plan de sièges du front
 * grand public (React, `seat-map.tsx`) et celui du back-office (Vue,
 * `TravelSeatMap.vue`). Le plan est **dérivé de données réelles** (nombre de
 * sièges du véhicule + état réel de chaque siège renvoyé par l'API) — jamais
 * d'une illustration statique.
 *
 * La disposition (rangées, couloir, sièges simples/doubles) est reconstruite à
 * partir de `total_seats` et d'une configuration `seatsPerRow` / `aisle`.
 *
 * Règle de qualité : les états ne doivent JAMAIS être distinguables par la
 * seule couleur → chaque état porte un libellé texte et un symbole.
 */

/** États de siège portés par l'inventaire et la vue (cf. SeatStatus PHP). */
export type SeatStatusValue =
  | "free"
  | "selected"
  | "held"
  | "reserved"
  | "pending_payment"
  | "sold"
  | "blocked"
  | "unavailable";

export interface SeatInput {
  seat_number: number;
  status: SeatStatusValue;
  /** Référence de réservation, si le siège est rattaché à une réservation. */
  booking_reference?: string | null;
}

export interface SeatCell {
  /** Numéro de siège, ou `null` pour la colonne couloir. */
  seatNumber: number | null;
  status: SeatStatusValue | "aisle";
  /** Symbole non coloré (accessibilité / impression N&B). */
  symbol: string;
  /** Libellé texte de l'état. */
  label: string;
  /** Renseigné uniquement pour les sièges rattachés à une réservation. */
  bookingReference?: string | null;
}

export interface SeatRowLayout {
  rowNumber: number;
  cells: SeatCell[];
}

export interface SeatMapLayout {
  rows: SeatRowLayout[];
  /** Nombre de colonnes (sièges + couloir éventuel). */
  columns: number;
  /** Colonne du couloir (1-indexée), ou `null` si aucun couloir. */
  aisleColumn: number | null;
  seatsPerRow: number;
}

export interface LayoutOptions {
  /** Sièges par rangée (2+1 → 3, 2+2 → 4, 2+3 → 5…). Défaut : 4. */
  seatsPerRow?: number;
  /** Insérer une colonne couloir au milieu de la rangée. Défaut : true. */
  aisle?: boolean;
}

/** Symboles non colorés par état — lisibles en niveaux de gris. */
const STATUS_SYMBOL: Record<SeatStatusValue | "aisle", string> = {
  free: "",
  selected: "\u2713\u2713", // ✓✓ sélectionné
  held: "\u25F4", // ◴ réservation en cours
  reserved: "\u25CF", // ● réservé
  pending_payment: "\u20BF", // ₿ / horloge simplifiée
  sold: "\u2715", // ✕ vendu
  blocked: "\u2716", // ✖ bloqué
  unavailable: "\u2013", // – indisponible
  aisle: "",
};

/** Libellés texte (FR) par état — source unique pour la légende et l'aria. */
const STATUS_LABEL: Record<SeatStatusValue | "aisle", string> = {
  free: "Libre",
  selected: "Sélectionné",
  held: "Réservation en cours",
  reserved: "Réservé",
  pending_payment: "Paiement en attente",
  sold: "Vendu",
  blocked: "Bloqué",
  unavailable: "Indisponible",
  aisle: "Couloir",
};

/** Ordre d'affichage de la légende. */
export const LEGEND_ORDER: SeatStatusValue[] = [
  "free",
  "selected",
  "held",
  "reserved",
  "pending_payment",
  "sold",
  "blocked",
  "unavailable",
];

export function seatStatusLabel(status: SeatStatusValue | "aisle"): string {
  return STATUS_LABEL[status] ?? String(status);
}

export function seatStatusSymbol(status: SeatStatusValue | "aisle"): string {
  return STATUS_SYMBOL[status] ?? "";
}

/**
 * Libellé d'accessibilité complet d'un siège (« Siège 12, Réservé »).
 * Combine numéro + état en TEXTE : l'information n'est jamais portée par la
 * seule couleur.
 */
export function seatAriaLabel(seatNumber: number, status: SeatStatusValue): string {
  return `Siège ${seatNumber}, ${seatStatusLabel(status)}`;
}

/** Un siège est-il sélectionnable (donc cliquable) ? */
export function isSeatSelectable(status: SeatStatusValue): boolean {
  return status === "free" || status === "selected";
}

/**
 * Reconstruit la disposition complète du bus depuis les sièges réels.
 *
 * - les sièges sont triés par numéro croissant puis découpés en rangées ;
 * - un couloir d'une colonne est inséré au milieu de chaque rangée si demandé ;
 * - la dernière rangée peut être partielle (nombre de sièges non multiple).
 */
export function buildSeatMapLayout(
  seats: SeatInput[],
  options: LayoutOptions = {},
): SeatMapLayout {
  const seatsPerRow = Math.max(1, Math.trunc(options.seatsPerRow ?? 4));
  const aisle = options.aisle ?? seatsPerRow >= 3;

  const sorted = [...seats].sort((a, b) => a.seat_number - b.seat_number);
  const aisleAfter = aisle ? Math.ceil(seatsPerRow / 2) : null;

  const rows: SeatRowLayout[] = [];
  for (let i = 0; i < sorted.length; i += seatsPerRow) {
    const chunk = sorted.slice(i, i + seatsPerRow);
    const cells: SeatCell[] = [];
    chunk.forEach((seat, index) => {
      cells.push({
        seatNumber: seat.seat_number,
        status: seat.status,
        symbol: seatStatusSymbol(seat.status),
        label: seatStatusLabel(seat.status),
        bookingReference: seat.booking_reference ?? null,
      });
      if (aisleAfter !== null && index + 1 === aisleAfter) {
        cells.push({
          seatNumber: null,
          status: "aisle",
          symbol: STATUS_SYMBOL.aisle,
          label: STATUS_LABEL.aisle,
        });
      }
    });
    rows.push({ rowNumber: rows.length + 1, cells });
  }

  return {
    rows,
    columns: seatsPerRow + (aisle ? 1 : 0),
    aisleColumn: aisleAfter,
    seatsPerRow,
  };
}

/**
 * Comptes par état — utiles pour le résumé (« 12 libres / 34 vendus ») affiché
 * au responsable comme au client.
 */
export function countSeatsByStatus(seats: SeatInput[]): Record<string, number> {
  return seats.reduce<Record<string, number>>((acc, seat) => {
    acc[seat.status] = (acc[seat.status] ?? 0) + 1;
    return acc;
  }, {});
}

/**
 * #8620 — Sièges sélectionnables d'un trajet, dérivés de l'INVENTAIRE RÉEL.
 *
 * Ne garde que les sièges `free` (les seuls commercialisables) et les renvoie
 * triés par numéro croissant. Remplace la génération côté navigateur
 * `1..total_seats`, qui supposait — à tort — que tous les numéros de la
 * capacité sont vendables et disponibles.
 */
export function selectableSeatNumbers(seats: readonly SeatInput[]): number[] {
  return seats
    .filter((seat) => seat.status === "free")
    .map((seat) => seat.seat_number)
    .sort((a, b) => a - b);
}

/**
 * #8620 — Gabarit de rangée déduit de la capacité du véhicule.
 *
 * Le modèle ne stocke pas encore la disposition physique : on regroupe donc
 * selon le gabarit réel le plus courant (2+2 au-delà de 15 places, 2+1 en
 * deçà). Seul le REGROUPEMENT VISUEL est déduit ; l'état de chaque siège reste
 * celui de l'inventaire serveur.
 */
export function resolveSeatsPerRow(totalSeats: number): number {
  if (!Number.isFinite(totalSeats) || totalSeats <= 0) return 4;
  return totalSeats > 15 ? 4 : 3;
}
