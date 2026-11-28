/**
 * Formatage monetaire.
 *
 * Les montants de l'API sont TOUJOURS en unite mineure (centimes). Les
 * diviser par 100 en flottant introduit des erreurs d'arrondi ; on formate
 * donc sur des entiers.
 */
export function formatMoney(minor: number | null | undefined, currency: string | null | undefined): string {
  if (minor === null || minor === undefined) return '\u2014';
  const code = currency ?? 'XOF';
  const zeroDecimal = code === 'XOF' || code === 'XAF' || code === 'JPY' || code === 'KRW';
  const value = zeroDecimal ? minor : minor / 100;

  try {
    return new Intl.NumberFormat('fr-FR', {
      style: 'currency',
      currency: code,
      minimumFractionDigits: zeroDecimal ? 0 : 2,
      maximumFractionDigits: zeroDecimal ? 0 : 2,
    }).format(value);
  } catch {
    return `${value.toFixed(zeroDecimal ? 0 : 2)} ${code}`;
  }
}

/** Date ISO (`2026-11-28`) vers `28 nov. 2026`. */
export function formatDate(iso: string | null | undefined): string {
  if (!iso) return '\u2014';
  const date = new Date(`${iso.slice(0, 10)}T00:00:00`);
  if (Number.isNaN(date.getTime())) return iso;
  try {
    return new Intl.DateTimeFormat('fr-FR', { day: 'numeric', month: 'short', year: 'numeric' }).format(date);
  } catch {
    return iso;
  }
}

/** `2026-11-28` + `08:30` vers `28 nov. \u00b7 08:30`. */
export function formatDateTime(date: string | null, time: string | null): string {
  const day = formatDate(date);
  return time ? `${day} \u00b7 ${time.slice(0, 5)}` : day;
}

/** Date du jour au format `YYYY-MM-DD` (fuseau local). */
export function todayIso(): string {
  const now = new Date();
  const month = `${now.getMonth() + 1}`.padStart(2, '0');
  const day = `${now.getDate()}`.padStart(2, '0');
  return `${now.getFullYear()}-${month}-${day}`;
}

/** Ajoute `days` jours a une date `YYYY-MM-DD`. */
export function addDaysIso(iso: string, days: number): string {
  const date = new Date(`${iso}T00:00:00`);
  date.setDate(date.getDate() + days);
  const month = `${date.getMonth() + 1}`.padStart(2, '0');
  const day = `${date.getDate()}`.padStart(2, '0');
  return `${date.getFullYear()}-${month}-${day}`;
}

/** Nombre de nuits entre deux dates `YYYY-MM-DD` (jamais negatif). */
export function nightsBetween(from: string, to: string): number {
  const start = new Date(`${from}T00:00:00`).getTime();
  const end = new Date(`${to}T00:00:00`).getTime();
  if (Number.isNaN(start) || Number.isNaN(end)) return 0;
  return Math.max(0, Math.round((end - start) / 86400000));
}

/** Libelle lisible d'un statut metier (`pending` -> `En attente`). */
export function statusLabel(status: string | null | undefined): string {
  if (!status) return 'Inconnu';
  const labels: Record<string, string> = {
    pending: 'En attente',
    confirmed: 'Confirme',
    paid: 'Paye',
    completed: 'Termine',
    served: 'Servi',
    delivered: 'Livre',
    cancelled: 'Annule',
    canceled: 'Annule',
    rejected: 'Refuse',
    failed: 'Echoue',
    expired: 'Expire',
    draft: 'Brouillon',
    accepted: 'Accepte',
    in_progress: 'En cours',
    ready: 'Pret',
    preparing: 'En preparation',
    assigned: 'Chauffeur assigne',
    arrived: 'Chauffeur sur place',
    started: 'Course demarree',
  };
  return labels[status.toLowerCase()] ?? status;
}
