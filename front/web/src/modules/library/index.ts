/**
 * Leopardo Library — exports publics du module.
 *
 * Module frontend autonome de la vitrine publique : catalogue déclaratif,
 * logique pure testée, lecteur numérique, espace utilisateur. Aucune
 * dépendance aux modules métiers (RH, paiement, tenant) au premier périmètre.
 */

export * from './types';
export { LIBRARY_CATEGORIES, LIBRARY_RESOURCES } from './data/catalog';
export { LIBRARY_LOCALES, getLibraryCopy, type LibraryCopy, type LibraryLocale } from './data/copy';
export {
  getResource,
  getAllResourceSlugs,
  getPublishedResources,
  getResourcesByCategory,
  getFeaturedResources,
  getNewResources,
  getFreeResources,
  getRelatedResources,
  getAvailableLanguages,
  searchResources,
  isPubliclyVisible,
} from './lib/registry';
export {
  getAccessCapabilities,
  isPubliclyReadable,
  requiresAuthentication,
  type AccessContext,
  type AccessCapabilities,
} from './lib/access';
export {
  clampProgress,
  progressPercent,
  mergeProgress,
  loadProgress,
  saveProgress,
  getProgressFor,
  loadFavorites,
  toggleFavorite,
  type ReadingProgress,
} from './lib/progress';
