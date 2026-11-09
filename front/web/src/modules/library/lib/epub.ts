/**
 * Leopardo Library — extraction du contenu d'un EPUB (cœur PUR, testable).
 *
 * Un EPUB est une archive ZIP contenant :
 *   - `META-INF/container.xml` → pointe le manifeste OPF ;
 *   - un fichier `.opf` → `<manifest>` (liste des ressources) + `<spine>`
 *     (ordre de lecture) ;
 *   - des documents XHTML, un par chapitre.
 *
 * Ce module ne fait AUCUN rendu : il transforme les octets d'un EPUB en une
 * liste de chapitres texte. Le rendu (navigation, zoom, recherche) vit dans
 * `components/EpubReader.tsx`, exactement comme pour le PDF.
 *
 * ⚠️ Aucune dépendance lourde : `fflate` (≈8 Ko, déjà présent dans l'arbre de
 * dépendances) fait le dézippage. Pas d'`epub.js`, qui embarque son propre
 * moteur de rendu et pèserait plus que tout le reste du module.
 *
 * ⚠️ SÉCURITÉ : le contenu XHTML n'est JAMAIS injecté dans le DOM. On en
 * extrait du TEXTE brut, rendu comme du texte. Un EPUB hostile ne peut donc
 * pas exécuter de script dans la page de la vitrine.
 */

import { unzipSync, strFromU8 } from 'fflate';

/** Un chapitre lisible. */
export interface EpubChapter {
  /** Titre du chapitre (balise `title`/`h1`, sinon nom de fichier). */
  title: string;
  /** Texte brut du chapitre, paragraphes séparés par `\n\n`. */
  text: string;
}

/** Résultat de l'extraction. */
export interface EpubContent {
  /** Titre du livre (métadonnée `dc:title`), si présente. */
  title: string | null;
  /** Chapitres dans l'ordre de lecture (spine). */
  chapters: EpubChapter[];
}

/** Erreur d'extraction — message technique, jamais affiché tel quel. */
export class EpubParseError extends Error {
  constructor(message: string) {
    super(message);
    this.name = 'EpubParseError';
  }
}

/** Décode une entrée ZIP en texte UTF-8. */
function readText(files: Record<string, Uint8Array>, path: string): string | null {
  const entry = files[path];
  if (!entry) return null;
  try {
    return strFromU8(entry);
  } catch {
    return null;
  }
}

/** Résout un `href` relatif par rapport au dossier du manifeste OPF. */
export function resolveHref(baseDir: string, href: string): string {
  const clean = href.split('#')[0];
  if (clean === '') return '';
  if (clean.startsWith('/')) return clean.slice(1);

  const segments = `${baseDir}/${clean}`.split('/');
  const stack: string[] = [];

  for (const segment of segments) {
    if (segment === '' || segment === '.') continue;
    if (segment === '..') {
      stack.pop();
      continue;
    }
    stack.push(segment);
  }

  return stack.join('/');
}

/** Décode les entités HTML les plus courantes (pas de DOM requis). */
export function decodeEntities(value: string): string {
  const named: Record<string, string> = {
    amp: '&',
    lt: '<',
    gt: '>',
    quot: '"',
    apos: "'",
    nbsp: ' ',
    laquo: '«',
    raquo: '»',
    hellip: '…',
    mdash: '—',
    ndash: '–',
    rsquo: '’',
    lsquo: '‘',
    ldquo: '“',
    rdquo: '”',
  };

  return value
    .replace(/&#x([0-9a-fA-F]+);/g, (_match, hex: string) => {
      const code = Number.parseInt(hex, 16);
      return Number.isFinite(code) ? String.fromCodePoint(code) : '';
    })
    .replace(/&#(\d+);/g, (_match, dec: string) => {
      const code = Number.parseInt(dec, 10);
      return Number.isFinite(code) ? String.fromCodePoint(code) : '';
    })
    .replace(/&([a-zA-Z]+);/g, (match, name: string) => named[name] ?? match);
}

/**
 * Convertit un document XHTML en texte brut structuré.
 *
 * Les blocs (`p`, `div`, `br`, titres, `li`) deviennent des séparateurs de
 * paragraphe ; le reste est aplati. Aucune balise n'est conservée.
 */
export function htmlToText(html: string): string {
  const withoutHead = html
    .replace(/<head[\s\S]*?<\/head>/gi, ' ')
    .replace(/<script[\s\S]*?<\/script>/gi, ' ')
    .replace(/<style[\s\S]*?<\/style>/gi, ' ')
    .replace(/<!--[\s\S]*?-->/g, ' ');

  const withBreaks = withoutHead
    .replace(/<\/(p|div|section|article|li|h[1-6]|blockquote|tr)>/gi, '\n\n')
    .replace(/<br\s*\/?>/gi, '\n')
    .replace(/<[^>]+>/g, ' ');

  return decodeEntities(withBreaks)
    .split(/\n{2,}/)
    .map((paragraph) => paragraph.replace(/[ \t\u00a0]+/g, ' ').trim())
    .filter((paragraph) => paragraph !== '')
    .join('\n\n');
}

