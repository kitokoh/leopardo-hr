import { isFiniteNumber } from '../../lib/validation';

/**
 * Calcul d'une date d'échéance — décalage d'une date dans une unité donnée.
 *
 * Complète `duree-entre-dates` (qui MESURE l'écart entre deux dates) : ici on
 * PROJETTE une date (« dans 30 jours », « dans 3 mois », « 5 jours ouvrés
 * avant »), ce qui est le besoin réel d'un délai de paiement, d'une date de
 * relance ou d'une échéance contractuelle.
 *
 * Formules :
 * - `jours`        : date + n jours calendaires (n peut être négatif) ;
 * - `jours-ouvres` : date + n jours ouvrés, en sautant samedi et dimanche ;
 * - `mois`         : date + n mois, le jour étant BORNÉ à la longueur du mois
 *                    d'arrivée (31 janv. + 1 mois = 28/29 févr.) ;
 * - `annees`       : date + n années, même règle de bornage (29 févr. + 1 an
 *                    = 28 févr. sur une année non bissextile).
 *
 * Tous les calculs se font en UTC : aucun décalage de fuseau ne peut déplacer
 * le résultat d'un jour (piège classique de `new Date('YYYY-MM-DD')`, qui est
 * interprété en UTC puis relu en heure locale).
 *
 * ⚠️ Les jours ouvrés excluent samedi et dimanche, mais **aucun jour férié
 * n'est connu de l'outil** : le résultat est THÉORIQUE et ne remplace pas un
 * calcul de délai légal ou contractuel (avertissement `theoretical`).
 *
 * Cas limites : date invalide, décalage non entier ou hors bornes → `null`.
 */

const DAY_MS = 86_400_000;

/** Bornes de sécurité : au-delà, le résultat n'a plus de sens pratique. */
const MAX_DAYS = 10_000;
const MAX_MONTHS = 1_200;
const MAX_YEARS = 100;

export type DateShiftUnit = 'jours' | 'jours-ouvres' | 'mois' | 'annees';

export const DATE_SHIFT_UNITS: DateShiftUnit[] = ['jours', 'jours-ouvres', 'mois', 'annees'];

export interface DateShiftInput {
  /** Date de départ au format ISO `YYYY-MM-DD`. */
  startDate: string;
  /** Décalage entier, positif (futur) ou négatif (passé). */
  amount: number;
  unit: DateShiftUnit;
}

export interface DateShiftResult {
  /** Date obtenue au format ISO `YYYY-MM-DD`. */
  date: string;
  /** Décalage en jours calendaires (signé : négatif si la date recule). */
  totalDays: number;
  /** Jours ouvrés décomptés entre le départ et l'arrivée (signé). */
  businessDays: number;
  /** Jour de la semaine de la date obtenue (0 = dimanche … 6 = samedi). */
  weekday: number;
}

interface Ymd {
  y: number;
  m: number;
  d: number;
}

/** Parse une date ISO en composants calendaires, ou `null` si invalide. */
function parseIsoDate(raw: string): Ymd | null {
  if (typeof raw !== 'string') return null;
  const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(raw.trim());
  if (!match) return null;

  const y = Number(match[1]);
  const m = Number(match[2]);
  const d = Number(match[3]);
  if (!isFiniteNumber(y) || !isFiniteNumber(m) || !isFiniteNumber(d)) return null;
  if (m < 1 || m > 12 || d < 1 || d > 31) return null;

  // Rejette les dates inexistantes (ex. 2026-02-30) : le round-trip doit être stable.
  const probe = new Date(Date.UTC(y, m - 1, d));
  if (probe.getUTCFullYear() !== y || probe.getUTCMonth() !== m - 1 || probe.getUTCDate() !== d) {
    return null;
  }

  return { y, m, d };
}

/** Nombre de jours dans un mois donné (1-12) d'une année donnée. */
function daysInMonth(year: number, month: number): number {
  return new Date(Date.UTC(year, month, 0)).getUTCDate();
}

/** Timestamp UTC (ms) d'une date calendaire. */
function toUtcMs(date: Ymd): number {
  return Date.UTC(date.y, date.m - 1, date.d);
}

/** Composants calendaires d'un timestamp UTC. */
function fromUtcMs(ms: number): Ymd {
  const date = new Date(ms);
  return { y: date.getUTCFullYear(), m: date.getUTCMonth() + 1, d: date.getUTCDate() };
}

