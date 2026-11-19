/**
 * Leopardo Public — DESIGN TOKENS partagés Web ↔ Flutter (Couloir 16, M1).
 *
 * ## Pourquoi ce fichier existe
 *
 * Le portail grand public est rendu par DEUX clients : le Web (`front/web`) et
 * l'app Flutter (`front/mobile_apps/leopardo_public`). Sans source unique, les
 * deux dérivent — c'est exactement ce qui s'était produit avant #9359, où le
 * mobile utilisait `TenantTheme.apply(ThemeData.light(), null)` et n'avait donc
 * aucune identité visuelle.
 *
 * Ce fichier est la **référence normative**. Il ne contient aucune valeur
 * nouvelle : il documente et nomme ce qui existe déjà dans
 * `front/web/tailwind.config.ts` (palette emerald/slate) et dans
 * `leopardo_core/lib/core/theme/app_colors.dart` (tokens `public*`).
 *
 * ## Règle d'or
 *
 * Une valeur ne se duplique pas. Si un token manque, on l'ajoute ICI d'abord,
 * puis on le répercute dans les deux implémentations. La garde
 * `dev-hub/tools/validate-mobile-color-tokens.ps1` interdit déjà tout littéral
 * `Color(0x…)` hors de `app_colors.dart` côté Flutter ; côté Web, les classes
 * Tailwind jouent ce rôle.
 *
 * ## Contraintes non négociables (cahier des charges Couloir 16)
 *
 * - Un seul accent principal par écran (emerald).
 * - Bordures fines, pas d'ombres décoratives excessives.
 * - Cartes : rayon 12 px.
 * - Contraste AA minimum.
 * - Texte minimum 11 px.
 * - Cibles tactiles minimum 44 px.
 */

/** Palette — miroir exact de Tailwind (emerald / slate). */
export const PUBLIC_COLORS = {
  /** Accent principal — CTA du mode clair. */
  accent: '#059669', // emerald-600
  /** Accent sur fond sombre. */
  accentDark: '#10B981', // emerald-500
  /** Accent en mode sombre (primaire). */
  accentOnDark: '#34D399', // emerald-400
  /** Liens et texte d'accent (mode clair). */
  accentText: '#047857', // emerald-700
  /** Fond des puces et badges clairs. */
  accentSoft: '#ECFDF5', // emerald-50
  /** Fond des icônes de catégorie (mode clair). */
  accentSoftAlt: '#D1FAE5', // emerald-100

  /** Fond du mode clair. */
  surface: '#F8FAFC', // slate-50
  /** Surfaces alternées (mode clair). */
  surfaceAlt: '#F1F5F9', // slate-100
  /** Bordures (mode clair). */
  border: '#E2E8F0', // slate-200
  /** Texte secondaire (mode clair). */
  textMuted: '#64748B', // slate-500
  /** Corps de texte (mode clair). */
  textBody: '#475569', // slate-600
  /** Texte principal (mode clair). */
  text: '#0F172A', // slate-900

  /** Fond du mode sombre. */
  surfaceDark: '#020617', // slate-950
  /** Surface des cartes en mode sombre. */
  cardDark: '#0B1220',
  /** Surface alternée en mode sombre. */
  surfaceDarkAlt: '#1E293B', // slate-800
  /** Bordure en mode sombre. */
  borderDark: '#334155', // slate-700
  /** Texte secondaire (mode sombre). */
  textMutedDark: '#94A3B8', // slate-400
  /** Texte principal en mode sombre. */
  textDark: '#F1F5F9', // slate-100

  /** Erreur (mode clair). */
  error: '#DC2626', // red-600
  /** Erreur (mode sombre). */
  errorDark: '#F87171', // red-400
} as const;

/**
 * Typographie.
 *
 * Le Web charge Inter via `next/font` ; le mobile garde la police système
 * (aucun asset à embarquer, donc aucun coût de démarrage sur Android
 * d'entrée de gamme). Les DEUX alignent en revanche graisses et interlignes.
 */
export const PUBLIC_TYPE = {
  family: 'Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif',
  /** Échelle en px — le minimum absolu est 11. */
  size: {
    micro: 11,
    caption: 12,
    body: 14,
    bodyLg: 16,
    title: 20,
    heading: 24,
    display: 32,
    hero: 40,
  },
  weight: {
    regular: 400,
    medium: 500,
    semibold: 600,
    bold: 700,
    black: 900,
  },
  /** Interligne (multiplicateur). */
  leading: {
    tight: 1.1,
    snug: 1.25,
    normal: 1.5,
    relaxed: 1.65,
  },
} as const;

/** Espacements — échelle de 4 px, identique aux classes Tailwind. */
export const PUBLIC_SPACE = {
  xxs: 2,
  xs: 4,
  sm: 8,
  md: 12,
  lg: 16,
  xl: 24,
  xxl: 32,
  section: 48,
  hero: 64,
} as const;

/** Rayons — `card` est imposé à 12 px par le cahier des charges. */
export const PUBLIC_RADIUS = {
  sm: 6,
  md: 8,
  card: 12,
  lg: 16,
  pill: 999,
} as const;

/** Dimensions d'icônes. */
export const PUBLIC_ICON = {
  sm: 14,
  md: 16,
  lg: 20,
  xl: 24,
  category: 22,
} as const;

/** Dimensions de cartes — partagées pour que les skeletons ne bougent pas. */
export const PUBLIC_CARD = {
  /** Hauteur d'une carte de commerce en liste (mobile). */
  rowHeight: 96,
  /** Hauteur d'une carte de catégorie. */
  categoryHeight: 88,
  /** Largeur minimale d'une carte de catégorie en grille. */
  categoryMinWidth: 104,
  /** Hauteur de la barre de recherche. */
  searchHeight: 52,
  /** Hauteur de la navigation basse mobile. */
  bottomNavHeight: 60,
} as const;

/**
 * Cible tactile minimale — 44 px (WCAG 2.5.5 / Apple HIG).
 * Toute zone interactive plus petite est un défaut d'accessibilité.
 */
export const PUBLIC_TOUCH_TARGET = 44;

/** Points de rupture — miroirs de Tailwind. */
export const PUBLIC_BREAKPOINT = {
  sm: 640,
  md: 768,
  lg: 1024,
  xl: 1280,
} as const;

/**
 * États des composants.
 *
 * `disabled` n'est PAS un gris arbitraire : il conserve un contraste lisible
 * (AA) tout en signalant l'inactivité — un bouton désactivé illisible est un
 * défaut, pas une économie.
 */
export const PUBLIC_STATE = {
  hover: { opacity: 0.9 },
  active: { scale: 0.98 },
  disabled: { opacity: 0.5 },
  focusRing: { width: 2, offset: 2 },
} as const;

/** Durées d'animation (ms) — courtes, et supprimées sous `prefers-reduced-motion`. */
export const PUBLIC_MOTION = {
  fast: 120,
  base: 200,
  slow: 320,
} as const;
