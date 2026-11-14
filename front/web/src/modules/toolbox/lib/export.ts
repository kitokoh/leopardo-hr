/**
 * Leopardo Toolbox — export d'un résultat en fichier texte.
 *
 * Un résultat calculé n'est utile que s'il peut sortir de la page : jusqu'ici
 * le visiteur pouvait le copier (presse-papiers) ou partager un lien, mais pas
 * l'emporter dans un fichier. Ce module comble ce manque.
 *
 * Contrat :
 * - **100 % côté client** : le fichier est construit en mémoire puis remis au
 *   navigateur via `Blob` + `URL.createObjectURL`. Aucun appel réseau, aucun
 *   endpoint backend, aucune donnée envoyée à un serveur.
 * - **Fonctions pures** pour la construction du contenu et du nom de fichier :
 *   testables directement, sans DOM.
 * - **Aucune donnée personnelle collectée** : seules les lignes déjà affichées
 *   à l'écran sont écrites.
 *
 * ⚠️ `downloadTextFile` touche au DOM : elle n'est appelable que dans un
 * navigateur (`typeof window !== 'undefined'`) et retourne `false` plutôt que
 * de lever une exception quand l'environnement ne le permet pas.
 */

/** Ligne de résultat exportée (même forme que `ResultRow` du panneau). */
export interface ExportRow {
  label: string;
  value: string;
}

/** Nom de fichier de repli quand l'URL ne porte aucun slug exploitable. */
const FALLBACK_FILENAME = 'leopardo-toolbox.txt';

/** Préfixe commun à tous les exports du module. */
const FILENAME_PREFIX = 'leopardo-toolbox';

/**
 * Construit le contenu texte d'un export.
 *
 * Format volontairement simple et lisible dans n'importe quel éditeur :
 *
 * ```
 * <titre>
 *
 * <libellé> : <valeur>
 * <libellé> : <valeur>
 *
 * <pied de page>
 * ```
 *
 * Les lignes vides sont ignorées (une ligne sans libellé ni valeur n'apporte
 * rien) et les valeurs sont écrites telles qu'affichées — donc déjà localisées
 * (séparateurs de milliers, devise, unités).
 *
 * @param title  Titre de la section de résultats (déjà localisé).
 * @param rows   Lignes affichées, dans l'ordre d'affichage.
 * @param footer Mention ajoutée en fin de fichier (avertissement, version…).
 */
export function buildExportText(title: string, rows: ExportRow[], footer?: string): string {
  const lines: string[] = [];

  const cleanTitle = title.trim();
  if (cleanTitle !== '') lines.push(cleanTitle);

  const body = rows
    .map((row) => ({ label: row.label.trim(), value: row.value.trim() }))
    .filter((row) => row.label !== '' || row.value !== '')
    .map((row) => (row.label === '' ? row.value : `${row.label} : ${row.value}`));

  if (body.length > 0) {
    if (lines.length > 0) lines.push('');
    lines.push(...body);
  }

  const cleanFooter = (footer ?? '').trim();
  if (cleanFooter !== '') {
    if (lines.length > 0) lines.push('');
    lines.push(cleanFooter);
  }

  return lines.join('\n');
}

/**
 * Déduit un nom de fichier sûr depuis le chemin de la page.
 *
 * `/toolbox/imc` → `leopardo-toolbox-imc.txt`
 * `/toolbox/cbm-poids-volumetrique` → `leopardo-toolbox-cbm-poids-volumetrique.txt`
 *
 * Le slug est réduit à `[a-z0-9-]` : un chemin inattendu (segment encodé,
 * caractère exotique, tentative de traversée `../`) ne peut donc pas produire
 * un nom de fichier dangereux. Un chemin sans slug exploitable retombe sur
 * `leopardo-toolbox.txt`.
 */
export function buildExportFilename(pathname: string): string {
  const segments = pathname
    .split('/')
    .map((segment) => segment.trim().toLowerCase())
    .filter((segment) => segment !== '');

  // Le dernier segment est le slug de l'outil ; `/toolbox` seul n'en a pas.
  const last = segments[segments.length - 1] ?? '';
  const slug = last === 'toolbox' ? '' : last.replace(/[^a-z0-9-]/g, '-').replace(/-+/g, '-').replace(/^-|-$/g, '');

  if (slug === '') return FALLBACK_FILENAME;
  return `${FILENAME_PREFIX}-${slug}.txt`;
}

/**
 * Déclenche le téléchargement d'un fichier texte dans le navigateur.
 *
 * Retourne `true` si le téléchargement a pu être déclenché, `false` sinon
 * (rendu serveur, API indisponible, exception du navigateur). L'appelant ne
 * doit donc jamais supposer un succès : c'est ce booléen qui pilote le retour
 * visuel.
 */
export function downloadTextFile(filename: string, content: string): boolean {
  if (typeof window === 'undefined') return false;
  if (typeof document === 'undefined') return false;
  if (typeof URL === 'undefined' || typeof URL.createObjectURL !== 'function') return false;

  let objectUrl: string | null = null;
  try {
    const blob = new Blob([content], { type: 'text/plain;charset=utf-8' });
    objectUrl = URL.createObjectURL(blob);

    const anchor = document.createElement('a');
    anchor.href = objectUrl;
    anchor.download = filename;
    anchor.rel = 'noopener';
    anchor.style.display = 'none';

    document.body.appendChild(anchor);
    anchor.click();
    document.body.removeChild(anchor);

    return true;
  } catch {
    return false;
  } finally {
    // Libération systématique : sans `revokeObjectURL`, le blob reste en
    // mémoire jusqu'au rechargement de la page.
    if (objectUrl !== null && typeof URL.revokeObjectURL === 'function') {
      URL.revokeObjectURL(objectUrl);
    }
  }
}
