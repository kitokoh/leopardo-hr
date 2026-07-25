/**
 * PA2-MKT-003: pricing plans are authored in EUR (see `data/pricing.ts`) but
 * the target markets are Algeria, Morocco, Tunisia, France, Turkey and
 * Canada (see `docs/PLAN_ACTION2/00_SOMMAIRE.md` / `CountryDefaults.php` on
 * the API side). This module lets the pricing page show an approximate,
 * clearly-labelled converted price in the visitor's local currency instead
 * of forcing everyone to mentally convert from EUR.
 *
 * Rates are intentionally static and coarse (no live FX API call from a
 * public marketing page): they only need to give a realistic order of
 * magnitude, and the UI always keeps the source EUR price + an
 * "approximate" disclaimer next to the converted figure so nothing reads as
 * a firm quote in another currency.
 */

export type CurrencyCode = 'EUR' | 'DZD' | 'MAD' | 'TND' | 'TRY' | 'CAD' | 'USD'

export type CountryCode = 'FR' | 'DZ' | 'MA' | 'TN' | 'TR' | 'CA' | 'US'

export type CurrencyOption = {
  country: CountryCode
  currency: CurrencyCode
  /** Approximate EUR -> currency multiplier, rounded for readability. */
  rateFromEur: number
  /** Native-script label shown in the selector, per locale. */
  label: Record<'fr' | 'en' | 'tr' | 'ar', string>
}

// Approximate rates as of the PA2-MKT-003 implementation. Deliberately
// rounded (not pulled from a live feed) since this is only used to give
// prospects a realistic order of magnitude on a public pricing page.
export const CURRENCY_OPTIONS: CurrencyOption[] = [
  {
    country: 'FR',
    currency: 'EUR',
    rateFromEur: 1,
    label: { fr: 'France (EUR)', en: 'France (EUR)', tr: 'Fransa (EUR)', ar: 'فرنسا (يورو)' },
  },
  {
    country: 'DZ',
    currency: 'DZD',
    rateFromEur: 148,
    label: { fr: 'Algerie (DZD)', en: 'Algeria (DZD)', tr: 'Cezayir (DZD)', ar: 'الجزائر (دينار)' },
  },
  {
    country: 'MA',
    currency: 'MAD',
    rateFromEur: 10.8,
    label: { fr: 'Maroc (MAD)', en: 'Morocco (MAD)', tr: 'Fas (MAD)', ar: 'المغرب (درهم)' },
  },
  {
    country: 'TN',
    currency: 'TND',
    rateFromEur: 3.4,
    label: { fr: 'Tunisie (TND)', en: 'Tunisia (TND)', tr: 'Tunus (TND)', ar: 'تونس (دينار)' },
  },
  {
    country: 'TR',
    currency: 'TRY',
    rateFromEur: 39,
    label: { fr: 'Turquie (TRY)', en: 'Turkey (TRY)', tr: 'Turkiye (TRY)', ar: 'تركيا (ليرة)' },
  },
  {
    country: 'CA',
    currency: 'CAD',
    rateFromEur: 1.55,
    label: { fr: 'Canada (CAD)', en: 'Canada (CAD)', tr: 'Kanada (CAD)', ar: 'كندا (دولار كندي)' },
  },
  {
    country: 'US',
    currency: 'USD',
    rateFromEur: 1.08,
    label: { fr: 'Etats-Unis (USD)', en: 'United States (USD)', tr: 'ABD (USD)', ar: 'الولايات المتحدة (دولار)' },
  },
]

export const DEFAULT_CURRENCY_OPTION = CURRENCY_OPTIONS[0]

export function findCurrencyOption(country: string | null | undefined): CurrencyOption {
  const normalized = (country ?? '').toUpperCase()
  return CURRENCY_OPTIONS.find((option) => option.country === normalized) ?? DEFAULT_CURRENCY_OPTION
}

/**
 * Convert a EUR amount (as it is authored in `data/pricing.ts`) into the
 * target currency and format it for display. Returns null for non-numeric
 * inputs (e.g. "Sur devis" / "Custom" / "Teklif") so callers can fall back
 * to the original localized label untouched.
 */
export function convertEurPrice(value: string, option: CurrencyOption): string | null {
  const numeric = Number(value.replace(',', '.'))
  if (!Number.isFinite(numeric)) return null

  const converted = numeric * option.rateFromEur

  // No decimals for large-denomination currencies (DZD/TRY/etc.), keep it
  // readable; small denomination currencies (EUR/USD/CAD) get at most 2.
  const decimals = converted >= 100 ? 0 : converted >= 10 ? 1 : 2
  const rounded = Number(converted.toFixed(decimals))

  return new Intl.NumberFormat('en-US', {
    maximumFractionDigits: decimals,
    minimumFractionDigits: 0,
  }).format(rounded)
}
