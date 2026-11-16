import {
  MAX_SHARED_LINES,
  INITIAL_LINES,
  addLine,
  canAddLine,
  linesToState,
  parseLines,
  pruneOrphanKeys,
  removeLine,
  stateToLines,
  type LineState,
} from './lines';

/**
 * Tests de la conversion lignes ↔ état partageable (issue #9531).
 *
 * Ces tests verrouillent les deux défauts constatés en production :
 * - supprimer une ligne laissait des clés orphelines, relues ensuite comme un
 *   duplicata de la ligne suivante (total TTC faux) ;
 * - le plafond de partage n'était pas appliqué à l'interface, donc un lien
 *   partagé recalculait un total différent de celui affiché.
 */

const LINES: LineState[] = [
  { amount: '1000', rate: '20' },
  { amount: '200', rate: '5.5' },
  { amount: '0', rate: '20' },
];

describe('linesToState — aplatissement contigu', () => {
  it('aplatit les lignes en clés indexées amount<n>/rate<n>', () => {
    expect(linesToState(LINES)).toEqual({
      amount0: '1000',
      rate0: '20',
      amount1: '200',
      rate1: '5.5',
      amount2: '0',
      rate2: '20',
    });
  });

  it('produit une numérotation SANS trou (invariant de la relecture)', () => {
    const keys = Object.keys(linesToState(LINES));
    expect(keys).toEqual([
      'amount0',
      'rate0',
      'amount1',
      'rate1',
      'amount2',
      'rate2',
    ]);
  });

  it('tronque au plafond de partage', () => {
    const many: LineState[] = Array.from({ length: MAX_SHARED_LINES + 3 }, (_, index) => ({
      amount: String(index),
      rate: '20',
    }));
    const state = linesToState(many);
    expect(Object.keys(state)).toHaveLength(MAX_SHARED_LINES * 2);
    expect(state[`amount${MAX_SHARED_LINES}`]).toBeUndefined();
  });

  it('retourne un objet vide pour aucune ligne', () => {
    expect(linesToState([])).toEqual({});
  });
});

describe('stateToLines — relecture', () => {
  it('fait l’aller-retour sans perte', () => {
    expect(stateToLines(linesToState(LINES))).toEqual(LINES);
  });

  it('ignore une clé orpheline au milieu (au lieu de la relire)', () => {
    // État d'un lien forgé : la ligne 1 manque, la ligne 2 existe.
    const state = { amount0: '1000', rate0: '20', amount2: '999', rate2: '20' };
    expect(stateToLines(state)).toEqual([{ amount: '1000', rate: '20' }]);
  });

  it('retombe sur les valeurs par défaut si aucune ligne complète', () => {
    expect(stateToLines({})).toEqual(INITIAL_LINES);
    expect(stateToLines({ amount0: '100' })).toEqual(INITIAL_LINES);
  });

  it('lit au plus le plafond de partage', () => {
    const state: Record<string, string> = {};
    for (let index = 0; index < MAX_SHARED_LINES + 5; index += 1) {
      state[`amount${index}`] = String(index);
      state[`rate${index}`] = '20';
    }
    expect(stateToLines(state)).toHaveLength(MAX_SHARED_LINES);
  });
});

