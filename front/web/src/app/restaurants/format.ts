import type { AppLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';

/**
 * RESTO-903 (#7748) — helpers de présentation partagés des pages publiques
 * /restaurants (annuaire + profil). Purs (aucun effet), utilisables côté
 * serveur comme côté client.
 */

/** Montant mineur → devise localisée (même convention que /shop). */
export function formatMoney(minor: number, currency: string, locale: string): string {
  try {
    return new Intl.NumberFormat(locale, {
      style: 'currency',
      currency,
      maximumFractionDigits: 2,
    }).format(minor / 100);
  } catch {
    return `${(minor / 100).toFixed(2)} ${currency}`;
  }
}

/** Libellé i18n d'un type d'établissement (8 valeurs du contrat public). */
export function establishmentTypeLabel(locale: AppLocale, type: string | null): string {
  switch (type) {
    case 'restaurant':
      return t(locale, 'restaurant.public.typeRestaurant');
    case 'fast_food':
      return t(locale, 'restaurant.public.typeFastFood');
    case 'pizzeria':
      return t(locale, 'restaurant.public.typePizzeria');
    case 'brasserie':
      return t(locale, 'restaurant.public.typeBrasserie');
    case 'cafe':
      return t(locale, 'restaurant.public.typeCafe');
    case 'patisserie':
      return t(locale, 'restaurant.public.typePatisserie');
    case 'traiteur':
      return t(locale, 'restaurant.public.typeTraiteur');
    case 'autre':
      return t(locale, 'restaurant.public.typeAutre');
    default:
      return '';
  }
}

/**
 * Nom localisé d'un jour d'horaire. Convention RestaurantHour (RESTO-805) :
 * 0 = lundi … 6 = dimanche. 2024-01-01 est un lundi (date pivot stable).
 */
export function dayOfWeekName(locale: AppLocale, dayOfWeek: number): string {
  const base = Date.UTC(2024, 0, 1 + ((dayOfWeek % 7) + 7) % 7);
  try {
    return new Intl.DateTimeFormat(locale, { weekday: 'long', timeZone: 'UTC' }).format(base);
  } catch {
    return String(dayOfWeek);
  }
}

/** Note moyenne affichable — « 4,5 ★ » (null si aucun avis publié). */
export function formatRating(locale: AppLocale, ratingAvg: number | null): string | null {
  if (ratingAvg === null || Number.isNaN(ratingAvg)) {
    return null;
  }
  try {
    return `${new Intl.NumberFormat(locale, { maximumFractionDigits: 1 }).format(ratingAvg)} ★`;
  } catch {
    return `${ratingAvg} ★`;
  }
}
