import { TOOLS } from '../data/catalog';
import type { ToolCategoryId, ToolDefinition } from '../types';

/**
 * Registre du catalogue — résolution slug → définition.
 *
 * Fonctions PURES (aucun accès réseau, aucun état) : elles sont testées
 * directement et utilisées par les routes `/toolbox` et `/toolbox/[slug]`.
 *
 * Deux notions distinctes (décision #9157) :
 * - **résolution** (`getTool`) : un slug connu est toujours résolu, même si
 *   l'outil est désactivé — un lien partagé ne casse jamais ;
 * - **catalogue** (`getCatalogTools`, `searchTools`, `getAllToolSlugs`) : seuls
 *   les outils `enabled` (défaut `true`) sont exposés, donc indexés.
 */

/** Vrai si l'outil est actif dans le catalogue public (défaut : actif). */
export function isToolEnabled(tool: ToolDefinition): boolean {
  return tool.enabled !== false;
}

/** Outils actifs du catalogue, dans l'ordre de déclaration. */
export function getCatalogTools(): ToolDefinition[] {
  return TOOLS.filter(isToolEnabled);
}

/**
 * Retourne la définition d'un outil, ou `null` si le slug est inconnu.
 *
 * Résolution **indépendante** de `enabled` : un outil désactivé reste
 * accessible par son URL directe (pas de 404 sur un lien partagé).
 */
export function getTool(slug: string): ToolDefinition | null {
  return TOOLS.find((tool) => tool.slug === slug) ?? null;
}

/** Slugs des outils ACTIFS (utilisé par `generateStaticParams` et le sitemap). */
export function getAllToolSlugs(): string[] {
  return getCatalogTools().map((tool) => tool.slug);
}

/** Outils actifs d'une catégorie, dans l'ordre du catalogue. */
export function getToolsByCategory(category: ToolCategoryId): ToolDefinition[] {
  return getCatalogTools().filter((tool) => tool.category === category);
}

/**
 * Recherche plein-texte simple sur le slug et les mots-clés (insensible à la
 * casse et aux accents). Une requête vide retourne tout le catalogue actif.
 */
export function searchTools(query: string): ToolDefinition[] {
  const needle = normalize(query);
  const catalog = getCatalogTools();
  if (needle === '') return catalog;
  return catalog.filter((tool) => {
    const haystack = [tool.slug, ...tool.keywords].map(normalize).join(' ');
    return haystack.includes(needle);
  });
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
 * Outils suggérés après un outil donné — « outils similaires ».
 *
 * Ordre : d'abord les outils ACTIFS de la même catégorie (ordre du catalogue),
 * puis les autres outils actifs. L'outil courant est toujours exclu, et un slug
 * inconnu retourne une liste vide (pas de suggestion arbitraire).
 *
 * Fonction PURE : aucun accès réseau, testée directement.
 */
export function getRelatedTools(slug: string, limit = 3): ToolDefinition[] {
  const current = getTool(slug);
  if (!current) return [];

  const catalog = getCatalogTools().filter((tool) => tool.slug !== slug);
  const sameCategory = catalog.filter((tool) => tool.category === current.category);
  const others = catalog.filter((tool) => tool.category !== current.category);

  return [...sameCategory, ...others].slice(0, Math.max(0, limit));
}
