/**
 * Domaine public — annuaire Retail (Marché).
 *
 * Une **entrée d'annuaire** est ce qu'un visiteur voit dans la liste des
 * boutiques : de quoi décider d'entrer ou non. Ce n'est pas la fiche complète
 * (`Shop`), qui porte le catalogue et la note.
 *
 * Aucune E/S, aucun import de framework.
 */

/** Une boutique telle qu'elle apparaît dans l'annuaire. */
export interface ShopDirectoryEntry {
  readonly slug: string;
  readonly name: string;
  readonly city: string | null;
  readonly description: string | null;
  /** Catégorie publiée par le provider (`null` si non déclarée). */
  readonly category: string | null;
}

/**
 * Ligne de métadonnées d'une carte d'annuaire.
 *
 * Règle du domaine : on n'affiche jamais un séparateur orphelin. Une boutique
 * sans ville ni catégorie n'affiche pas « · » — elle n'affiche rien, et le
 * composant retombe sur le libellé de verticale.
 */
export function shopDirectoryMeta(entry: ShopDirectoryEntry): string | null {
  const parts = [entry.city, entry.category].filter(
    (part): part is string => typeof part === 'string' && part.trim() !== '',
  );

  return parts.length === 0 ? null : parts.join(' · ');
}
