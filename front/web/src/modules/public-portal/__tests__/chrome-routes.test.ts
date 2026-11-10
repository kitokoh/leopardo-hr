import fs from 'fs';
import path from 'path';
import { PUBLIC_PORTAL_LOCALES, getPublicPortalCopy } from '../data/copy';

/**
 * Garde-fou de cohérence chrome ↔ routes du portail public.
 *
 * Le chrome partagé (`PublicPortalNav` + `PublicPortalLayout`) est le SEUL
 * point d'entrée vers les verticales : un lien mort y enverrait un visiteur
 * (ou un crawler) vers une 404, et une surface oubliée rendrait une verticale
 * inatteignable. Ce test lit la source des composants, en extrait chaque
 * `href` littéral et vérifie qu'une route correspondante est réellement servie
 * par l'app — même approche que la garde « clé i18n morte » de la Toolbox.
 *
 * Il verrouille aussi la présence des 6 surfaces du portail (accueil + 5
 * annuaires) et d'un libellé de navigation pour chacune, dans les 4 langues.
 */

const moduleRoot = path.resolve(__dirname, '..');
const appRoot = path.resolve(__dirname, '../../../app');

/** Vrai si une route servie correspond au chemin (page.tsx, groupe ou non). */
function routeExists(href: string): boolean {
  const clean = href.split('?')[0].split('#')[0];
  if (clean === '' || clean === '/') {
    return (
      fs.existsSync(path.join(appRoot, 'page.tsx')) ||
      fs.existsSync(path.join(appRoot, 'business', 'page.tsx'))
    );
  }
  const segment = clean.replace(/^\//, '');
  return [
    path.join(appRoot, segment, 'page.tsx'),
    path.join(appRoot, 'business', segment, 'page.tsx'),
    path.join(appRoot, '(dashboard)', segment, 'page.tsx'),
  ].some((candidate) => fs.existsSync(candidate));
}

/** Fichiers source du module (hors tests). */
function collectSourceFiles(dir: string): string[] {
  if (!fs.existsSync(dir)) return [];
  return fs.readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) return collectSourceFiles(full);
    if (/\.(ts|tsx)$/.test(entry.name) && !/\.test\.(ts|tsx)$/.test(entry.name)) return [full];
    return [];
  });
}

/** Extrait les `href` littéraux (`href="/x"`), ignore les `href={…}` dynamiques. */
function extractHrefs(source: string): string[] {
  const hrefs = new Set<string>();
  const pattern = /href=["'](\/[^"']*)["']/g;
  let match: RegExpExecArray | null;
  while ((match = pattern.exec(source)) !== null) {
    hrefs.add(match[1]);
  }
  return [...hrefs];
}

/** Surfaces du portail : accueil + 5 annuaires de verticale. */
const PORTAL_SURFACES = [
  '/decouvrir',
  '/restaurants',
  '/boutiques',
  '/operateurs',
  '/entreprises',
  '/stay',
] as const;

describe('public-portal — cohérence chrome ↔ routes', () => {
  const sources = collectSourceFiles(moduleRoot)
    .map((file) => fs.readFileSync(file, 'utf8'))
    .join('\n');
  const hrefs = extractHrefs(sources);

  it('expose au moins un lien interne', () => {
    expect(hrefs.length).toBeGreaterThan(0);
  });

  it('ne pointe QUE vers des routes réellement servies (aucun lien mort)', () => {
    const dead = hrefs.filter((href) => !routeExists(href));
    expect(dead).toEqual([]);
  });

  it('relie les 6 surfaces du portail (accueil + 5 annuaires)', () => {
    for (const href of PORTAL_SURFACES) {
      expect(hrefs).toContain(href);
    }
  });

  it('déclare un libellé de navigation non vide pour chaque surface, dans les 4 langues', () => {
    const keys = ['discover', 'restaurants', 'shops', 'operators', 'companies', 'stays'] as const;
    for (const locale of PUBLIC_PORTAL_LOCALES) {
      const chrome = getPublicPortalCopy(locale).chrome;
      for (const key of keys) {
        expect(chrome[key].trim().length).toBeGreaterThan(0);
      }
    }
  });
});
