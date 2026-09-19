import {
  STOCK_REASON_CODES,
  formatMinor,
  parseMajorToMinor,
  reasonDirection,
  signedQuantityDelta,
} from '../commerce-format';

/**
 * BC-17 RETAIL (#7675) — helpers purs de l'espace vendeur Commerce.
 *
 * Le backend travaille en minor units (entiers) et le delta de stock envoyé
 * à `POST /retail/stock/movements` est SIGNÉ : ces tests verrouillent la
 * conversion affichage/saisie et le mapping motif → direction → signe.
 */
describe('commerce-format — formatMinor', () => {
  it('affiche les minor units en unités majeures avec 2 décimales et la devise', () => {
    expect(formatMinor('en', 1250, 'XOF')).toBe('12.50 XOF');
    expect(formatMinor('en', 0, 'EUR')).toBe('0.00 EUR');
    expect(formatMinor('en', 999, 'USD')).toBe('9.99 USD');
  });

  it('respecte la locale pour le séparateur décimal', () => {
    expect(formatMinor('fr', 1250, 'XOF')).toBe('12,50 XOF');
  });

  it('gère les montants négatifs (écart de caisse)', () => {
    expect(formatMinor('en', -500, 'XOF')).toBe('-5.00 XOF');
  });
});

describe('commerce-format — parseMajorToMinor', () => {
  it('convertit une saisie décimale en minor units entières', () => {
    expect(parseMajorToMinor('12.50')).toBe(1250);
    expect(parseMajorToMinor('12,5')).toBe(1250);
    expect(parseMajorToMinor('0')).toBe(0);
    expect(parseMajorToMinor(' 3 ')).toBe(300);
  });

  it('arrondit au centime (pas de flottant qui fuit)', () => {
    expect(parseMajorToMinor('0.105')).toBe(11);
    expect(parseMajorToMinor('19.99')).toBe(1999);
  });

  it('retourne null pour une saisie invalide ou vide', () => {
    expect(parseMajorToMinor('')).toBeNull();
    expect(parseMajorToMinor('abc')).toBeNull();
    expect(parseMajorToMinor('1.2.3')).toBeNull();
  });

  it('accepte les valeurs négatives (ajustements)', () => {
    expect(parseMajorToMinor('-4.25')).toBe(-425);
  });
});

describe('commerce-format — mapping reason_code (miroir RetailStockReasonCode)', () => {
  it('couvre exactement les 7 motifs du backend', () => {
    expect([...STOCK_REASON_CODES].sort()).toEqual(
      ['adjustment', 'loss', 'purchase', 'return', 'sale', 'transfer_in', 'transfer_out'].sort(),
    );
  });

  it('classe les motifs en entrée / sortie / ajustement signé', () => {
    expect(reasonDirection('purchase')).toBe('in');
    expect(reasonDirection('return')).toBe('in');
    expect(reasonDirection('transfer_in')).toBe('in');
    expect(reasonDirection('sale')).toBe('out');
    expect(reasonDirection('transfer_out')).toBe('out');
    expect(reasonDirection('loss')).toBe('out');
    expect(reasonDirection('adjustment')).toBe('signed');
  });

  it('signe le delta selon la direction (quantité saisie positive)', () => {
    expect(signedQuantityDelta('purchase', 5)).toBe(5);
    expect(signedQuantityDelta('sale', 5)).toBe(-5);
    expect(signedQuantityDelta('loss', 2.5)).toBe(-2.5);
  });

  it('force la magnitude même si l’opérateur saisit un signe', () => {
    expect(signedQuantityDelta('purchase', -5)).toBe(5);
    expect(signedQuantityDelta('transfer_out', -3)).toBe(-3);
  });

  it('laisse le signe saisi tel quel pour un ajustement', () => {
    expect(signedQuantityDelta('adjustment', -4)).toBe(-4);
    expect(signedQuantityDelta('adjustment', 4)).toBe(4);
  });
});
