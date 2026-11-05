import { TOOLS } from '../data/catalog';
import type { ToolCategoryId, ToolDefinition } from '../types';

/**
 * Registre du catalogue — résolution slug → définition.
 *
 * Fonctions PURES (aucun accès réseau, aucun état) : elles sont testées
 * directement et utilisées par les routes `/toolbox` et `/toolbox/[slug]`.
 */

/** Retourne la définition d'un outil, ou `null` si le slug est inconnu. */
export function getTool(slug: string): ToolDefinition | null {
  return TOOLS.find((tool) => tool.slug === slug) ?? null;
}

/** Tous les slugs (utilisé par `generateStaticParams`). */
export function getAllToolSlugs(): string[] {
  return TOOLS.map((tool) => tool.slug);
}

/** Outils d'une catégorie, dans l'ordre du catalogue. */
export function getToolsByCategory(category: ToolCategoryId): ToolDefinition[] {
  return TOOLS.filter((tool) => tool.category === category);
}

/**
 * Recherche plein-texte simple sur le slug et les mots-clés (insensible à la
 * casse et aux accents). Une requête vide retourne tout le catalogue.
 */
export function searchTools(query: string): ToolDefinition[] {
  const needle = normalize(query);
  if (needle === '') return [...TOOLS];
  return TOOLS.filter((tool) => {
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
