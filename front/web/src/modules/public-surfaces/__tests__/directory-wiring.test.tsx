import { renderToStaticMarkup } from 'react-dom/server';

import { CareersDirectorySurface } from '../careers/components/CareersDirectorySurface';
import { CAREERS_SURFACE } from '../careers';
import { CompanyDirectorySurface } from '../trade/components/CompanyDirectorySurface';
import { TRADE_SURFACE } from '../trade';
import { MobilitySurface } from '../vtc/components/MobilitySurface';
import { MOBILITY_SURFACE } from '../vtc';
import { PharmacySurface } from '../pharmacy/components/PharmacySurface';
import { PHARMACY_SURFACE } from '../pharmacy';

/**
 * Câblage recherche + pagination sur les quatre verticales restantes (#9697).
 *
 * ## Le défaut que ces tests figent
 *
 * `SurfacePagination` et `SurfaceSearchBar` existaient et étaient testées, mais
 * seules retail et hospitality les montaient. Sur `trade`, `pharmacy`, `vtc` et
 * `careers`, les adaptateurs acceptaient `q`/`city`/`page` et les pages les
 * lisaient depuis l'URL — **aucun contrôle à l'écran**. Le filtre n'était
 * atteignable qu'en éditant l'URL à la main, et les résultats au-delà de la
 * première page étaient inatteignables.
 *
 * Ces tests vérifient le câblage, pas le rendu de la primitive (déjà couvert
 * par `surface-pagination.test.tsx` et `surface-search-bar.test.tsx`) : chaque
 * surface doit monter les deux contrôles, et la pagination doit recevoir la
 * page et la dernière page du résultat.
 */

const tradeResult = {
  state: 'ready' as const,
  page: 2,
  lastPage: 5,
  cards: [
    {
      entry: {
        slug: 'acme',
        name: 'ACME Import',
        city: 'Lyon',
        description: null,
        productCategories: ['Textile'],
        imports: true,
        exports: false,
        domestic: false,
      },
      directions: [],
      categories: ['Textile'],
    },
  ],
};

const pharmacyResult = {
  state: 'ready' as const,
  page: 3,
  lastPage: 4,
  cards: [
    {
      pharmacy: {
        slug: 'officine-centrale',
        name: 'Officine Centrale',
        city: 'Nantes',
        description: null,
        onDuty: true,
        services: [],
      },
      duty: 'De garde',
    },
  ],
};

const mobilityResult = {
  state: 'ready' as const,
  page: 1,
  lastPage: 2,
  cards: [
    {
      operator: {
        slug: 'taxi-bleu',
        name: 'Taxi Bleu',
        city: 'Nice',
        country: 'FR',
        currency: 'EUR',
      },
      meta: 'Nice · FR',
      currency: 'EUR',
    },
  ],
};

const careersResult = {
  failed: false,
  page: 2,
  lastPage: 3,
  companies: [
    { slug: 'leopardo', name: 'Leopardo', country: 'FR', lastPublishedAt: '2026-11-01' },
  ],
};

describe('câblage recherche + pagination (#9697)', () => {
  it('trade : monte la barre de recherche et la pagination', () => {
    const html = renderToStaticMarkup(
      <CompanyDirectorySurface
        identity={TRADE_SURFACE}
        result={tradeResult}
        searchParams={{ q: 'acme', city: 'Lyon' }}
      />,
    );

    expect(html).toContain('data-surface-search');
    // Le filtre courant est pré-rempli : sans ça, la page 2 afficherait un
    // champ vide alors que la liste est filtrée.
    expect(html).toContain('value="acme"');
    expect(html).toContain('value="Lyon"');
    // La pagination reçoit la page courante et la dernière page du résultat.
    expect(html).toContain('page=3');
  });

  it('pharmacy : monte la barre de recherche et la pagination', () => {
    const html = renderToStaticMarkup(
      <PharmacySurface identity={PHARMACY_SURFACE} result={pharmacyResult} />,
    );

    expect(html).toContain('data-surface-search');
    expect(html).toContain('page=4');
  });

  it('vtc : monte la barre de recherche et la pagination', () => {
    const html = renderToStaticMarkup(
      <MobilitySurface identity={MOBILITY_SURFACE} result={mobilityResult} />,
    );

    expect(html).toContain('data-surface-search');
    expect(html).toContain('page=2');
  });

  it('careers : monte la barre de recherche et la pagination', () => {
    const html = renderToStaticMarkup(
      <CareersDirectorySurface
        identity={CAREERS_SURFACE}
        result={careersResult}
        searchParams={{ q: 'leo' }}
      />,
    );

    expect(html).toContain('data-surface-search');
    expect(html).toContain('value="leo"');
    expect(html).toContain('page=3');
  });

  it('careers : n’affiche PAS de champ ville (pas de filtre géographique)', () => {
    const html = renderToStaticMarkup(
      <CareersDirectorySurface identity={CAREERS_SURFACE} result={careersResult} />,
    );

    // Un champ ville inerte serait un contrôle qui ne filtre rien : l'API
    // `/public/careers` n'accepte pas `city`.
    expect(html).not.toContain('name="city"');
  });

  it('ne rend aucune pagination quand il n’y a qu’une page', () => {
    const html = renderToStaticMarkup(
      <PharmacySurface
        identity={PHARMACY_SURFACE}
        result={{ ...pharmacyResult, page: 1, lastPage: 1 }}
      />,
    );

    expect(html).not.toContain('page=2');
  });
});
