/**
 * Domaine public — verticale Mobilité (VTC / taxi).
 *
 * Un **opérateur** vu par le grand public : nom commercial, ville d'ancrage,
 * pays, devise. Aucune E/S.
 *
 * ## Ce que ce modèle ne porte PAS — et pourquoi
 *
 * - **Aucun prix.** La grille tarifaire d'un opérateur est interne au tenant.
 *   Un devis s'obtient par le contrat public dédié
 *   (`POST /public/vtc/marketplace/estimate`), jamais depuis une fiche.
 * - **Aucune position, aucun véhicule, aucun chauffeur.** La proximité VTC est
 *   celle des chauffeurs disponibles en dispatch interne : l'exposer dans un
 *   annuaire public serait une fuite opérationnelle.
 * - **Aucune note.** `VtcDiscoverableProvider` ne publie pas de `rating` (le
 *   DTO public est strict) : afficher « 0,0 » ou masquer la ligne serait deux
 *   mensonges. La règle du domaine est donc `null`, jamais zéro.
 */

/** Un opérateur tel que la vitrine le présente. */
export interface MobilityOperator {
  readonly slug: string;
  readonly name: string;
  readonly city: string | null;
  readonly country: string | null;
  readonly currency: string | null;
}

/**
 * Ligne de métadonnées : ville et pays, sans séparateur orphelin.
 *
 * `Nantes · ` (virgule pendante) est le défaut le plus courant d'un annuaire
 * alimenté par une API partielle. `null` quand rien n'est connu : la carte
 * affiche alors son libellé générique plutôt qu'une ponctuation seule.
 */
export function operatorMeta(operator: MobilityOperator): string | null {
  const parts = [operator.city, operator.country]
    .map((value) => (typeof value === 'string' ? value.trim() : ''))
    .filter((value) => value !== '');

  return parts.length === 0 ? null : parts.join(' · ');
}

/**
 * Libellé de devise : `null` si absente.
 *
 * Une devise vide ou un code d'une lettre n'est pas une devise — afficher
 * « » ou « E » à l'écran serait pire que de n'afficher aucune information.
 */
export function currencyLabel(operator: MobilityOperator): string | null {
  const code = operator.currency?.trim().toUpperCase() ?? '';

  if (!/^[A-Z]{3}$/.test(code)) {
    return null;
  }

  return code;
}

/**
 * Vrai si l'opérateur peut être contacté depuis sa fiche.
 *
 * `VtcDiscoverableProvider::supportedActions()` ne déclare que `contact` :
 * la course se demande par l'endpoint public dédié, pas par un bouton
 * générique. La vitrine ne doit donc jamais promettre une réservation
 * immédiate qu'aucun service ne servirait.
 */
export function isContactable(operator: MobilityOperator): boolean {
  return operator.slug.trim() !== '';
}

/**
 * Type canonique d'action servi par la verticale.
 *
 * Exposé comme constante pour que l'écran de friction (« pourquoi pas de
 * bouton Réserver ? ») puisse être vérifié par un test plutôt que par la
 * mémoire du développeur.
 */
export const MOBILITY_ACTIONS = ['contact'] as const;

export type MobilityAction = (typeof MOBILITY_ACTIONS)[number];
