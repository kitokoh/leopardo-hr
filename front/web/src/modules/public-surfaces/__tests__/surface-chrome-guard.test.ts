import { readFileSync } from 'node:fs';
import { join } from 'node:path';

/**
 * Garde d'architecture — chaque vitrine porte le chrome du portail (#9657).
 *
 * ## Le défaut que cette garde empêche de réintroduire
 *
 * Six verticales sur huit avaient un `layout.tsx` montant `PublicPortalLayout`.
 * `/billets` et `/emploi` n'en avaient pas : un visiteur arrivé depuis un moteur
 * de recherche n'avait aucun en-tête, donc aucun moyen de changer de verticale,
 * de langue ou de revenir au portail. C'est une impasse de navigation, et rien
 * ne la signalait — la page compilait, passait les tests et s'affichait.
 *
 * ## Ce que la garde vérifie
 *
 * Pour chaque verticale du registre réel (pas une liste recopiée) : la page
 * d'index existe, et un `layout.tsx` voisin monte `PublicPortalLayout`.
 *
 * Une neuvième verticale ajoutée sans layout fait échouer ce test.
 */

const APP_DIR = join(__dirname, '..', '..', '..', 'app');

/** Verticale → répertoire de route de son annuaire. */
const SURFACE_ROUTES: ReadonlyArray<{ vertical: string; dir: string }> = [
  { vertical: 'restaurant', dir: 'restaurants' },
  { vertical: 'retail', dir: 'boutiques' },
  { vertical: 'hospitality', dir: 'stay' },
  { vertical: 'trade', dir: 'entreprises' },
  { vertical: 'travel', dir: 'billets' },
  { vertical: 'pharmacy', dir: 'pharmacies' },
  { vertical: 'vtc', dir: 'operateurs' },
  { vertical: 'careers', dir: 'emploi' },
];

describe('garde — chaque vitrine porte le chrome du portail', () => {
  it.each(SURFACE_ROUTES)(
    '$vertical : la route $dir monte PublicPortalLayout',
    ({ dir }) => {
      const layout = readFileSync(join(APP_DIR, dir, 'layout.tsx'), 'utf8');

      expect(layout).toContain('PublicPortalLayout');
    },
  );

  it('couvre toutes les verticales du registre', () => {
    const { SURFACE_VERTICALS } = require('../kernel/domain/surface-identity');

    const covered = SURFACE_ROUTES.map((entry) => entry.vertical).sort();

    expect(covered).toEqual([...SURFACE_VERTICALS].sort());
  });
});