describe('pruneOrphanKeys — correctif des lignes fantômes', () => {
  it('signale les clés de la ligne supprimée', () => {
    const initial = linesToState(LINES); // 3 lignes
    const afterRemove = linesToState([
      { amount: '1000', rate: '20' },
      { amount: '0', rate: '20' },
    ]); // 2 lignes
    const orphans = pruneOrphanKeys(initial, [
      { amount: '1000', rate: '20' },
      { amount: '0', rate: '20' },
    ]);
    expect(orphans.sort()).toEqual(['amount2', 'rate2']);
    // Les clés conservées ne doivent PAS être supprimées.
    for (const key of Object.keys(afterRemove)) {
      expect(orphans).not.toContain(key);
    }
  });

  it('ne signale rien quand rien ne change', () => {
    const state = linesToState(LINES);
    expect(pruneOrphanKeys(state, LINES)).toEqual([]);
  });

  it('signale les clés au-delà du plafond (état hérité d’un lien forgé)', () => {
    const state: Record<string, string> = linesToState(INITIAL_LINES);
    state.amount9 = '5';
    state.rate9 = '20';
    expect(pruneOrphanKeys(state, INITIAL_LINES).sort()).toEqual(['amount9', 'rate9']);
  });

  it('ignore les clés qui ne sont pas des clés de ligne', () => {
    const state = { ...linesToState(INITIAL_LINES), note: 'x' };
    expect(pruneOrphanKeys(state, INITIAL_LINES)).toEqual([]);
  });

  it('nettoyer puis relire donne EXACTEMENT les lignes voulues (non-régression)', () => {
    // Reproduit le défaut : 3 lignes, suppression de la ligne du milieu.
    let state = linesToState(LINES);
    const remaining = [
      { amount: '1000', rate: '20' },
      { amount: '0', rate: '20' },
    ];

    // Ancien comportement : on réécrivait sans supprimer -> lignes fantômes.
    const buggy = { ...state, ...linesToState(remaining) };
    expect(stateToLines(buggy)).toHaveLength(3);

    // Nouveau comportement : on supprime les clés orphelines.
    for (const key of pruneOrphanKeys(state, remaining)) delete state[key];
    state = { ...state, ...linesToState(remaining) };
    expect(stateToLines(state)).toEqual(remaining);
    expect(stateToLines(state)).toHaveLength(2);
  });
});

describe('canAddLine / addLine — plafond de partage', () => {
  it('autorise l’ajout sous le plafond', () => {
    expect(canAddLine(INITIAL_LINES)).toBe(true);
  });

  it('refuse l’ajout au plafond en retournant le MÊME tableau', () => {
    const full: LineState[] = Array.from({ length: MAX_SHARED_LINES }, (_, index) => ({
      amount: String(index),
      rate: '20',
    }));
    expect(canAddLine(full)).toBe(false);
    expect(addLine(full)).toBe(full); // identité = refus détectable par l'appelant
  });

  it('ajoute une ligne à 0 en reprenant le taux de la dernière ligne', () => {
    const next = addLine([{ amount: '500', rate: '5.5' }]);
    expect(next).toHaveLength(2);
    expect(next[1]).toEqual({ amount: '0', rate: '5.5' });
  });

  it('retombe sur 20 % si le taux précédent est invalide', () => {
    expect(addLine([{ amount: '500', rate: '' }])[1]).toEqual({ amount: '0', rate: '20' });
    expect(addLine([{ amount: '500', rate: 'abc' }])[1]).toEqual({ amount: '0', rate: '20' });
  });

  it('ne modifie jamais le tableau d’origine', () => {
    const original: LineState[] = [{ amount: '1', rate: '20' }];
    const next = addLine(original);
    expect(original).toHaveLength(1);
    expect(next).not.toBe(original);
  });
});

describe('removeLine — suppression réelle', () => {
  it('supprime la ligne demandée', () => {
    expect(removeLine(LINES, 1)).toEqual([
      { amount: '1000', rate: '20' },
      { amount: '0', rate: '20' },
    ]);
  });

  it('refuse de supprimer la dernière ligne restante', () => {
    const one: LineState[] = [{ amount: '1', rate: '20' }];
    expect(removeLine(one, 0)).toBe(one);
  });

  it('ignore un index hors bornes sans lever', () => {
    expect(removeLine(LINES, -1)).toBe(LINES);
    expect(removeLine(LINES, 3)).toBe(LINES);
    expect(removeLine(LINES, 1.5)).toBe(LINES);
  });

  it('supprimer toutes les lignes sauf une conserve la bonne', () => {
    let current = LINES;
    current = removeLine(current, 0);
    current = removeLine(current, 1);
    expect(current).toEqual([{ amount: '200', rate: '5.5' }]);
  });
});

describe('parseLines — validation des saisies', () => {
  it('convertit des saisies valides (virgule décimale acceptée)', () => {
    expect(parseLines([{ amount: '100', rate: '5,5' }])).toEqual([
      { amountHt: 100, ratePercent: 5.5 },
    ]);
  });

  it('retourne null dès qu’une saisie est vide ou non numérique', () => {
    expect(parseLines([{ amount: '', rate: '20' }])).toBeNull();
    expect(parseLines([{ amount: '100', rate: 'abc' }])).toBeNull();
    expect(parseLines([{ amount: '100', rate: 'Infinity' }])).toBeNull();
  });

  it('retourne null pour une liste vide (pas de total trompeur)', () => {
    expect(parseLines([])).toBeNull();
  });
});
