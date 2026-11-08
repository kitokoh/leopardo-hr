import { isFiniteNumber } from '../../lib/validation';

/**
 * Durée calendaire entre deux dates (années / mois / jours).
 *
 * Formule : décomposition calendaire exacte, PAS une division par 365,25.
 * Algorithme (celui de `dateutil.relativedelta`) : on avance d'abord en années
 * puis en mois entiers depuis la date de début, en **bornant le jour** à la
 * longueur réelle du mois d'arrivée (ex. 31 janv. + 1 mois = 28/29 févr.), puis
 * on compte les jours restants jusqu'à la date de fin. Si le candidat dépasse
 * la fin, on retire un mois et on recalcule.
 *
 * Entrées : deux dates au format ISO `YYYY-MM-DD`. L'ordre est normalisé : si
 * `from` est après `to`, le résultat est la durée positive entre les deux.
 *
 * Cas limite : date invalide → `null`. Dates identiques → `{ 0, 0, 0, 0 }`.
 */

export interface CalendarDuration {
  years: number;
  months: number;
  days: number;
  /** Nombre total de jours calendaires entre les deux dates (valeur absolue). */
  totalDays: number;
}

interface Ymd {
  y: number;
  m: number;
  d: number;
}

/** Parse une date ISO en composants calendaires, ou `null` si invalide. */
function parseIsoDate(raw: string): Ymd | null {
  if (typeof raw !== 'string') return null;
  const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(raw.trim());
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

/**
 * Ajoute `months` mois à une date, en bornant le jour à la longueur du mois
 * d'arrivée (31 janv. + 1 mois = 28/29 févr.).
 */
function addMonths(date: Ymd, months: number): Ymd {
  const total = date.y * 12 + (date.m - 1) + months;
  const y = Math.floor(total / 12);
  const m = (total % 12) + 1;
  const d = Math.min(date.d, daysInMonth(y, m));
  return { y, m, d };
}

export function durationBetween(from: string, to: string): CalendarDuration | null {
  const a = parseIsoDate(from);
  const b = parseIsoDate(to);
  if (a === null || b === null) return null;

  // Normalise l'ordre : on calcule toujours de la plus ancienne à la plus récente.
  const start = toUtcMs(a) <= toUtcMs(b) ? a : b;
  const end = start === a ? b : a;

  const totalDays = Math.round((toUtcMs(end) - toUtcMs(start)) / 86_400_000);

  let years = end.y - start.y;
  let months = end.m - start.m;
  if (months < 0) {
    years -= 1;
    months += 12;
  }

  // Candidat = début + (années, mois) ; s'il dépasse la fin, on retire un mois.
  let candidate = addMonths(start, years * 12 + months);
  if (toUtcMs(candidate) > toUtcMs(end)) {
    months -= 1;
    if (months < 0) {
      years -= 1;
      months += 12;
    }
    candidate = addMonths(start, years * 12 + months);
  }

  const days = Math.round((toUtcMs(end) - toUtcMs(candidate)) / 86_400_000);

  return { years, months, days, totalDays };
}
