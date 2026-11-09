import {
  EMPTY_TOOLBOX_STATE,
  HISTORY_LIMIT,
  TOOLBOX_STORAGE_KEY,
  clearState,
  isFavorite,
  loadState,
  normalizeState,
  recordUsage,
  saveState,
  toggleFavorite,
} from './storage';

/**
 * Tests de la façade de stockage « Mes outils » (décision #9156).
 *
 * Le contrat testé est celui qui survivra à un passage éventuel en persistance
 * serveur : clé versionnée, normalisation défensive, historique borné, repli
 * silencieux.
 */

describe('normalizeState', () => {
  it('retourne un état vide pour une valeur non-objet', () => {
    expect(normalizeState(null)).toEqual(EMPTY_TOOLBOX_STATE);
    expect(normalizeState('nope')).toEqual(EMPTY_TOOLBOX_STATE);
    expect(normalizeState(42)).toEqual(EMPTY_TOOLBOX_STATE);
  });

  it('écarte les entrées non-chaînes et les chaînes vides', () => {
    const state = normalizeState({ favorites: ['imc', 42, '', '  ', null, 'tva'] });
    expect(state.favorites).toEqual(['imc', 'tva']);
  });

  it('déduplique en conservant le premier ordre', () => {
    const state = normalizeState({ favorites: ['imc', 'tva', 'imc'] });
    expect(state.favorites).toEqual(['imc', 'tva']);
  });

  it('tronque l’historique à HISTORY_LIMIT', () => {
    const history = Array.from({ length: HISTORY_LIMIT + 10 }, (_, index) => `outil-${index}`);
    expect(normalizeState({ history }).history).toHaveLength(HISTORY_LIMIT);
  });

  it('tolère un objet partiel', () => {
    expect(normalizeState({ favorites: ['imc'] })).toEqual({ favorites: ['imc'], history: [] });
  });
});

describe('loadState / saveState', () => {
  beforeEach(() => {
    window.localStorage.clear();
  });

  it('retourne un état vide quand rien n’est stocké', () => {
    expect(loadState()).toEqual(EMPTY_TOOLBOX_STATE);
  });

  it('relit ce qui a été écrit', () => {
    saveState({ favorites: ['imc'], history: ['tva'] });
    expect(loadState()).toEqual({ favorites: ['imc'], history: ['tva'] });
  });

  it('utilise une clé versionnée', () => {
    saveState({ favorites: ['imc'], history: [] });
    expect(window.localStorage.getItem(TOOLBOX_STORAGE_KEY)).not.toBeNull();
    expect(TOOLBOX_STORAGE_KEY).toMatch(/_v\d+$/);
  });

  it('repart d’un état vide si le JSON stocké est corrompu', () => {
    window.localStorage.setItem(TOOLBOX_STORAGE_KEY, '{ pas du json');
    expect(loadState()).toEqual(EMPTY_TOOLBOX_STATE);
  });

  it('normalise à l’écriture', () => {
    saveState({ favorites: ['imc', 'imc', ''], history: [] });
    expect(loadState().favorites).toEqual(['imc']);
  });
});

describe('toggleFavorite', () => {
  beforeEach(() => {
    window.localStorage.clear();
  });

  it('ajoute puis retire un favori', () => {
    expect(toggleFavorite('imc').favorites).toEqual(['imc']);
    expect(toggleFavorite('imc').favorites).toEqual([]);
  });

  it('place le dernier favori en tête', () => {
    toggleFavorite('imc');
    expect(toggleFavorite('tva').favorites).toEqual(['tva', 'imc']);
  });

  it('isFavorite reflète l’état persisté', () => {
    expect(isFavorite('imc')).toBe(false);
    toggleFavorite('imc');
    expect(isFavorite('imc')).toBe(true);
  });
});

describe('recordUsage', () => {
  beforeEach(() => {
    window.localStorage.clear();
  });

  it('place l’outil utilisé en tête', () => {
    recordUsage('imc');
    expect(recordUsage('tva').history).toEqual(['tva', 'imc']);
  });

  it('ne duplique pas un outil déjà présent', () => {
    recordUsage('imc');
    recordUsage('tva');
    expect(recordUsage('imc').history).toEqual(['imc', 'tva']);
  });

  it('borne l’historique', () => {
    for (let index = 0; index < HISTORY_LIMIT + 5; index += 1) {
      recordUsage(`outil-${index}`);
    }
    expect(loadState().history).toHaveLength(HISTORY_LIMIT);
  });

  it('ne touche pas aux favoris', () => {
    toggleFavorite('imc');
    recordUsage('tva');
    expect(loadState().favorites).toEqual(['imc']);
  });
});

describe('clearState', () => {
  beforeEach(() => {
    window.localStorage.clear();
  });

  it('efface favoris et historique', () => {
    toggleFavorite('imc');
    recordUsage('tva');
    expect(clearState()).toEqual(EMPTY_TOOLBOX_STATE);
    expect(loadState()).toEqual(EMPTY_TOOLBOX_STATE);
  });
});
