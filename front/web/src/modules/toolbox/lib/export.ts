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
 * Dérive le nom du fichier CSV depuis le nom du fichier texte.
 *
 * Une seule source de vérité pour le slug : on ne réimplémente pas la
 * réduction `[a-z0-9-]` ici, on remplace l'extension. `leopardo-toolbox-tva.txt`
 * → `leopardo-toolbox-tva.csv`.
 */
export function buildCsvFilename(textFilename: string): string {
  return textFilename.replace(/\.txt$/, '.csv');
}

/** Séparateur de colonnes par défaut (un tableur français attend le point-virgule). */
export const CSV_DELIMITER = ';';

/**
 * Caractères qui, en tête de cellule, font interpréter la valeur comme une
 * FORMULE par un tableur (injection CSV / « formula injection »).
 *
 * Une cellule commençant par `=`, `+`, `-` ou `@` est exécutée par Excel et
 * LibreOffice. Un résultat d'outil est aujourd'hui toujours numérique, mais
 * `buildCsv` est une fonction PUBLIQUE du module : la règle est appliquée ici,
 * une fois, pour qu'aucun appelant futur ne puisse l'oublier.
 */
const FORMULA_PREFIXES = ['=', '+', '-', '@', '\t', '\r'];

/**
 * Neutralise une cellule potentiellement dangereuse.
 *
 * Convention retenue : préfixer d'une apostrophe, ce qui force le tableur à
 * traiter la valeur comme du TEXTE tout en l'affichant telle quelle. On ne
 * supprime ni ne modifie jamais la valeur d'origine.
 */
function neutralizeFormula(value: string): string {
  if (value === '') return value;
  return FORMULA_PREFIXES.includes(value[0]) ? `'${value}` : value;
}

/**
 * Échappe une cellule selon RFC 4180.
 *
 * Une cellule est entourée de guillemets si elle contient le séparateur, un
 * guillemet ou un retour à la ligne ; les guillemets internes sont doublés.
 * Les cellules sans caractère spécial restent nues, pour un fichier lisible
 * dans un éditeur de texte.
 */
export function escapeCsvCell(value: string, delimiter = CSV_DELIMITER): string {
  const raw = neutralizeFormula(value ?? '');
  const needsQuotes =
    raw.includes(delimiter) || raw.includes('"') || raw.includes('\n') || raw.includes('\r');

  if (!needsQuotes) return raw;
  return `"${raw.replace(/"/g, '""')}"`;
}

/**
 * Construit le contenu CSV d'un tableau (RFC 4180).
 *
 * Format : une ligne par entrée, colonnes séparées par `delimiter`. Le fichier
 * n'embarque **pas** de BOM : celui-ci est ajouté par `downloadCsvFile`, car
 * il relève du transport (Excel a besoin d'un BOM UTF-8 pour lire les accents),
 * pas du contenu logique — et un BOM dans une chaîne testée serait invisible
 * mais présent.
 *
 * Fonction PURE : testable sans DOM, aucun accès réseau.
 *
 * @param rows      Lignes du tableau ; chaque ligne est une liste de cellules.
 * @param delimiter Séparateur de colonnes (`;` par défaut).
 */
export function buildCsv(rows: string[][], delimiter = CSV_DELIMITER): string {
  if (!Array.isArray(rows) || rows.length === 0) return '';
  return rows
    .map((row) => (Array.isArray(row) ? row : []).map((cell) => escapeCsvCell(String(cell ?? ''), delimiter)).join(delimiter))
    .join('\r\n');
}

/** BOM UTF-8 — indispensable pour que les accents survivent à l'ouverture dans Excel. */
export const UTF8_BOM = '\uFEFF';

/**
 * Déclenche le téléchargement d'un contenu CSV dans le navigateur.
 *
 * Même contrat que `downloadTextFile` : retourne un BOOLÉEN réel, jamais un
 * succès supposé, et n'échoue pas quand l'environnement ne le permet pas.
 * Le type MIME `text/csv` et le BOM distinguent ce fichier d'un export texte.
 */
export function downloadCsvFile(filename: string, content: string): boolean {
  return downloadFile(filename, `${UTF8_BOM}${content}`, 'text/csv;charset=utf-8');
}

/**
 * Déclenche le téléchargement d'un contenu dans le navigateur (socle commun).
 *
 * Retourne `true` si le téléchargement a pu être déclenché, `false` sinon
 * (rendu serveur, API indisponible, exception du navigateur). L'appelant ne
 * doit donc jamais supposer un succès : c'est ce booléen qui pilote le retour
 * visuel.
 *
 * Le type MIME est un paramètre : l'export texte et l'export CSV partagent ce
 * socle et ne diffèrent que par le type et le BOM — jamais par la mécanique.
 */
function downloadFile(filename: string, content: string, mimeType: string): boolean {
  if (typeof window === 'undefined') return false;
  if (typeof document === 'undefined') return false;
  if (typeof URL === 'undefined' || typeof URL.createObjectURL !== 'function') return false;

  let objectUrl: string | null = null;
  try {
    const blob = new Blob([content], { type: mimeType });
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

/**
 * Déclenche le téléchargement d'un fichier texte dans le navigateur.
 *
 * Retourne `true` si le téléchargement a pu être déclenché, `false` sinon
 * (rendu serveur, API indisponible, exception du navigateur). L'appelant ne
 * doit donc jamais supposer un succès : c'est ce booléen qui pilote le retour
 * visuel.
 */
export function downloadTextFile(filename: string, content: string): boolean {
  return downloadFile(filename, content, 'text/plain;charset=utf-8');
}
