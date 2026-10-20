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
  status: SeatStatusValue | "aisle" | "absent";
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
  /**
   * Position (1-based) de la colonne APRÈS laquelle se trouve le couloir.
   * `null` = pas de couloir. Quand fourni, prime sur `aisle`.
   */
  aisleAfter?: number | null;
  /**
   * #8652 — numéros de sièges PHYSIQUEMENT ABSENTS du véhicule.
   *
   * L'inventaire du trajet ne contient aucune ligne pour ces sièges : sans
   * cette liste, le plan les ignorerait et **décalerait** les sièges suivants
   * (un car de 30 places dont la 30e est absente afficherait 29 places
   * compactées, et le client ne retrouverait pas son siège dans le bus).
   * Chaque siège absent occupe donc sa place réelle, en case non cliquable.
   */
  absentSeats?: readonly number[];
}

/** Symboles non colorés par état — lisibles en niveaux de gris. */
const STATUS_SYMBOL: Record<SeatStatusValue | "aisle" | "absent", string> = {
  free: "",
  selected: "\u2713\u2713", // ✓✓ sélectionné
  held: "\u25F4", // ◴ réservation en cours
  reserved: "\u25CF", // ● réservé
  pending_payment: "\u20BF", // ₿ / horloge simplifiée
  sold: "\u2715", // ✕ vendu
  blocked: "\u2716", // ✖ bloqué
  unavailable: "\u2013", // – indisponible
  aisle: "",
  absent: "\u25A2", // ▢ siège inexistant dans le bus
};

