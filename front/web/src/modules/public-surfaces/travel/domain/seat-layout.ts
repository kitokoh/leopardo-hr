/**
 * Domaine public — verticale Billets : le plan de sièges.
 *
 * Repris de `front/travel-web/src/lib/seat-layout.ts` (#9628). La logique était
 * déjà pure et framework-neutre dans l'app autonome ; elle est reprise **à
 * l'identique** ici, avec ses règles documentées, parce qu'elle porte des
 * décisions produit non triviales :
 *
 *  - un siège physiquement absent occupe sa place réelle (sinon tous les
 *    numéros suivants se décalent et le client ne retrouve pas son siège) ;
 *  - l'état n'est jamais porté par la seule couleur (symbole + libellé texte) ;
 *  - une géométrie serveur incohérente retombe sur un gabarit déduit, signalé
 *    comme tel — on ne présente jamais une déduction comme une donnée lue.
 *
 * Aucune E/S, aucun import de framework.
 */

/** États de siège portés par l'inventaire et la vue (cf. SeatStatus PHP). */
export type SeatStatusValue =
  | 'free'
  | 'selected'
  | 'held'
  | 'reserved'
  | 'pending_payment'
  | 'sold'
  | 'blocked'
  | 'unavailable';

export interface SeatInput {
  readonly seat_number: number;
  readonly status: SeatStatusValue;
  readonly booking_reference?: string | null;
}

export interface SeatCell {
  /** Numéro de siège, ou `null` pour la colonne couloir. */
  readonly seatNumber: number | null;
  readonly status: SeatStatusValue | 'aisle' | 'absent';
  /** Symbole non coloré (accessibilité / impression N&B). */
  readonly symbol: string;
  /** Libellé texte de l'état. */
  readonly label: string;
  readonly bookingReference?: string | null;
}

export interface SeatRowLayout {
  readonly rowNumber: number;
  readonly cells: readonly SeatCell[];
}

export interface SeatMapLayout {
  readonly rows: readonly SeatRowLayout[];
  readonly columns: number;
  readonly aisleColumn: number | null;
  readonly seatsPerRow: number;
}

export interface LayoutOptions {
  /** Sièges par rangée (2+1 → 3, 2+2 → 4, 2+3 → 5…). Défaut : 4. */
  readonly seatsPerRow?: number;
  /** Insérer une colonne couloir au milieu de la rangée. Défaut : true. */
  readonly aisle?: boolean;
  /** Position (1-based) de la colonne APRÈS laquelle se trouve le couloir. */
  readonly aisleAfter?: number | null;
  /** Numéros de sièges PHYSIQUEMENT ABSENTS du véhicule. */
  readonly absentSeats?: readonly number[];
}

/** Symboles non colorés par état — lisibles en niveaux de gris. */
const STATUS_SYMBOL: Record<SeatStatusValue | 'aisle' | 'absent', string> = {
  free: '',
  selected: '\u2713\u2713',
  held: '\u25F4',
  reserved: '\u25CF',
  pending_payment: '\u20BF',
  sold: '\u2715',
  blocked: '\u2716',
  unavailable: '\u2013',
  aisle: '',
  absent: '\u25A2',
};

/** Libellés texte (FR) par état — source unique pour la légende et l'aria. */
const STATUS_LABEL: Record<SeatStatusValue | 'aisle' | 'absent', string> = {
  free: 'Libre',
  selected: 'Sélectionné',
  held: 'Réservation en cours',
  reserved: 'Réservé',
  pending_payment: 'Paiement en attente',
  sold: 'Vendu',
  blocked: 'Bloqué',
  unavailable: 'Indisponible',
  aisle: 'Couloir',
  absent: 'Siège absent',
};

/** Ordre d'affichage de la légende. */
export const LEGEND_ORDER: readonly SeatStatusValue[] = [
  'free',
  'selected',
  'held',
  'reserved',
  'pending_payment',
  'sold',
  'blocked',
  'unavailable',
];

export function seatStatusLabel(status: SeatStatusValue | 'aisle' | 'absent'): string {
  return STATUS_LABEL[status] ?? String(status);
}

export function seatStatusSymbol(status: SeatStatusValue | 'aisle' | 'absent'): string {
  return STATUS_SYMBOL[status] ?? '';
}

/**
 * Libellé d'accessibilité complet d'un siège (« Siège 12, Réservé »).
 *
 * Combine numéro + état en TEXTE : l'information n'est jamais portée par la
 * seule couleur.
 */
