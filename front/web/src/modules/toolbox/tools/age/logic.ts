import { durationBetween, type CalendarDuration } from '../duree-entre-dates/logic';

/**
 * Âge calendaire (années / mois / jours) à une date de référence.
 *
 * Formule : décomposition calendaire exacte (années entières, puis mois, puis
 * jours), PAS une division par 365,25. Réutilise `durationBetween` pour rester
 * cohérent avec l'outil « durée entre deux dates ».
 *
 * Entrées : date de naissance ISO `YYYY-MM-DD` et date de référence ISO
 * (par défaut : aujourd'hui, en UTC).
 *
 * Cas limite : date invalide → `null`. Date de naissance dans le futur → `null`
 * (un âge négatif n'a pas de sens).
 */
export function ageAt(birthDate: string, referenceDate?: string): CalendarDuration | null {
  const reference = referenceDate ?? new Date().toISOString().slice(0, 10);

  const duration = durationBetween(birthDate, reference);
  if (duration === null) return null;

  // `durationBetween` normalise l'ordre : on vérifie donc explicitement que la
  // naissance n'est pas postérieure à la référence.
  const birth = /^(\d{4})-(\d{2})-(\d{2})/.exec(birthDate.trim());
  const ref = /^(\d{4})-(\d{2})-(\d{2})/.exec(reference.trim());
  if (!birth || !ref) return null;

  const birthKey = `${birth[1]}${birth[2]}${birth[3]}`;
  const refKey = `${ref[1]}${ref[2]}${ref[3]}`;
  if (birthKey > refKey) return null;

  return duration;
}