/** Libellés texte (FR) par état — source unique pour la légende et l'aria. */
const STATUS_LABEL: Record<SeatStatusValue | "aisle" | "absent", string> = {
  free: "Libre",
  selected: "Sélectionné",
  held: "Réservation en cours",
  reserved: "Réservé",
  pending_payment: "Paiement en attente",
  sold: "Vendu",
  blocked: "Bloqué",
  unavailable: "Indisponible",
  aisle: "Couloir",
  absent: "Siège absent",
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

export function seatStatusLabel(status: SeatStatusValue | "aisle" | "absent"): string {
  return STATUS_LABEL[status] ?? String(status);
}

export function seatStatusSymbol(status: SeatStatusValue | "aisle" | "absent"): string {
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

  // #8652 — quand le véhicule déclare des sièges physiquement absents, on
  // reconstruit la séquence PHYSIQUE complète (1..N) : chaque siège absent
  // occupe sa place réelle en case non cliquable, au lieu d'être simplement
  // omis — ce qui décalerait tous les sièges suivants et ferait mentir le plan.
  const absentSeats = options.absentSeats ?? [];
  const absentSet = new Set(absentSeats);

  const sorted: SeatInput[] = (() => {
    if (absentSeats.length === 0) {
      return [...seats].sort((a, b) => a.seat_number - b.seat_number);
    }

    const byNumber = new Map(seats.map((seat) => [seat.seat_number, seat]));
    const maxNumber = Math.max(
      0,
      ...seats.map((seat) => seat.seat_number),
      ...absentSeats,
    );

    const sequence: SeatInput[] = [];
    for (let number = 1; number <= maxNumber; number += 1) {
      if (absentSet.has(number)) {
        // Marqueur de trou : rendu en case « absent », jamais sélectionnable.
        sequence.push({ seat_number: number, status: "absent" as SeatStatusValue });
        continue;
      }

      const seat = byNumber.get(number);
      if (seat) sequence.push(seat);
    }

    return sequence;
  })();

  // #8646 — quand le serveur précise la position du couloir, elle fait foi ;
  // `null` signifie explicitement « aucun couloir ». Sinon on garde l'ancien
  // comportement (couloir dès 3 sièges par rangée).
  const aisleAfter =
    options.aisleAfter !== undefined && options.aisleAfter !== null
      ? Math.min(Math.max(1, Math.trunc(options.aisleAfter)), seatsPerRow - 1)
      : options.aisleAfter === null
        ? null
        : (options.aisle ?? seatsPerRow >= 3)
          ? Math.ceil(seatsPerRow / 2)
          : null;

  const rows: SeatRowLayout[] = [];
  for (let i = 0; i < sorted.length; i += seatsPerRow) {
    const chunk = sorted.slice(i, i + seatsPerRow);
    const cells: SeatCell[] = [];
    chunk.forEach((seat, index) => {
      const isAbsent = absentSet.has(seat.seat_number);
      const status = isAbsent ? "absent" : seat.status;

      cells.push({
        seatNumber: seat.seat_number,
        status,
        symbol: seatStatusSymbol(status),
        label: seatStatusLabel(status),
        bookingReference: isAbsent ? null : (seat.booking_reference ?? null),
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
    columns: seatsPerRow + (aisleAfter !== null ? 1 : 0),
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

/**
 * #8646 — disposition de sièges telle qu'exposée par l'API.
 *
 * `seats_per_row` / `aisle_after` sont des ENTIERS de géométrie ;
 * `is_configured` dit si la géométrie a été LUE sur le véhicule ou DÉDUITE de
 * sa capacité. Une disposition déduite ne doit jamais être présentée comme
 * une donnée du véhicule.
 */
export interface SeatLayoutConfig {
  seats_per_row: number;
  aisle_after: number | null;
  is_configured?: boolean;
  /** #8652 — sièges physiquement absents du véhicule (numéros). */
  absent_seats?: number[] | null;
}

/** Disposition effective prête à être passée au plan de sièges. */
export interface ResolvedSeatLayout {
  seatsPerRow: number;
  aisleAfter: number | null;
  /** `true` quand la géométrie vient du véhicule, `false` quand elle est déduite. */
  isConfigured: boolean;
  /** #8652 — sièges physiquement absents (vide quand le véhicule n'en déclare pas). */
  absentSeats: number[];
}

/**
 * #8646 — résout la disposition EFFECTIVE d'un plan de sièges.
 *
 * Priorité : configuration serveur → repli historique sur la capacité. Le
 * repli existe uniquement pour les véhicules non configurés (antérieurs à
 * #8644) et pour les trajets sans véhicule affecté ; il est signalé par
 * `isConfigured: false` afin que l'UI ne fasse pas passer une déduction pour
 * une donnée lue.
 */
export function resolveSeatLayout(
  totalSeats: number,
  layout?: SeatLayoutConfig | null,
): ResolvedSeatLayout {
  if (
    layout &&
    Number.isInteger(layout.seats_per_row) &&
    layout.seats_per_row >= 1 &&
    layout.seats_per_row <= 6
  ) {
    const aisleAfter =
      layout.aisle_after === null || layout.aisle_after === undefined
        ? null
        : Math.trunc(layout.aisle_after);

    if (aisleAfter !== null && (aisleAfter < 1 || aisleAfter >= layout.seats_per_row)) {
      // Géométrie serveur incohérente : on ne rend pas un plan impossible.
      return fallbackSeatLayout(totalSeats);
    }

    return {
      seatsPerRow: layout.seats_per_row,
      aisleAfter,
      isConfigured: layout.is_configured !== false,
      absentSeats: normalizeAbsentSeats(layout.absent_seats),
    };
  }

  return fallbackSeatLayout(totalSeats);
}

/**
 * #8652 — normalise `absent_seats` reçu de l'API : entiers positifs, uniques,
 * triés. Une forme inexploitable est ignorée (aucun siège absent) plutôt que de
 * faire échouer le rendu du plan.
 */
function normalizeAbsentSeats(raw: number[] | null | undefined): number[] {
  if (!Array.isArray(raw)) return [];

  const unique = new Set<number>();
  for (const value of raw) {
    if (Number.isInteger(value) && value >= 1) unique.add(value);
  }

  return [...unique].sort((a, b) => a - b);
}

/** Repli historique — utilisé UNIQUEMENT quand aucune géométrie n'est connue. */
function fallbackSeatLayout(totalSeats: number): ResolvedSeatLayout {
  const seatsPerRow = resolveSeatsPerRow(totalSeats);

  return {
    seatsPerRow,
    aisleAfter: seatsPerRow >= 3 ? Math.ceil(seatsPerRow / 2) : null,
    isConfigured: false,
    absentSeats: [],
  };
}
