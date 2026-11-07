/**
 * Leopardo Toolbox — types du catalogue.
 *
 * Le catalogue est DÉCLARATIF : il ne contient que des métadonnées (slug,
 * catégorie, mots-clés, avertissements, références). Aucune formule, aucun
 * calcul : la logique vit dans `tools/<slug>/logic.ts` (fonctions pures
 * testées). Cette séparation est la règle structurante du module.
 */

export type ToolCategoryId = 'calcul' | 'conversion' | 'finance' | 'sante' | 'travail';

export type ToolIconName =
  | 'calculator'
  | 'arrow-left-right'
  | 'receipt'
  | 'heart-pulse'
  | 'briefcase'
  | 'percent';

export interface ToolCategory {
  id: ToolCategoryId;
  icon: ToolIconName;
}

/**
 * Avertissements normalisés. `estimate` = résultat indicatif ; `theoretical` =
 * résultat théorique (jamais une validation de sécurité) ; `notAdvice` = ne
 * constitue pas un conseil professionnel.
 */
export type ToolWarningId = 'estimate' | 'theoretical' | 'notAdvice';

export interface ToolReference {
  /** Clé i18n du libellé de la référence. */
  labelKey: string;
  url: string;
}

export interface ToolDefinition {
  /** Identifiant d'URL, unique, kebab-case. */
  slug: string;
  category: ToolCategoryId;
  icon: ToolIconName;
  /** Version sémantique du CALCUL (à incrémenter si une formule change). */
  version: string;
  /** Mots-clés de recherche (catalogue) — non traduits, normalisés en minuscules. */
  keywords: string[];
  warnings: ToolWarningId[];
  references: ToolReference[];
}
