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
