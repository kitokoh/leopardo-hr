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
const appRoot = path.resolve(__dirname, '../../../../app');

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

/**
 * Extrait les `href` littéraux, sous les deux formes utilisées dans le module :
 *
 *  - JSX : `href="/x"` ;
 *  - objet de navigation : `{ href: '/x', label: … }`.
 *
 * ## Pourquoi la seconde forme compte (#9657)
 *
 * L'extracteur ne lisait que la forme JSX. Or `PublicPortalNav` déclare ses
 * entrées dans un tableau d'objets `{ href: '/billets', … }` : les liens de
 * navigation étaient donc **invisibles** pour cette garde. C'est précisément
 * pourquoi trois verticales ont pu manquer à l'en-tête sans qu'aucun test ne
 * bronche — la garde vérifiait les liens qu'elle savait voir, pas ceux qui
 * existaient.
 */
function extractHrefs(source: string): string[] {
  const hrefs = new Set<string>();
  const patterns = [/href=["'](\/[^"']*)["']/g, /href:\s*["'](\/[^"']*)["']/g];
  for (const pattern of patterns) {
    let match: RegExpExecArray | null;
    while ((match = pattern.exec(source)) !== null) {
      hrefs.add(match[1]);
    }
  }
  return [...hrefs];
}

/**
 * Surfaces du portail : accueil + les annuaires de verticale.
 *
 * ## Pourquoi cette liste a grandi (#9657)
 *
 * Elle était figée sur 6 entrées alors que le registre en déclare 8. Trois
 * verticales livrées après sa création — `/billets` (#9593), `/pharmacies`
 * (#9592) et `/emploi` (#9629) — n'avaient **jamais** été ajoutées à la
 * navigation du portail : elles existaient, elles étaient indexées, et elles
 * restaient inatteignables depuis l'en-tête. Une garde qui recopie une liste
 * au lieu de la lire ne signale pas l'oubli : elle le valide.
 */
const PORTAL_SURFACES = [
  '/decouvrir',
  '/restaurants',
  '/boutiques',
  '/operateurs',
  '/entreprises',
  '/stay',
  '/billets',
  '/pharmacies',
  '/emploi',
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

  it('relie toutes les surfaces du portail', () => {
    for (const href of PORTAL_SURFACES) {
      expect(hrefs).toContain(href);
    }
  });

  it('déclare un libellé de navigation non vide pour chaque surface, dans les 4 langues', () => {
    const keys = [
      'discover',
      'restaurants',
      'shops',
      'operators',
      'companies',
      'stays',
      'tickets',
      'pharmacies',
      'jobs',
    ] as const;
    for (const locale of PUBLIC_PORTAL_LOCALES) {
      const chrome = getPublicPortalCopy(locale).chrome;
      for (const key of keys) {
        expect(chrome[key].trim().length).toBeGreaterThan(0);
      }
    }
  });
});
