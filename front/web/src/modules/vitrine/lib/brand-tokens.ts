/**
 * Leopardo — tokens de marque officiels (issue #9194).
 *
 * Source de vérité UNIQUE des couleurs, typographies et règles d'usage de la
 * marque. Les valeurs sont alignées sur `tailwind.config.ts` (thème `brand`,
 * `emerald`, `cyan`, `surface`) et sur `LeopardoBrandMark.tsx` (emblème).
 *
 * Usage : importer ces tokens plutôt que réécrire des hexadécimaux dans les
 * composants. Un changement de marque se fait ICI, pas dans 40 fichiers.
 *
 * Guide complet : `docs/brand/BRAND_GUIDELINES.md`.
 */

/** Couleur de marque — échelle complète (teal, couleur primaire historique). */
export const BRAND_TEAL = {
  50: '#f0fdfa',
  100: '#ccfbf1',
  200: '#99f6e4',
  300: '#5eead4',
  400: '#2dd4bf',
  500: '#14b8a6',
  600: '#0d9488',
  700: '#0f766e',
  800: '#115e59',
  900: '#134e4a',
  950: '#042f2e',
} as const;

/** Vert émeraude — couleur d'action principale (CTA, succès). */
export const BRAND_EMERALD = {
  50: '#ecfdf5',
  100: '#d1fae5',
  200: '#a7f3d0',
  300: '#6ee7b7',
  400: '#34d399',
  500: '#10b981',
  600: '#059669',
  700: '#047857',
  800: '#065f46',
  900: '#064e3b',
  950: '#022c22',
} as const;

/** Cyan — couleur secondaire (accent, liens, dégradés). */
export const BRAND_CYAN = {
  50: '#ecfeff',
  100: '#cffafe',
  200: '#a5f3fc',
  300: '#67e8f9',
  400: '#22d3ee',
  500: '#06b6d4',
  600: '#0891b2',
  700: '#0e7490',
  800: '#155e75',
  900: '#164e63',
  950: '#083344',
} as const;

/** Ambre — accent chaud de l'emblème et du slogan « BUSINESS OS ». */
export const BRAND_AMBER = {
  300: '#fcd34d',
  400: '#fbbf24',
  500: '#f59e0b',
  600: '#d97706',
  700: '#b45309',
} as const;

/** Surfaces sombres (thème par défaut de l'application). */
export const BRAND_SURFACE = {
  /** Fond principal. */
  base: '#0b1326',
  /** Fond élevé (cartes, panneaux). */
  bright: '#31394d',
  /** Fond alternatif. */
  variant: '#2d3449',
  /** Teinte d'accent sur fond sombre. */
  tint: '#4edea3',
} as const;

/** Couleurs de texte sur surface sombre. */
export const BRAND_ON_SURFACE = {
  /** Texte principal. */
  base: '#dae2fd',
  /** Texte secondaire. */
  variant: '#bbcabf',
} as const;

/**
 * Dégradé officiel de l'emblème — ambre → émeraude → vert profond.
 * Utilisé pour la branche « T » du monogramme et les bordures luminescentes.
 */
export const BRAND_GRADIENT = {
  from: BRAND_AMBER[400],
  via: BRAND_EMERALD[400],
  to: BRAND_EMERALD[600],
  /** Forme CSS prête à l'emploi. */
  css: `linear-gradient(135deg, ${BRAND_AMBER[400]} 0%, ${BRAND_EMERALD[400]} 55%, ${BRAND_EMERALD[600]} 100%)`,
} as const;

/** Typographie officielle. */
export const BRAND_TYPOGRAPHY = {
  /** Titres et logotype — Inter, graisse 900. */
  display: {
    family: 'Inter, system-ui, sans-serif',
    weight: 900,
    letterSpacing: '-0.02em',
  },
  /** Corps de texte — Inter, graisse 400. */
  body: {
    family: 'Inter, system-ui, sans-serif',
    weight: 400,
    letterSpacing: '0',
  },
  /** Slogan et sur-titres — Inter, graisse 800, très espacé. */
  overline: {
    family: 'Inter, system-ui, sans-serif',
    weight: 800,
    letterSpacing: '0.22em',
    transform: 'uppercase' as const,
  },
  /** Code et données — JetBrains Mono. */
  mono: {
    family: 'JetBrains Mono, monospace',
    weight: 400,
  },
} as const;

/** Slogan officiel. */
export const BRAND_TAGLINE = 'BUSINESS OS';

/** Nom officiel de la marque (casse exacte). */
export const BRAND_NAME = 'Leopardo';

/**
 * Espace de sécurité de l'emblème, exprimé en fraction de sa hauteur.
 * Aucun élément (texte, bord de cadre, autre logo) ne doit entrer dans cette
 * zone : `0.25 × hauteur` sur les quatre côtés.
 */
export const BRAND_CLEAR_SPACE_RATIO = 0.25;

/** Chemins des assets officiels (relatifs à `public/`). */
export const BRAND_ASSETS = {
  /** Logo principal, fond clair. */
  logo: '/brand/leopardo-logo.svg',
  /** Logo principal, fond sombre. */
  logoDark: '/brand/leopardo-logo-dark.svg',
  /** Symbole seul (monogramme), transparent. */
  symbol: '/brand/leopardo-symbol.svg',
  /** Icône carrée à fond plein (favicon, app icon). */
  icon: '/brand/leopardo-icon.svg',
} as const;
