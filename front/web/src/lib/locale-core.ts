/**
 * Noyau locale minimal (#8000) — type, registre et préférence persistée.
 *
 * Extrait de `i18n.ts` pour casser le cycle i18n ↔ auth-session : la session
 * auth a besoin de ces primitives (normaliser la langue de l'utilisateur à
 * la connexion), et i18n a besoin de la session (lire la langue stockée).
 * Ce module ne dépend de RIEN : le graphe devient locale-core ← auth-session
 * ← i18n.
 */

export type AppLocale = 'fr' | 'ar' | 'tr' | 'en';
export const SUPPORTED_LOCALES: AppLocale[] = ['fr', 'ar', 'tr', 'en'];
export const PREFERRED_LOCALE_KEY = 'preferred_locale';
export function isSupportedLocale(value: unknown): value is AppLocale {
  return typeof value === 'string' && SUPPORTED_LOCALES.includes(value as AppLocale);
}
export function normalizeLocale(value: unknown): AppLocale {
  if (typeof value !== 'string' || value.trim() === '') {
    return 'fr';
  }

  const normalized = value.toLowerCase().slice(0, 2);
  return isSupportedLocale(normalized) ? normalized : 'fr';
}
export function storePreferredLocale(locale: AppLocale): void {
  if (typeof window === 'undefined') return;
  window.localStorage.setItem(PREFERRED_LOCALE_KEY, locale);
}
