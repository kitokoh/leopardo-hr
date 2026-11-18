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

/** Verticale → (chemin de page, composant de surface attendu). */
const SURFACE_PAGES: ReadonlyArray<{
  vertical: string;
  page: string;
  component: string;
}> = [
  { vertical: 'restaurant', page: 'restaurants/page.tsx', component: 'RestaurantsExplorer' },
  { vertical: 'retail', page: 'boutiques/page.tsx', component: 'ShopDirectorySurface' },
  { vertical: 'hospitality', page: 'stay/page.tsx', component: 'PropertyDirectorySurface' },
  { vertical: 'trade', page: 'entreprises/page.tsx', component: 'CompanyDirectorySurface' },
  { vertical: 'travel', page: 'billets/page.tsx', component: 'TravelSurface' },
  { vertical: 'pharmacy', page: 'pharmacies/page.tsx', component: 'PharmacySurface' },
  { vertical: 'vtc', page: 'operateurs/page.tsx', component: 'MobilitySurface' },
];

function readPage(relativePath: string): string {
  return readFileSync(join(APP_DIR, relativePath), 'utf8');
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

  it('couvre toutes les verticales du registre', () => {
    // Import paresseux : le registre tire les identités, qui tirent le
    // catalogue de copie. On veut la liste, pas un rendu.
    const { SURFACE_VERTICALS } = require('../kernel/domain/surface-identity');

    const covered = SURFACE_PAGES.map((entry) => entry.vertical).sort();

    expect(covered).toEqual([...SURFACE_VERTICALS].sort());
  });
});
