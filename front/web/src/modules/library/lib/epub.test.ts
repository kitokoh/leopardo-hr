import { zipSync, strToU8 } from 'fflate';
import {
  parseEpub,
  htmlToText,
  decodeEntities,
  resolveHref,
  EpubParseError,
} from '../lib/epub';

/**
 * Tests de l'extraction EPUB (cœur PUR).
 *
 * On construit de vraies archives ZIP en mémoire avec `fflate` : le parseur
 * est donc exercé sur des octets réels, pas sur des mocks. Aucun fichier
 * binaire n'est versionné dans le dépôt.
 */

/** Construit un EPUB minimal valide en mémoire. */
function buildEpub(options: {
  title?: string;
  chapters?: { href: string; title: string; body: string }[];
  containerPath?: string;
} = {}): Uint8Array {
  const chapters = options.chapters ?? [
    { href: 'ch1.xhtml', title: 'Chapitre premier', body: '<p>Bonjour le monde.</p>' },
    { href: 'ch2.xhtml', title: 'Chapitre second', body: '<p>Deuxième partie.</p>' },
  ];
  const opfPath = options.containerPath ?? 'OEBPS/content.opf';
  const baseDir = opfPath.includes('/') ? opfPath.slice(0, opfPath.lastIndexOf('/')) : '';

  const manifestItems = chapters
    .map((chapter, index) => `<item id="c${index}" href="${chapter.href}" media-type="application/xhtml+xml"/>`)
    .join('');
  const spineItems = chapters.map((_chapter, index) => `<itemref idref="c${index}"/>`).join('');

  const opf = `<?xml version="1.0"?>
<package xmlns="http://www.idpf.org/2007/opf">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:title>${options.title ?? 'Livre de test'}</dc:title>
  </metadata>
  <manifest>${manifestItems}</manifest>
  <spine>${spineItems}</spine>
</package>`;

  const container = `<?xml version="1.0"?>
<container version="1.0" xmlns="urn:oasis:names:tc:opendocument:xmlns:container">
  <rootfiles><rootfile full-path="${opfPath}" media-type="application/oebps-package+xml"/></rootfiles>
</container>`;

  const files: Record<string, Uint8Array> = {
    'META-INF/container.xml': strToU8(container),
    [opfPath]: strToU8(opf),
  };

  for (const chapter of chapters) {
    const path = baseDir === '' ? chapter.href : `${baseDir}/${chapter.href}`;
    files[path] = strToU8(
      `<html><head><title>${chapter.title}</title></head><body>${chapter.body}</body></html>`,
    );
  }

  return zipSync(files);
}

describe('epub — parseEpub', () => {
  it('extrait le titre et les chapitres dans l’ordre du spine', () => {
    const content = parseEpub(buildEpub());

    expect(content.title).toBe('Livre de test');
    expect(content.chapters).toHaveLength(2);
    expect(content.chapters[0].title).toBe('Chapitre premier');
    expect(content.chapters[0].text).toContain('Bonjour le monde.');
    expect(content.chapters[1].text).toContain('Deuxième partie.');
  });

  it('respecte l’ordre du spine même si le manifeste est dans un autre ordre', () => {
    const content = parseEpub(
      buildEpub({
        chapters: [
          { href: 'b.xhtml', title: 'B', body: '<p>second</p>' },
          { href: 'a.xhtml', title: 'A', body: '<p>premier</p>' },
        ],
      }),
    );

    expect(content.chapters.map((chapter) => chapter.title)).toEqual(['B', 'A']);
  });

  it('gère un OPF à la racine (sans dossier)', () => {
    const content = parseEpub(buildEpub({ containerPath: 'content.opf' }));
    expect(content.chapters).toHaveLength(2);
  });

  it('rejette une archive qui n’est pas un ZIP', () => {
    expect(() => parseEpub(strToU8('pas un zip du tout'))).toThrow(EpubParseError);
  });

  it('rejette une archive sans manifeste OPF', () => {
    const files = { 'META-INF/container.xml': strToU8('<container/>') };
    expect(() => parseEpub(zipSync(files))).toThrow(EpubParseError);
  });

  it('rejette un EPUB sans chapitre lisible', () => {
    const files = {
      'META-INF/container.xml': strToU8(
        '<container><rootfiles><rootfile full-path="c.opf"/></rootfiles></container>',
      ),
      'c.opf': strToU8('<package><manifest></manifest><spine></spine></package>'),
    };
    expect(() => parseEpub(zipSync(files))).toThrow(EpubParseError);
  });
});

describe('epub — htmlToText', () => {
  it('convertit les blocs en paragraphes', () => {
    const text = htmlToText('<html><body><p>Un</p><p>Deux</p></body></html>');
    expect(text).toBe('Un\n\nDeux');
  });

  it('supprime scripts et styles', () => {
    const text = htmlToText(
      '<html><head><style>p{color:red}</style></head><body><script>alert(1)</script><p>Texte</p></body></html>',
    );
    expect(text).toBe('Texte');
    expect(text).not.toContain('alert');
    expect(text).not.toContain('color');
  });

  it('décode les entités et normalise les espaces', () => {
    expect(htmlToText('<p>a &amp; b&nbsp;&nbsp;c</p>')).toBe('a & b c');
  });

  it('retourne une chaîne vide pour un document sans texte', () => {
    expect(htmlToText('<html><body><div></div></body></html>')).toBe('');
  });
});

describe('epub — decodeEntities', () => {
  it('décode les entités nommées, décimales et hexadécimales', () => {
    expect(decodeEntities('&lt;tag&gt;')).toBe('<tag>');
    expect(decodeEntities('&#233;')).toBe('é');
    expect(decodeEntities('&#xE9;')).toBe('é');
  });

  it('laisse intacte une entité inconnue', () => {
    expect(decodeEntities('&inconnue;')).toBe('&inconnue;');
  });
});

describe('epub — resolveHref', () => {
  it('résout un chemin relatif', () => {
    expect(resolveHref('OEBPS', 'ch1.xhtml')).toBe('OEBPS/ch1.xhtml');
  });

  it('remonte les segments `..`', () => {
    expect(resolveHref('OEBPS/text', '../images/a.png')).toBe('OEBPS/images/a.png');
  });

  it('ignore l’ancre et le préfixe absolu', () => {
    expect(resolveHref('OEBPS', 'ch1.xhtml#part2')).toBe('OEBPS/ch1.xhtml');
    expect(resolveHref('OEBPS', '/root.xhtml')).toBe('root.xhtml');
  });
});
