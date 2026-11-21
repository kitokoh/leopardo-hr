import { renderToStaticMarkup } from 'react-dom/server';

import { SurfaceSearchBar } from '../kernel/components/SurfaceSearchBar';
import { RETAIL_SURFACE } from '../retail';

/**
 * Barre de recherche des annuaires de vitrine (#9657).
 *
 * ## Ce que ces tests protègent
 *
 * Les adaptateurs acceptaient `q` et `city` et les pages les lisaient depuis
 * l'URL, mais aucun annuaire n'affichait de champ : le filtre n'était
 * atteignable qu'en éditant l'URL. Ces tests figent les propriétés qui rendent
 * la correction utile :
 *
 *  1. formulaire GET natif (fonctionne sans JS, URL partageable et crawlable) ;
 *  2. les valeurs courantes sont pré-remplies (sinon un filtre actif paraît
 *     absent et le visiteur le croit perdu) ;
 *  3. le bouton de réinitialisation n'apparaît que s'il y a un filtre.
 */

const labels = {
  searchLabel: 'Rechercher',
  searchPlaceholder: 'Nom, spécialité…',
  cityLabel: 'Ville',
  cityPlaceholder: 'Toutes les villes',
  searchSubmit: 'Rechercher',
  searchReset: 'Réinitialiser',
};

function render(props: Partial<Parameters<typeof SurfaceSearchBar>[0]> = {}): string {
  return renderToStaticMarkup(
    <SurfaceSearchBar identity={RETAIL_SURFACE} basePath="/boutiques" labels={labels} {...props} />,
  );
}

describe('SurfaceSearchBar', () => {
  it('rend un formulaire GET vers l’annuaire', () => {
    const html = render();

    expect(html).toContain('method="get"');
    expect(html).toContain('action="/boutiques"');
    expect(html).toContain('role="search"');
  });

  it('expose les deux champs attendus par l’adaptateur', () => {
    const html = render();

    expect(html).toContain('name="q"');
    expect(html).toContain('name="city"');
  });

  it('pré-remplit les filtres courants', () => {
    const html = render({ defaultQuery: 'boulangerie', defaultCity: 'Lyon' });

    expect(html).toContain('value="boulangerie"');
    expect(html).toContain('value="Lyon"');
  });

  it('n’affiche pas de réinitialisation sans filtre actif', () => {
    expect(render()).not.toContain('Réinitialiser');
  });

  it('affiche la réinitialisation quand un filtre est actif', () => {
    const html = render({ defaultQuery: 'boulangerie' });

    expect(html).toContain('Réinitialiser');
    expect(html).toContain('href="/boutiques"');
  });

  it('associe chaque champ à un libellé', () => {
    const html = render();

    // Un placeholder n'est pas un libellé : il disparaît à la saisie et n'est
    // pas lu de façon fiable par un lecteur d'écran.
    expect(html).toContain('<label');
    expect(html).toContain('Rechercher');
    expect(html).toContain('Ville');
  });
});
