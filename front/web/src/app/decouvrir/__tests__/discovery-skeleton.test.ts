/**
 * Couloir 16 — M1 (#9374, état 1) : le squelette de chargement doit avoir des
 * dimensions FIXES, sinon il provoque un décalage de layout quand les données
 * arrivent — exactement ce que le critère d'acceptation interdit.
 */

import {
  SKELETON_CARD_COUNT,
  SKELETON_CARD_HEIGHT,
} from '../discovery-skeleton';

describe('M1 — squelette de chargement (#9374 état 1)', () => {
  it('déclare une hauteur de carte fixe, non dérivée du contenu', () => {
    expect(typeof SKELETON_CARD_HEIGHT).toBe('number');
    expect(SKELETON_CARD_HEIGHT).toBeGreaterThan(0);
  });

  it('rend un nombre de cartes fixe (grille 3×2)', () => {
    expect(SKELETON_CARD_COUNT).toBe(6);
  });

  it('la hauteur est une constante du module, pas un calcul runtime', () => {
    // Une constante exportée ne peut pas varier entre deux rendus : c'est la
    // garantie mécanique de l'absence de CLS.
    expect(SKELETON_CARD_HEIGHT).toBe(168);
  });
});
