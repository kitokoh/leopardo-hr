/**
 * locale-catalog.ts
 *
 * Chargeur de catalogues i18n pour le front/web (Next.js).
 *
 * Les fichiers JSON sont générés par `shared/i18n/sync/sync-web.js`.
 * Source de vérité : shared/i18n/locales/{fr,ar,tr,en}.json
 *
 * Usage :
 *   import { getCatalog, t } from '@/lib/i18n/locale-catalog'
 *   const catalog = getCatalog('ar')
 *   const label   = t('ar', 'common.language.label') // → 'اللغة'
 */

import type { AppLocale } from '@/lib/i18n';

import fr from './locales/fr.json';
import ar from './locales/ar.json';
import tr from './locales/tr.json';
import en from './locales/en.json';

export type LocaleCatalog = Record<string, unknown>;

const CATALOGS: Record<AppLocale, LocaleCatalog> = { fr, ar, tr, en };

/** Retourne le catalogue JSON complet pour une locale. */
export function getCatalog(locale: AppLocale): LocaleCatalog {
  return CATALOGS[locale] ?? CATALOGS['fr'];
}

/**
 * Résout une clé par chemin pointé dans le catalogue.
 *
 * @example
 *   t('ar', 'common.language.label')        // 'اللغة'
 *   t('fr', 'modules.attendance')            // 'Pointage'
 *   t('en', 'welcome.hero.title')            // 'Your workday starts here…'
 */
export function t(locale: AppLocale, key: string, fallback = ''): string {
  const catalog = getCatalog(locale);
  const segments = key.split('.');
  let node: unknown = catalog;

  for (const seg of segments) {
    if (!node || typeof node !== 'object' || !(seg in (node as Record<string, unknown>))) {
      // Essai en fallback fr
      if (locale !== 'fr') return t('fr', key, fallback);
      return fallback;
    }
    node = (node as Record<string, unknown>)[seg];
  }

  return typeof node === 'string' ? node : fallback;
}

/** Retourne la direction du document pour une locale. */
export function catalogDirection(locale: AppLocale): 'ltr' | 'rtl' {
  return locale === 'ar' ? 'rtl' : 'ltr';
}

/** Liste toutes les langues avec leur label natif depuis les catalogues. */
export function getLocaleOptions(): Array<{ value: AppLocale; label: string; nativeLabel: string }> {
  const locales: AppLocale[] = ['fr', 'ar', 'tr', 'en'];
  const nativeKey = 'app.title';

  const nativeLabels: Record<AppLocale, string> = {
    fr: 'Français',
    ar: 'العربية',
    tr: 'Türkçe',
    en: 'English',
  };

  return locales.map((code) => ({
    value: code,
    label: t('fr', `common.language.variants.${code}-${code.toUpperCase()}`, code),
    nativeLabel: nativeLabels[code],
  }));
}
