/**
 * Portail grand public — bounded context du chrome et de l'accueil.
 *
 * ## Pourquoi ce bounded context existe
 *
 * Avant #9633, `modules/public-portal` vivait à côté de `public-surfaces` alors
 * qu'ADR-0034 fait de `public-surfaces` la norme du front grand public. Le
 * portail n'est pas une verticale : c'est la **coquille commune** que toutes
 * les verticales partagent (en-tête, pied de page, navigation basse, thème,
 * recherche d'accueil, annuaire générique).
 *
 * Il est donc ici un bounded context à part entière, mais **sans identité de
 * verticale** : il ne figure pas dans `SURFACE_VERTICALS` et n'a pas de
 * `identity/`. Sa palette est celle du portail (émeraude), pas celle d'une
 * verticale.
 *
 * ## Structure
 *
 * ```
 * portal/
 *   components/   chrome (layout, nav, bottom nav), accueil, annuaire générique
 *   data/         copie i18n, tokens de design, catégories de découverte
 *   __tests__/    gardes de cohérence chrome ↔ routes
 * ```
 *
 * ## Ce que ce module ne fait pas
 *
 * Il ne porte aucune logique métier de verticale : les annuaires sont alimentés
 * par `GET /public/discover`, et chaque verticale garde son propre domaine.
 */

export { default as PublicPortalLayout } from './components/PublicPortalLayout';
export { PublicPortalNav } from './components/PublicPortalNav';
export { PublicBottomNav, type PublicTabKey } from './components/PublicBottomNav';
export { HomeSearch, type HomeSearchState } from './components/HomeSearch';
export { PublicResumeSearch, buildResumeHref, describeResumeQuery } from './components/PublicResumeSearch';
export { PublicNearbyDiscover } from './components/PublicNearbyDiscover';
export { default as PublicDirectoryExplorer } from './components/PublicDirectoryExplorer';
export { default as LeaSection } from './components/LeaSection';
export { ThemeToggle, THEME_SCRIPT, type ThemeMode } from './components/ThemeToggle';
export {
  buildDirectoryMetadata,
  buildDirectoryPage,
  type DirectoryType,
  type PublicDirectoryPageConfig,
} from './components/buildDirectoryPage';

export {
  getPublicPortalCopy,
  publicTypeLabel,
  type PublicPortalCopy,
  type PublicPortalLocale,
} from './data/copy';
export { getPublicHomeCopy, type PublicHomeCopy } from './data/home-copy';
export { getPublicAccountCopy, type PublicAccountCopy } from './data/account-copy';
export { getPublicLeaCopy, type PublicLeaCopy } from './data/lea-copy';
export {
  getPublicDiscoveryCategories,
  selectDisplayableCategories,
  type PublicDiscoveryCategory,
} from './data/discovery-categories';
export { PUBLIC_CARD, PUBLIC_TOUCH_TARGET } from './data/design-tokens';
