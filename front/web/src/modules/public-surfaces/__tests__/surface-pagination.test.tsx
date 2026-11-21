import { renderToStaticMarkup } from 'react-dom/server';

import { SurfacePagination } from '../kernel/components/SurfacePagination';
import { RETAIL_SURFACE } from '../retail';

/**
 * Pagination des annuaires de vitrine (#9657).
 *
 * ## Ce que ces tests protègent
 *
 * Les adaptateurs acceptaient `page` et l'API renvoyait `last_page`, mais aucune
 * vitrine n'affichait de contrôle : les résultats au-delà de la première page
 * étaient inatteignables. Ces tests figent les trois comportements qui rendent
 * la correction utile plutôt que décorative :
 *
 *  1. une seule page → aucun contrôle (pas de bruit sur une verticale vide) ;
 *  2. les liens conservent les filtres courants (sinon la page 2 perd le filtre
 *     et renvoie une liste non filtrée — pire que pas de pagination) ;
 *  3. les bornes sont désactivées, pas supprimées : un `<span aria-disabled>`
 *     reste annoncé, un `<a>` sans `href` ne l'est pas.
 */

const labels = { previous: 'Page précédente', next: 'Page suivante', pageOf: 'Page {page} sur {last}' };

function render(props: Partial<Parameters<typeof SurfacePagination>[0]> = {}): string {
  return renderToStaticMarkup(
    <SurfacePagination
      identity={RETAIL_SURFACE}
      basePath="/boutiques"
      page={1}
      lastPage={1}
      labels={labels}
      {...props}
    />,
  );
}

describe('SurfacePagination', () => {
  it('ne rend rien quand il n’y a qu’une page', () => {
    expect(render({ page: 1, lastPage: 1 })).toBe('');
  });

  it('rend un lien « suivant » vers la page 2 sur la première page', () => {
    const html = render({ page: 1, lastPage: 3 });

    expect(html).toContain('href="/boutiques?page=2"');
    expect(html).toContain('rel="next"');
    // Pas de page 0 : la borne basse est un span, pas un lien.
    expect(html).not.toContain('href="/boutiques?page=0"');
    expect(html).toContain('aria-disabled="true"');
  });

  it('rend un lien « précédent » sur une page intermédiaire', () => {
    const html = render({ page: 2, lastPage: 3 });

    expect(html).toContain('href="/boutiques"');
    expect(html).toContain('rel="prev"');
    expect(html).toContain('href="/boutiques?page=3"');
  });

  it('conserve les filtres courants dans les liens', () => {
    const html = render({
      page: 1,
      lastPage: 2,
      searchParams: { q: 'boulangerie', city: 'Lyon' },
    });

    expect(html).toContain('q=boulangerie');
    expect(html).toContain('city=Lyon');
    expect(html).toContain('page=2');
  });

  it('n’écrit pas `page=1` dans l’URL de retour à la première page', () => {
    const html = render({ page: 2, lastPage: 2 });

    // La page 1 est l'URL canonique de l'annuaire : y ajouter `?page=1`
    // créerait un doublon indexable pour le même contenu.
    expect(html).toContain('href="/boutiques"');
    expect(html).not.toContain('page=1');
  });

  it('borne une page hors plage à la dernière page connue', () => {
    const html = render({ page: 99, lastPage: 3 });

    expect(html).toContain('Page 3 sur 3');
  });
});