/** Formate des composants calendaires en ISO `YYYY-MM-DD`. */
function toIso(date: Ymd): string {
  const y = String(date.y).padStart(4, '0');
  const m = String(date.m).padStart(2, '0');
  const d = String(date.d).padStart(2, '0');
  return `${y}-${m}-${d}`;
}

/** Vrai si le timestamp UTC tombe un samedi ou un dimanche. */
function isWeekend(ms: number): boolean {
  const day = new Date(ms).getUTCDay();
  return day === 0 || day === 6;
}

/**
 * Ajoute `months` mois à une date, en bornant le jour à la longueur du mois
 * d'arrivée (31 janv. + 1 mois = 28/29 févr.).
 */
function addMonthsClamped(date: Ymd, months: number): Ymd {
  const total = date.y * 12 + (date.m - 1) + months;
  const y = Math.floor(total / 12);
  const m = (total % 12) + 1;
  const d = Math.min(date.d, daysInMonth(y, m));
  return { y, m, d };
}

/**
 * Ajoute `count` jours ouvrés (lun-ven) à un timestamp UTC.
 *
 * On avance d'un jour à la fois et on ne décompte que les jours ouvrés : c'est
 * la seule façon correcte de traiter un décalage négatif (reculer de 1 jour
 * ouvré depuis un lundi donne le vendredi précédent, pas le dimanche).
 */
function addBusinessDays(startMs: number, count: number): number {
  if (count === 0) return startMs;
  const step = count > 0 ? 1 : -1;
  let remaining = Math.abs(count);
  let cursor = startMs;

  while (remaining > 0) {
    cursor += step * DAY_MS;
    if (!isWeekend(cursor)) remaining -= 1;
  }

  return cursor;
}

/**
 * Compte les jours ouvrés entre deux timestamps UTC (signé).
 *
 * Convention : l'intervalle est semi-ouvert — la date de DÉPART est exclue, la
 * date d'ARRIVÉE est incluse. Un décalage de 1 jour ouvré donne donc bien 1.
 */
function countBusinessDays(startMs: number, endMs: number): number {
  if (startMs === endMs) return 0;
  const step = endMs > startMs ? 1 : -1;
  let cursor = startMs;
  let count = 0;

  while (cursor !== endMs) {
    cursor += step * DAY_MS;
    if (!isWeekend(cursor)) count += step;
  }

  return count;
}

/** Vrai si l'unité est reconnue. */
function isDateShiftUnit(value: unknown): value is DateShiftUnit {
  return typeof value === 'string' && (DATE_SHIFT_UNITS as string[]).includes(value);
}

/**
 * Projette une date dans une unité donnée.
 *
 * Retourne `null` sur entrée invalide (date inexistante, décalage non entier,
 * unité inconnue, décalage hors bornes) — jamais `NaN` ni `Infinity`.
 */
export function shiftDate(input: DateShiftInput): DateShiftResult | null {
  if (!input || typeof input !== 'object') return null;

  const start = parseIsoDate(input.startDate);
  if (start === null) return null;

  const { amount, unit } = input;
  if (!isFiniteNumber(amount) || !Number.isInteger(amount)) return null;
  if (!isDateShiftUnit(unit)) return null;

  // Bornes par unité : un décalage absurde n'est pas un résultat, c'est une faute de saisie.
  const limit =
    unit === 'jours' || unit === 'jours-ouvres'
      ? MAX_DAYS
      : unit === 'mois'
        ? MAX_MONTHS
        : MAX_YEARS;
  if (Math.abs(amount) > limit) return null;

  const startMs = toUtcMs(start);
  let endMs: number;

  switch (unit) {
    case 'jours':
      endMs = startMs + amount * DAY_MS;
      break;
    case 'jours-ouvres':
      endMs = addBusinessDays(startMs, amount);
      break;
    case 'mois':
      endMs = toUtcMs(addMonthsClamped(start, amount));
      break;
    case 'annees':
      endMs = toUtcMs(addMonthsClamped(start, amount * 12));
      break;
  }

  const end = fromUtcMs(endMs);

  return {
    date: toIso(end),
    totalDays: Math.round((endMs - startMs) / DAY_MS),
    businessDays: countBusinessDays(startMs, endMs),
    weekday: new Date(endMs).getUTCDay(),
  };
}
