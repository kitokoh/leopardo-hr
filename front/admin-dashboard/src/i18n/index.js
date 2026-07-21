import ar from './locales/ar.json';
import en from './locales/en.json';
import fr from './locales/fr.json';
import tr from './locales/tr.json';

export const defaultLocale = 'fr';
export const rtlLocales = new Set(['ar']);
export const supportedLocales = ['fr', 'ar', 'tr', 'en'];
export const localeVariants = {
  fr: ['fr-FR', 'fr-BE', 'fr-CA'],
  ar: ['ar-SA', 'ar-MA'],
  tr: ['tr-TR'],
  en: ['en-US', 'en-GB'],
};

export const messages = { ar, en, fr, tr };

export function normalizeLocale(input) {
  if (!input) {
    return defaultLocale;
  }

  const normalized = String(input).trim().replace('_', '-').toLowerCase();
  const base = normalized.slice(0, 2);

  return supportedLocales.includes(base) ? base : defaultLocale;
}

export function resolveDirection(locale) {
  return rtlLocales.has(normalizeLocale(locale)) ? 'rtl' : 'ltr';
}

/**
 * Resout un code de locale applicatif (fr/ar/tr/en) vers un tag BCP-47
 * pret pour `Intl.NumberFormat`/`Intl.DateTimeFormat` (ex: 'fr' -> 'fr-FR').
 * Utiliser cette fonction plutot que de coder 'fr-FR' en dur dans les vues.
 */
export function toIntlLocale(locale) {
  const normalized = normalizeLocale(locale);
  return localeVariants[normalized]?.[0] ?? localeVariants[defaultLocale][0];
}

export function translate(locale, key, fallback = '') {
  const normalized = normalizeLocale(locale);
  const dictionary = messages[normalized] || messages[defaultLocale];
  const segments = key.split('.');
  let cursor = dictionary;

  for (const segment of segments) {
    if (!cursor || typeof cursor !== 'object' || !(segment in cursor)) {
      return fallback;
    }
    cursor = cursor[segment];
  }

  return typeof cursor === 'string' ? cursor : fallback;
}
