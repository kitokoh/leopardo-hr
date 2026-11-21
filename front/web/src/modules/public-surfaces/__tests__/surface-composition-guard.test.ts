import { readFileSync } from 'node:fs';
import { join } from 'node:path';

/**
 * Garde d'architecture — chaque vitrine rend sa propre composition (#9618).
 *
 * ## Le défaut que cette garde empêche de réintroduire
 *
 * `/boutiques`, `/stay` et `/entreprises` étaient trois fichiers de 19 lignes
 * appelant `buildDirectoryPage({ type, basePath })` — la **même** fabrique, donc
 * le même gabarit visuel. Les composants `ShopDirectorySurface`,
 * `PropertyDirectorySurface` et `CompanyDirectorySurface` existaient, étaient
 * testés, portaient une composition propre… et n'étaient montés nulle part.
 *
 * Rien ne l'empêchait : une page d'annuaire « générique » compile, passe les
 * tests et s'affiche. Seule une garde explicite rend la régression visible.
 *
 * ## Ce que la garde vérifie
 *
 * 1. Aucune page d'annuaire de vitrine n'importe `buildDirectoryPage`.
 * 2. Chaque page d'annuaire monte bien son composant de surface dédié.
 *
 * La liste des verticales est lue depuis le registre réel, pas recopiée : une
 * septième vitrine ajoutée sans page dédiée fait échouer ce test.
 */

const APP_DIR = join(__dirname, '..', '..', '..', 'app');

/** Verticale → (chemin de page, composant de surface attendu, fichier du composant). */
const SURFACE_PAGES: ReadonlyArray<{
  vertical: string;
  page: string;
  component: string;
  /** Fichier du composant de surface, relatif à `modules/public-surfaces/`. */
  componentFile: string;
}> = [
  {
    vertical: 'restaurant',
    page: 'restaurants/page.tsx',
    component: 'RestaurantExplorer',
    componentFile: 'restaurants/components/RestaurantSurface.tsx',
  },
  {
    vertical: 'retail',
    page: 'boutiques/page.tsx',
    component: 'ShopDirectorySurface',
    componentFile: 'retail/components/ShopDirectorySurface.tsx',
  },
  {
    vertical: 'hospitality',
    page: 'stay/page.tsx',
    component: 'PropertyDirectorySurface',
    componentFile: 'hospitality/components/PropertyDirectorySurface.tsx',
  },
  {
    vertical: 'trade',
    page: 'entreprises/page.tsx',
    component: 'CompanyDirectorySurface',
    componentFile: 'trade/components/CompanyDirectorySurface.tsx',
  },
  {
    vertical: 'travel',
    page: 'billets/page.tsx',
    component: 'TravelSurface',
    componentFile: 'travel/components/TravelSurface.tsx',
  },
  {
    vertical: 'pharmacy',
    page: 'pharmacies/page.tsx',
    component: 'PharmacySurface',
    componentFile: 'pharmacy/components/PharmacySurface.tsx',
  },
  {
    vertical: 'vtc',
    page: 'operateurs/page.tsx',
    component: 'MobilitySurface',
    componentFile: 'vtc/components/MobilitySurface.tsx',
  },
  {
    vertical: 'careers',
    page: 'emploi/page.tsx',
    component: 'CareersDirectorySurface',
    componentFile: 'careers/components/CareersDirectorySurface.tsx',
  },
];

function readPage(relativePath: string): string {
  return readFileSync(join(APP_DIR, relativePath), 'utf8');
}

/** Lit un fichier du module `public-surfaces`. */
function readSurfaceFile(relativePath: string): string {
  return readFileSync(join(__dirname, '..', relativePath), 'utf8');
}

/**
 * Retire commentaires de bloc et de ligne.
 *
 * Nécessaire : les pages documentent l'historique (« cette page appelait
 * `buildDirectoryPage(...)` »). Sans ce nettoyage, la garde signalerait le
 * commentaire qui explique la correction, et forcerait à supprimer une
 * documentation utile pour la faire passer.
 */
function stripComments(source: string): string {
  return source.replace(/\/\*[\s\S]*?\*\//g, '').replace(/^\s*\/\/.*$/gm, '');
}

describe('garde — chaque vitrine a sa propre composition', () => {
  it.each(SURFACE_PAGES)(
    '$vertical : la page monte son composant de surface dédié',
    ({ page, component }) => {
      expect(readPage(page)).toContain(component);
    },
  );

  it.each(SURFACE_PAGES)(
    '$vertical : la page n’importe pas la fabrique générique',
    ({ page }) => {
      // `buildDirectoryPage` produit un gabarit partagé : c'est exactement ce
      // que la mission devait éliminer. Une page qui l'importe n'a pas de
      // composition propre, même si elle compile.
      //
      // On cherche l'IMPORT, pas le mot : les commentaires d'historique citent
      // légitimement `buildDirectoryPage` pour expliquer ce qui a été retiré.
      const source = stripComments(readPage(page));

      expect(source).not.toMatch(/import[^;]*buildDirectoryPage/);
      expect(source).not.toMatch(/buildDirectoryPage\s*\(/);
    },
  );

  /**
   * #9696 — toutes les pages d'annuaire montent le noyau de rendu.
   *
   * `/restaurants` était la seule des huit à ne pas monter `SurfaceShell` : elle
   * posait `data-surface` et une couleur de fond à la main, avec un `<header>`
   * et un `<h1>` ad hoc. Sept vitrines partageaient donc une composition
   * (palette en variables CSS, hero, CTA, invitation tenant) et la huitième en
   * avait une copie approximative — même contenu, mais pas les mêmes variables
   * CSS, donc pas les mêmes composants enfants.
   *
   * Rien ne le signalait : la page compilait, passait les tests et s'affichait.
   * Cette garde rend la divergence visible.
   */
  it.each(SURFACE_PAGES)(
    '$vertical : le composant de surface monte SurfaceShell (noyau de rendu partagé)',
    ({ componentFile }) => {
      const source = stripComments(readSurfaceFile(componentFile));

      // On vérifie le COMPOSANT, pas la page : sept pages délèguent le rendu à
      // leur composant de surface, qui monte le noyau. C'est le composant qui
      // doit porter la composition partagée — une page qui monterait
      // `SurfaceShell` sans que son composant le fasse resterait divergente.
      expect(source).toMatch(/SurfaceShell/);
    },
  );

  it('couvre toutes les verticales du registre', () => {
    // Import paresseux : le registre tire les identités, qui tirent le
    // catalogue de copie. On veut la liste, pas un rendu.
    const { SURFACE_VERTICALS } = require('../kernel/domain/surface-identity');

    const covered = SURFACE_PAGES.map((entry) => entry.vertical).sort();

    expect(covered).toEqual([...SURFACE_VERTICALS].sort());
  });
});