export function seatAriaLabel(seatNumber: number, status: SeatStatusValue): string {
  return `Siège ${seatNumber}, ${seatStatusLabel(status)}`;
}

/** Un siège est-il sélectionnable (donc cliquable) ? */
export function isSeatSelectable(status: SeatStatusValue): boolean {
  return status === 'free' || status === 'selected';
}

/**
 * Reconstruit la disposition complète du bus depuis les sièges réels.
 *
 * - les sièges sont triés par numéro croissant puis découpés en rangées ;
 * - un couloir d'une colonne est inséré au milieu de chaque rangée si demandé ;
 * - la dernière rangée peut être partielle.
 */
export function buildSeatMapLayout(
  seats: readonly SeatInput[],
  options: LayoutOptions = {},
): SeatMapLayout {
  const seatsPerRow = Math.max(1, Math.trunc(options.seatsPerRow ?? 4));
  const absentSeats = options.absentSeats ?? [];
  const absentSet = new Set(absentSeats);

  const sorted: SeatInput[] = (() => {
    if (absentSeats.length === 0) {
      return [...seats].sort((a, b) => a.seat_number - b.seat_number);
    }

    const byNumber = new Map(seats.map((seat) => [seat.seat_number, seat]));
    const maxNumber = Math.max(0, ...seats.map((seat) => seat.seat_number), ...absentSeats);

    const sequence: SeatInput[] = [];

    for (let number = 1; number <= maxNumber; number += 1) {
      if (absentSet.has(number)) {
        // Marqueur de trou : rendu en case « absent », jamais sélectionnable.
        sequence.push({ seat_number: number, status: 'unavailable' });
        continue;
      }

      const seat = byNumber.get(number);

      if (seat) {
        sequence.push(seat);
      }
    }

    return sequence;
  })();

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
      const status: SeatStatusValue | 'absent' = isAbsent ? 'absent' : seat.status;

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
          status: 'aisle',
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

/** Comptes par état — utiles pour le résumé (« 12 libres / 34 vendus »). */
export function countSeatsByStatus(seats: readonly SeatInput[]): Record<string, number> {
  return seats.reduce<Record<string, number>>((acc, seat) => {
    acc[seat.status] = (acc[seat.status] ?? 0) + 1;

    return acc;
  }, {});
}

/**
 * Sièges sélectionnables d'un trajet, dérivés de l'INVENTAIRE RÉEL.
 *
 * Ne garde que les sièges `free` et les renvoie triés. Remplace la génération
 * côté navigateur `1..total_seats`, qui supposait — à tort — que tous les
 * numéros de la capacité sont vendables et disponibles.
 */
export function selectableSeatNumbers(seats: readonly SeatInput[]): number[] {
  return seats
    .filter((seat) => seat.status === 'free')
    .map((seat) => seat.seat_number)
    .sort((a, b) => a - b);
}

/**
 * Gabarit de rangée déduit de la capacité du véhicule.
 *
 * Le modèle ne stocke pas toujours la disposition physique : on regroupe selon
 * le gabarit réel le plus courant (2+2 au-delà de 15 places, 2+1 en deçà).
 */
export function resolveSeatsPerRow(totalSeats: number): number {
  if (!Number.isFinite(totalSeats) || totalSeats <= 0) {
    return 4;
  }

  return totalSeats > 15 ? 4 : 3;
}

/** Disposition de sièges telle qu'exposée par l'API. */
export interface SeatLayoutConfig {
  readonly seats_per_row: number;
  readonly aisle_after: number | null;
  readonly is_configured?: boolean;
  readonly absent_seats?: readonly number[] | null;
}

/** Disposition effective prête à être passée au plan de sièges. */
export interface ResolvedSeatLayout {
  readonly seatsPerRow: number;
  readonly aisleAfter: number | null;
  /** `true` quand la géométrie vient du véhicule, `false` quand elle est déduite. */
  readonly isConfigured: boolean;
  readonly absentSeats: readonly number[];
}

/**
 * Résout la disposition EFFECTIVE d'un plan de sièges.
 *
 * Priorité : configuration serveur → repli sur la capacité. Le repli est
 * signalé par `isConfigured: false` afin que l'UI ne fasse pas passer une
 * déduction pour une donnée lue.
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

/** Normalise `absent_seats` : entiers positifs, uniques, triés. */
function normalizeAbsentSeats(raw: readonly number[] | null | undefined): number[] {
  if (!Array.isArray(raw)) {
    return [];
  }

  const unique = new Set<number>();

  for (const value of raw) {
    if (Number.isInteger(value) && value >= 1) {
      unique.add(value);
    }
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
