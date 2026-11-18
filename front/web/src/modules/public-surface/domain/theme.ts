/**
 * #9580 (BC-41 BRANDING) — dérivation du thème de surface.
 *
 * ## Fonction PURE, et pourquoi c'est un choix d'architecture
 *
 * `buildThemeTokens()` ne lit rien : elle reçoit une palette et un profil, et
 * rend un jeu de variables CSS. C'est testable sans réseau, sans DOM et sans
 * React — donc les cas limites (couleur invalide, densité inconnue, profil qui
 * ne déclare pas de thème) sont couverts en millisecondes.
 *
 * L'alternative — calculer le thème dans un composant, à partir d'un `fetch` —
 * est exactement ce que l'audit a relevé : une règle métier (le contraste, la
 * densité) inaccessible au test et dupliquée entre desktop et mobile.
 *
 * ## Injection par variables CSS, pas par styles inline
 *
 * Le thème sort sous forme de variables (`--brand-primary`, `--brand-radius`…)
 * posées une fois sur le conteneur racine. Les composants consomment ensuite
 * `var(--brand-primary)`. Si chaque composant recevait `primary` en prop pour
 * le mettre en `style={{...}}`, ajouter une teinte demain demanderait de
 * toucher tous les composants — et le mode sombre deviendrait impossible.
 */

import type { BrandPalette, SurfaceProfile } from './brand';

/** Densité d'affichage — registre fermé côté API, validé ici aussi. */
const DENSITIES = ['compact', 'comfortable', 'spacious'] as const;

type Density = (typeof DENSITIES)[number];

/** Pas vertical par densité, en `rem`. */
const DENSITY_STEP: Record<Density, string> = {
  compact: '1.5rem',
  comfortable: '2.5rem',
  spacious: '4rem',
};

/** Rayon par défaut si le profil n'en déclare pas. */
const DEFAULT_RADIUS = '0.5rem';

/** Police par défaut si le profil n'en déclare pas. */
const DEFAULT_FONT = 'system-ui, -apple-system, "Segoe UI", sans-serif';

export interface ThemeTokens {
  /** Variables CSS prêtes à poser sur le conteneur racine. */
  cssVariables: Record<string, string>;
  /** Valeur de l'attribut `data-density` (utile pour les media queries CSS). */
  density: Density;
  /** Attribut `dir` pour les architectures RTL. */
  direction: 'ltr' | 'rtl';
}

function isDensity(value: unknown): value is Density {
  return typeof value === 'string' && (DENSITIES as readonly string[]).includes(value);
}

function asString(value: unknown): string | null {
  return typeof value === 'string' && value.trim() !== '' ? value.trim() : null;
}

/**
 * Construit les tokens de thème.
 *
 * `direction` est passé séparément et non déduit du profil : le sens
 * d'écriture est une propriété de la LOCALE rendue, pas de la verticale. Une
 * même Brand restaurant s'affiche en LTR en français et en RTL en arabe.
 */
export function buildThemeTokens(
  palette: BrandPalette,
  profile: SurfaceProfile,
  direction: 'ltr' | 'rtl' = 'ltr',
): ThemeTokens {
  const theme = profile.theme ?? {};
  const radius = asString(theme.radius) ?? DEFAULT_RADIUS;
  const fontFamily = asString(theme.font_family) ?? DEFAULT_FONT;
  const density: Density = isDensity(theme.density) ? theme.density : 'comfortable';

  return {
    density,
    direction,
    cssVariables: {
      '--brand-primary': palette.primary,
      '--brand-accent': palette.accent,
      '--brand-surface': palette.surface,
      // Calculé côté serveur (WCAG). Le front ne le recalcule pas : deux
      // implémentations de la même formule finiraient par diverger.
      '--brand-on-primary': palette.on_primary,
      '--brand-radius': radius,
      '--brand-font': fontFamily,
      '--brand-section-gap': DENSITY_STEP[density],
    },
  };
}

/**
 * Sérialise les variables en chaîne `style` utilisable par React.
 *
 * React accepte un objet `style` mais pas des clés `--x` en TypeScript typé
 * sans cast. Une chaîne passée à `style` (ou à `dangerouslySetInnerHTML` sur
 * `<style>`) évite le cast et garde les variables groupées à un seul endroit.
 */
export function themeVariablesToStyle(tokens: ThemeTokens): string {
  return Object.entries(tokens.cssVariables)
    .map(([key, value]) => `${key}: ${value}`)
    .join('; ');
}

/** Les capacités à afficher, dans l'ordre de pertinence du profil. */
export function capabilityOrder(profile: SurfaceProfile): string[] {
  const primary = profile.primary_cta;

  if (!primary) {
    return [...profile.capabilities];
  }

  return [primary, ...profile.capabilities.filter((capability) => capability !== primary)];
}
