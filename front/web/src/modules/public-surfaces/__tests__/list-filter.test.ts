import {
  filterByQuery,
  matchesQuery,
  normalizeQuery,
  paginateList,
} from '../kernel/domain/list-filter';

/**
 * Filtrage et pagination des **fiches détail** (#9705).
 *
 * ## Ce que ces tests protègent
 *
 * Les annuaires ont reçu recherche et pagination en #9657/#9697. Les fiches
 * détail — catégories d'une boutique, types de chambre, services d'une
 * officine, offres d'une entreprise — sont restées sans contrôle : le filtre
 * n'était atteignable qu'en éditant l'URL à la main, et une liste de 60 lignes
 * s'affichait d'un bloc.
 *
 * Le noyau porte les trois règles qu'on ne veut pas voir diverger entre huit
 * verticales : comparaison insensible à la casse **et aux accents**, requête
 * vide qui ne filtre rien, page hors bornes ramenée dans l'intervalle.
 */

describe('normalizeQuery', () => {
  it('passe en minuscules et retire les accents', () => {
    expect(normalizeQuery('Sévres')).toBe('sevres');
    expect(normalizeQuery('CRÈME BRÛLÉE')).toBe('creme brulee');
  });

  it('réduit les espaces et rogne les bords', () => {
    expect(normalizeQuery('  chambre   double  ')).toBe('chambre double');
  });

  it('rend une chaîne vide pour une entrée vide', () => {
    expect(normalizeQuery('')).toBe('');
    expect(normalizeQuery('   ')).toBe('');
  });
});

describe('matchesQuery', () => {
  it('ne filtre rien quand la requête est vide', () => {
    expect(matchesQuery('n’importe quoi', '')).toBe(true);
    expect(matchesQuery(null, '')).toBe(true);
  });

  it('ignore un champ absent plutôt que de faire échouer la comparaison', () => {
    expect(matchesQuery(null, 'abc')).toBe(false);
    expect(matchesQuery(undefined, 'abc')).toBe(false);
    expect(matchesQuery('', 'abc')).toBe(false);
  });

  it('trouve une sous-chaîne sans tenir compte des accents', () => {
    expect(matchesQuery('Crème brûlée', normalizeQuery('creme'))).toBe(true);
    expect(matchesQuery('Chambre Double', normalizeQuery('DOUBLE'))).toBe(true);
  });
});

describe('filterByQuery', () => {
  const categories = [
    { name: 'Boulangerie' },
    { name: 'Charcuterie' },
    { name: 'Crèmerie' },
  ];

  it('renvoie la liste telle quelle quand la requête est absente', () => {
    expect(filterByQuery(categories, undefined, (c) => [c.name])).toBe(categories);
    expect(filterByQuery(categories, '', (c) => [c.name])).toBe(categories);
  });

  it('filtre sur le champ choisi', () => {
    const result = filterByQuery(categories, 'charcu', (c) => [c.name]);

    expect(result).toHaveLength(1);
    expect(result[0].name).toBe('Charcuterie');
  });

  it('trouve « cremerie » sans accent alors que la donnée en porte un', () => {
    const result = filterByQuery(categories, 'cremerie', (c) => [c.name]);

    expect(result).toHaveLength(1);
    expect(result[0].name).toBe('Crèmerie');
  });

  it('conserve l’ordre d’origine — le tri reste une décision du domaine', () => {
    const result = filterByQuery(categories, 'e', (c) => [c.name]);

    expect(result.map((c) => c.name)).toEqual(['Boulangerie', 'Charcuterie', 'Crèmerie']);
  });

  it('accepte plusieurs champs comparables et ignore les nuls', () => {
    const rooms = [
      { name: 'Suite', description: 'Vue mer' },
      { name: 'Standard', description: null },
    ];

    expect(filterByQuery(rooms, 'mer', (r) => [r.name, r.description])).toHaveLength(1);
    expect(filterByQuery(rooms, 'standard', (r) => [r.name, r.description])).toHaveLength(1);
  });

  it('rend une liste vide quand rien ne correspond', () => {
    expect(filterByQuery(categories, 'poissonnerie', (c) => [c.name])).toHaveLength(0);
  });
});

describe('paginateList', () => {
  const items = Array.from({ length: 25 }, (_, index) => index + 1);

  it('découpe en pages de la taille demandée', () => {
    const first = paginateList(items, 1, 10);

    expect(first.items).toEqual([1, 2, 3, 4, 5, 6, 7, 8, 9, 10]);
    expect(first.page).toBe(1);
    expect(first.lastPage).toBe(3);
    expect(first.total).toBe(25);
  });

  it('rend la dernière page partielle', () => {
    const last = paginateList(items, 3, 10);

    expect(last.items).toEqual([21, 22, 23, 24, 25]);
    expect(last.page).toBe(3);
  });

  it('ramène une page hors bornes dans l’intervalle au lieu de rendre du vide', () => {
    // Un lien périmé (`?page=9` sur une liste réduite à 3 pages) doit montrer la
    // dernière page, pas un écran blanc.
    expect(paginateList(items, 9, 10).page).toBe(3);
    expect(paginateList(items, 9, 10).items).toHaveLength(5);
    expect(paginateList(items, 0, 10).page).toBe(1);
    expect(paginateList(items, -4, 10).page).toBe(1);
  });

  it('traite une page absente comme la première', () => {
    expect(paginateList(items, undefined, 10).page).toBe(1);
  });

  it('rend lastPage = 1 sur une liste vide — jamais 0', () => {
    const empty = paginateList([], 1, 10);

    expect(empty.items).toHaveLength(0);
    expect(empty.lastPage).toBe(1);
    expect(empty.total).toBe(0);
  });

  it('refuse une taille de page nulle ou négative', () => {
    expect(paginateList(items, 1, 0).items).toHaveLength(1);
    expect(paginateList(items, 1, -5).items).toHaveLength(1);
  });
});
