/**
 * #9580 (BC-41 BRANDING) — cas d'usage : résoudre une surface publique.
 *
 * ## Pourquoi une couche application si le repository est mince
 *
 * Parce que le RENDU a besoin d'un objet déjà prêt, pas d'un DTO brut plus
 * trois fonctions de calcul dispersées. `resolveSurface()` compose :
 *
 *   1. la résolution de la Brand (déléguée à l'infrastructure) ;
 *   2. le choix de la locale (query string → locale par défaut de la Brand) ;
 *   3. la dérivation du thème (fonction pure du domaine) ;
 *   4. l'ordre des capacités (CTA primaire en tête).
 *
 * Le composant reçoit le résultat et n'a plus qu'à rendre. C'est ce qui rend
 * la règle vérifiable : aucun composant ne peut « décider » d'un thème, donc
 * aucun composant ne peut diverger de la charte.
 */

import {
  directionFor,
  normalizeLocale,
  type PublicBrand,
} from '../domain/brand';
import { buildThemeTokens, capabilityOrder, type ThemeTokens } from '../domain/theme';

/** Ce que le rendu consomme : un DTO résolu, pas un DTO brut. */
export interface ResolvedSurface {
  brand: PublicBrand;
  tokens: ThemeTokens;
  /** Capacités ordonnées, CTA primaire en tête. */
  capabilities: string[];
  /** Locale effectivement rendue (peut différer de celle demandée). */
  locale: string;
}

/**
 * Compose un DTO public résolu.
 *
 * `requestedLang` est la locale demandée (`?lang=`) : si la Brand ne la
 * publie pas, on retombe sur SA locale par défaut plutôt que sur `fr`. Une
 * enseigne dont la langue est l'arabe ne doit pas basculer en français parce
 * qu'un visiteur a demandé `?lang=xx`.
 */
export function resolveSurface(brand: PublicBrand, requestedLang?: string | null): ResolvedSurface {
  const requested = normalizeLocale(requestedLang);
  const published = brand.surface.locales.length > 0 ? brand.surface.locales : brand.locales;

  const locale =
    requested && published.includes(requested)
      ? requested
      : brand.surface.default_locale || brand.default_locale || brand.locale;

  const tokens = buildThemeTokens(brand.palette, brand.profile, directionFor(locale));

  return {
    brand: {
      ...brand,
      locale,
      direction: directionFor(locale),
    },
    tokens,
    capabilities: capabilityOrder(brand.profile),
    locale,
  };
}
