import type { ToolboxLocale } from '../data/copy';

/**
 * Formatage des nombres et devises pour l'affichage des résultats.
 *
 * Le calcul reste en `number` ; seul l'AFFICHAGE est localisé. Les locales
 * Toolbox suivent celles de la vitrine (fr, en, tr, ar).
 */

const INTL_LOCALES: Record<ToolboxLocale, string> = {
  fr: 'fr-FR',
  en: 'en-GB',
  tr: 'tr-TR',
  ar: 'ar-MA',
};

export function intlLocale(locale: ToolboxLocale): string {
  return INTL_LOCALES[locale] ?? INTL_LOCALES.fr;
}

/** Formate un nombre avec séparateurs localisés et un nombre de décimales borné. */
export function formatNumber(
  value: number,
  locale: ToolboxLocale,
  maxFractionDigits = 2,
): string {
  if (!Number.isFinite(value)) return '—';
  return new Intl.NumberFormat(intlLocale(locale), {
    maximumFractionDigits: maxFractionDigits,
    minimumFractionDigits: 0,
  }).format(value);
}

/** Formate un montant en devise (défaut : EUR). */
export function formatCurrency(
  value: number,
  locale: ToolboxLocale,
  currency = 'EUR',
  maxFractionDigits = 2,
): string {
  if (!Number.isFinite(value)) return '—';
  return new Intl.NumberFormat(intlLocale(locale), {
    style: 'currency',
    currency,
    maximumFractionDigits: maxFractionDigits,
  }).format(value);
}

/** Formate un pourcentage (la valeur est déjà en points de pourcentage). */
export function formatPercent(value: number, locale: ToolboxLocale, maxFractionDigits = 2): string {
  if (!Number.isFinite(value)) return '—';
  return `${formatNumber(value, locale, maxFractionDigits)} %`;
}
