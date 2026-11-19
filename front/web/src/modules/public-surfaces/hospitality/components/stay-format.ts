import type { AppLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';

/**
 * HOSP-008 (#7950) — helpers de présentation de la vitrine `/stay/{slug}`
 * (même rôle que `app/restaurants/format.ts`, RESTO-903).
 */

/** Montant en unité mineure → devise localisée (fallback texte brut). */
export function formatStayMoney(minor: number, currency: string, locale: string): string {
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

/** Libellé i18n d'un type d'établissement (4 valeurs du contrat public HOSP-002). */
export function stayPropertyTypeLabel(locale: AppLocale, type: string): string {
  const key = `stay.public.propertyType.${type}`;
  return t(locale, key, t(locale, 'stay.public.propertyType.hotel'));
}
