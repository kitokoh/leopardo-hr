/**
 * #9580 (BC-41 BRANDING) — API publique du module `public-surface`.
 *
 * ## Pourquoi un index explicite
 *
 * Le module expose sa surface consommable, pas son arborescence. Une page
 * importe `@/modules/public-surface`, jamais un chemin interne profond : le
 * jour où l'implémentation se réorganise, seul cet index change.
 *
 * On n'exporte PAS `infrastructure/` : aucun consommateur extérieur au module
 * ne doit appeler l'API directement. Le seul point d'entrée pour la donnée est
 * `loadSurfaceBySlug()` — c'est ce qui garantit que la validation et la
 * composition s'appliquent toujours.
 */

// ── Domaine ───────────────────────────────────────────────────────────────
export {
  directionFor,
  normalizeLocale,
  parsePublicBrand,
  parsePalette,
  SUPPORTED_LOCALES,
} from './domain/brand';

export type {
  BrandIdentity,
  BrandPalette,
  BrandSurface,
  Direction,
  PublicBrand,
  SupportedLocale,
  SurfaceCapability,
  SurfaceProfile,
} from './domain/brand';

export { buildThemeTokens, capabilityOrder, themeVariablesToStyle } from './domain/theme';
export type { ThemeTokens } from './domain/theme';

// ── Application ───────────────────────────────────────────────────────────
export { resolveSurface } from './application/resolve-surface';
export type { ResolvedSurface } from './application/resolve-surface';

export { loadSurfaceBySlug, loadSurfaceByHost } from './application/load-surface';
export type { LoadSurfaceOptions, SurfaceLoader } from './application/load-surface';

// ── Composants ────────────────────────────────────────────────────────────
export { PublicSurfaceShell } from './components/PublicSurfaceShell';
export { SurfaceHero } from './components/SurfaceHero';
export { SurfaceCapabilityBar } from './components/SurfaceCapabilityBar';
export {
  SurfaceBlocks,
  hasSurfaceBlockRenderer,
  registerSurfaceBlockRenderers,
} from './components/SurfaceBlocks';
export type { SurfaceBlockRenderer } from './components/SurfaceBlocks';
