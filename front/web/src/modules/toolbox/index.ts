/**
 * Leopardo Toolbox — point d'entrée public du module.
 *
 * Module AUTONOME de la vitrine : calculs 100 % côté client, aucun appel API,
 * aucune dépendance au Business OS. Voir `docs/leopardo-toolbox/`.
 */

export { TOOLS, TOOL_CATEGORIES } from './data/catalog';
export { getToolboxCopy, getToolCopy, TOOLBOX_LOCALES } from './data/copy';
export type { ToolboxLocale, ToolboxCopy, ToolCopy } from './data/copy';
export { getTool, getToolsByCategory, getAllToolSlugs, searchTools } from './lib/registry';
export type { ToolDefinition, ToolCategory, ToolCategoryId, ToolWarningId } from './types';
