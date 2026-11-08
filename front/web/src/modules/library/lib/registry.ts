import { LIBRARY_RESOURCES } from '../data/catalog';
import type {
  LibraryFilters,
  LibraryResource,
  LibraryResourceStatus,
} from '../types';

/**
 * Registre de la bibliothèque — résolution et recherche.
 *
 * Fonctions PURES (aucun accès réseau, aucun état) : testées directement et
 * utilisées par les routes `/library` et `/library/[slug]`.
 *
 * Règle de visibilité : seules les ressources `published` sont exposées au
 * public. Les brouillons (`draft`), en attente (`pending`) et archivés
 * (`archived`) ne sortent JAMAIS du catalogue public.
 */

/** Statuts visibles publiquement. */
const PUBLIC_STATUSES: LibraryResourceStatus[] = ['published'];

/** Vrai si la ressource est visible publiquement. */
export function isPubliclyVisible(resource: LibraryResource): boolean {
  return PUBLIC_STATUSES.includes(resource.status);
}

/** Toutes les ressources publiées, dans l'ordre du catalogue. */
export function getPublishedResources(): LibraryResource[] {
  return LIBRARY_RESOURCES.filter(isPubliclyVisible);
}

/** Retourne une ressource publiée par slug, ou `null` si inconnue/non publiée. */
export function getResource(slug: string): LibraryResource | null {
  const resource = LIBRARY_RESOURCES.find((item) => item.slug === slug);
  if (!resource || !isPubliclyVisible(resource)) return null;
  return resource;
}

/** Tous les slugs publiés (utilisé par `generateStaticParams`). */
export function getAllResourceSlugs(): string[] {
  return getPublishedResources().map((resource) => resource.slug);
}

/** Ressources d'une catégorie, dans l'ordre du catalogue. */
export function getResourcesByCategory(category: string): LibraryResource[] {
  return getPublishedResources().filter((resource) => resource.category === category);
}

/** Ressources mises en avant (sélection éditoriale). */
export function getFeaturedResources(): LibraryResource[] {
  return getPublishedResources().filter((resource) => resource.featured);
}

/** Nouveautés. */
export function getNewResources(): LibraryResource[] {
  return getPublishedResources().filter((resource) => resource.isNew);
}

/** Ressources gratuites (lecture et/ou téléchargement libres). */
export function getFreeResources(): LibraryResource[] {
  return getPublishedResources().filter(
    (resource) => resource.accessModel === 'free' || resource.accessModel === 'read_only',
  );
}

/** Ressources similaires (même catégorie, hors ressource courante, max 3). */
export function getRelatedResources(slug: string, limit = 3): LibraryResource[] {
  const current = getResource(slug);
  if (!current) return [];
  return getPublishedResources()
    .filter((resource) => resource.category === current.category && resource.slug !== slug)
    .slice(0, limit);
}

/** Minuscules sans diacritiques (NFD + suppression des marques combinantes). */
function normalize(value: string): string {
  return value
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .trim();
}

/**
 * Recherche + filtres combinés sur le catalogue publié.
 *
 * La recherche porte sur le titre (slug), l'auteur, l'éditeur, les mots-clés
 * et l'extrait. Les filtres catégorie / langue / modèle d'accès / type de
 * document sont appliqués en plus. Une requête vide retourne tout le catalogue
 * publié (filtré).
 */
export function searchResources(filters: LibraryFilters = {}): LibraryResource[] {
  const needle = normalize(filters.query ?? '');
  const category = filters.category ?? 'all';
  const language = filters.language ?? 'all';
  const accessModel = filters.accessModel ?? 'all';
  const documentType = filters.documentType ?? 'all';

  return getPublishedResources().filter((resource) => {
    if (category !== 'all' && resource.category !== category) return false;
    if (language !== 'all' && resource.language !== language) return false;
    if (accessModel !== 'all' && resource.accessModel !== accessModel) return false;
    if (documentType !== 'all' && resource.documentType !== documentType) return false;
    if (filters.freeOnly && resource.accessModel !== 'free' && resource.accessModel !== 'read_only') {
      return false;
    }
    if (needle === '') return true;
    const haystack = normalize(
      [
        resource.slug,
        resource.author,
        resource.publisher ?? '',
        resource.excerpt ?? '',
        ...resource.keywords,
      ].join(' '),
    );
    return haystack.includes(needle);
  });
}

/** Langues distinctes présentes dans le catalogue publié (triées). */
export function getAvailableLanguages(): string[] {
  return [...new Set(getPublishedResources().map((resource) => resource.language))].sort();
}
