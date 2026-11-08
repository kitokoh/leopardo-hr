import type { LibraryCopy } from '../data/copy';
import type { LibraryResource } from '../types';
import type { CatalogItem } from '../components/LibraryCatalog';

/**
 * Présentation — conversion d'une ressource en données sérialisables pour les
 * composants client. Fonctions PURES (testées) : aucune logique métier ici,
 * uniquement de la mise en forme localisée.
 */

/** Ton visuel d'un badge d'accès. */
export function accessTone(accessModel: LibraryResource['accessModel']): 'free' | 'paid' | 'private' {
  if (accessModel === 'free' || accessModel === 'read_only') return 'free';
  if (accessModel === 'private') return 'private';
  return 'paid';
}

/** Libellé localisé du modèle d'accès. */
export function accessLabel(resource: LibraryResource, copy: LibraryCopy): string {
  return copy.access[resource.accessModel];
}

/** Libellé localisé du type de document. */
export function typeLabel(resource: LibraryResource, copy: LibraryCopy): string {
  return copy.types[resource.documentType];
}

/** Libellé localisé de la catégorie. */
export function categoryLabel(resource: LibraryResource, copy: LibraryCopy): string {
  return copy.categories[resource.category] ?? resource.category;
}

/** Convertit une ressource en item de catalogue sérialisable. */
export function toCatalogItem(resource: LibraryResource, copy: LibraryCopy): CatalogItem {
  return {
    slug: resource.slug,
    title: resource.slug,
    author: resource.author,
    category: resource.category,
    categoryLabel: categoryLabel(resource, copy),
    language: resource.language,
    accessModel: resource.accessModel,
    accessLabel: accessLabel(resource, copy),
    accessTone: accessTone(resource.accessModel),
    documentType: resource.documentType,
    keywords: resource.keywords,
  };
}