/** Extrait le contenu d'un `<title>` ou du premier titre de section. */
function extractChapterTitle(html: string, fallback: string): string {
  const titleMatch = /<title[^>]*>([\s\S]*?)<\/title>/i.exec(html);
  if (titleMatch) {
    const title = decodeEntities(titleMatch[1]).replace(/\s+/g, ' ').trim();
    if (title !== '') return title;
  }

  const headingMatch = /<h[1-3][^>]*>([\s\S]*?)<\/h[1-3]>/i.exec(html);
  if (headingMatch) {
    const heading = decodeEntities(headingMatch[1].replace(/<[^>]+>/g, ' '))
      .replace(/\s+/g, ' ')
      .trim();
    if (heading !== '') return heading;
  }

  return fallback;
}

/** Lit une métadonnée Dublin Core (`<dc:title>`), avec ou sans préfixe. */
function readDcTitle(opf: string): string | null {
  const match = /<dc:title[^>]*>([\s\S]*?)<\/dc:title>/i.exec(opf);
  if (!match) return null;
  const title = decodeEntities(match[1]).replace(/\s+/g, ' ').trim();
  return title === '' ? null : title;
}

/** Chemin du manifeste OPF, lu dans `META-INF/container.xml`. */
function findOpfPath(files: Record<string, Uint8Array>): string {
  const container = readText(files, 'META-INF/container.xml');

  if (container) {
    const match = /full-path\s*=\s*"([^"]+)"/i.exec(container);
    if (match && files[match[1]]) return match[1];
  }

  // Repli : premier `.opf` de l'archive (certains EPUB sont mal formés).
  const fallback = Object.keys(files).find((path) => path.toLowerCase().endsWith('.opf'));
  if (fallback) return fallback;

  throw new EpubParseError('epub_opf_not_found');
}

/** Entrées du `<manifest>` : id → chemin résolu. */
function readManifest(opf: string, baseDir: string): Map<string, string> {
  const manifest = new Map<string, string>();
  const itemPattern = /<item\b[^>]*>/gi;

  for (const tag of opf.match(itemPattern) ?? []) {
    const id = /\bid\s*=\s*"([^"]+)"/i.exec(tag)?.[1];
    const href = /\bhref\s*=\s*"([^"]+)"/i.exec(tag)?.[1];
    if (!id || !href) continue;
    manifest.set(id, resolveHref(baseDir, href));
  }

  return manifest;
}

/** Ordre de lecture du `<spine>` : liste de chemins. */
function readSpine(opf: string, manifest: Map<string, string>): string[] {
  const spineBlock = /<spine\b[^>]*>([\s\S]*?)<\/spine>/i.exec(opf)?.[1] ?? '';
  const order: string[] = [];

  for (const tag of spineBlock.match(/<itemref\b[^>]*>/gi) ?? []) {
    const idref = /\bidref\s*=\s*"([^"]+)"/i.exec(tag)?.[1];
    if (!idref) continue;
    const path = manifest.get(idref);
    if (path) order.push(path);
  }

  // Repli : aucun spine exploitable → tous les XHTML du manifeste, triés.
  if (order.length === 0) {
    return [...manifest.values()]
      .filter((path) => /\.x?html?$/i.test(path))
      .sort((a, b) => a.localeCompare(b));
  }

  return order;
}

/**
 * Extrait le contenu lisible d'un EPUB.
 *
 * @param data octets de l'archive EPUB
 * @throws EpubParseError si l'archive est illisible ou sans chapitre
 */
export function parseEpub(data: Uint8Array): EpubContent {
  let files: Record<string, Uint8Array>;

  try {
    files = unzipSync(data);
  } catch {
    throw new EpubParseError('epub_not_a_zip');
  }

  const opfPath = findOpfPath(files);
  const opf = readText(files, opfPath);

  if (opf === null) {
    throw new EpubParseError('epub_opf_unreadable');
  }

  const baseDir = opfPath.includes('/') ? opfPath.slice(0, opfPath.lastIndexOf('/')) : '';
  const manifest = readManifest(opf, baseDir);
  const spine = readSpine(opf, manifest);

  const chapters: EpubChapter[] = [];

  for (const path of spine) {
    const html = readText(files, path);
    if (html === null) continue;

    const text = htmlToText(html);
    if (text === '') continue;

    const fallbackTitle = path.split('/').pop()?.replace(/\.x?html?$/i, '') ?? path;
    chapters.push({ title: extractChapterTitle(html, fallbackTitle), text });
  }

  if (chapters.length === 0) {
    throw new EpubParseError('epub_no_readable_chapter');
  }

  return { title: readDcTitle(opf), chapters };
}
