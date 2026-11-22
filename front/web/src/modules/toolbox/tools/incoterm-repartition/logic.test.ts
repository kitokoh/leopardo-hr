import { COST_ITEMS, INCOTERMS, isIncoterm, splitByIncoterm } from './logic';

/**
 * Tests répartition Incoterm — table déterministe, cas connus + limites.
 */
describe('splitByIncoterm', () => {
  it('EXW : tout à la charge de l’acheteur', () => {
    const result = splitByIncoterm('EXW');

    expect(result?.sellerItems).toBe(0);
    expect(result?.buyerItems).toBe(COST_ITEMS.length);
    expect(result?.items.every((entry) => entry.bearer === 'buyer')).toBe(true);
  });

  it('DDP : tout à la charge du vendeur', () => {
    const result = splitByIncoterm('DDP');

    expect(result?.sellerItems).toBe(COST_ITEMS.length);
    expect(result?.buyerItems).toBe(0);
    expect(result?.items.every((entry) => entry.bearer === 'seller')).toBe(true);
  });

  it('FOB : export vendeur, transport et import acheteur', () => {
    const result = splitByIncoterm('FOB');
    const byItem = Object.fromEntries((result?.items ?? []).map((entry) => [entry.item, entry.bearer]));

    expect(byItem.exportClearance).toBe('seller');
    expect(byItem.mainCarriage).toBe('buyer');
    expect(byItem.insurance).toBe('buyer');
    expect(byItem.importClearance).toBe('buyer');
    expect(byItem.importDuty).toBe('buyer');
  });

  it('CIF : transport ET assurance au vendeur, import à l’acheteur', () => {
    const result = splitByIncoterm('CIF');
    const byItem = Object.fromEntries((result?.items ?? []).map((entry) => [entry.item, entry.bearer]));

    expect(byItem.mainCarriage).toBe('seller');
    expect(byItem.insurance).toBe('seller');
    expect(byItem.importDuty).toBe('buyer');
  });

  it('CFR : transport vendeur mais PAS l’assurance', () => {
    const result = splitByIncoterm('CFR');
    const byItem = Object.fromEntries((result?.items ?? []).map((entry) => [entry.item, entry.bearer]));

    expect(byItem.mainCarriage).toBe('seller');
    expect(byItem.insurance).toBe('buyer');
  });

  it('DAP : import non dédouané par le vendeur', () => {
    const result = splitByIncoterm('DAP');
    const byItem = Object.fromEntries((result?.items ?? []).map((entry) => [entry.item, entry.bearer]));

    expect(byItem.mainCarriage).toBe('seller');
    expect(byItem.importClearance).toBe('buyer');
    expect(byItem.importDuty).toBe('buyer');
  });

  it('normalise la casse et les espaces', () => {
    expect(splitByIncoterm('  cif  ')?.incoterm).toBe('CIF');
    expect(splitByIncoterm('ddp')?.incoterm).toBe('DDP');
  });

  it('retourne null sur un code inconnu (fail-closed)', () => {
    expect(splitByIncoterm('XYZ')).toBeNull();
    expect(splitByIncoterm('')).toBeNull();
    expect(splitByIncoterm('DDU')).toBeNull();
  });

  it('couvre les 7 Incoterms supportés', () => {
    for (const incoterm of INCOTERMS) {
      expect(splitByIncoterm(incoterm)).not.toBeNull();
    }
  });

  it('la prise en charge vendeur est monotone de EXW à DDP', () => {
    const counts = INCOTERMS.map((incoterm) => splitByIncoterm(incoterm)?.sellerItems ?? -1);

    for (let index = 1; index < counts.length; index += 1) {
      expect(counts[index]).toBeGreaterThanOrEqual(counts[index - 1]);
    }
  });
});

describe('isIncoterm', () => {
  it('reconnaît les codes du registre', () => {
    expect(isIncoterm('FOB')).toBe(true);
    expect(isIncoterm('fob')).toBe(false);
    expect(isIncoterm('DDU')).toBe(false);
  });
});
