import { shiftDate } from './logic';

/**
 * Tests calcul de date d'échéance — cas connus calculés à la main + cas limites.
 *
 * Les dates de référence sont choisies pour couvrir : un mois de 31 jours, un
 * mois de 28 jours, une année bissextile, un week-end et un passage d'année.
 */
describe('shiftDate — jours calendaires', () => {
  it('ajoute 30 jours (2026-01-01 → 2026-01-31)', () => {
    const result = shiftDate({ startDate: '2026-01-01', amount: 30, unit: 'jours' });
    expect(result).not.toBeNull();
    expect(result!.date).toBe('2026-01-31');
    expect(result!.totalDays).toBe(30);
  });

  it('recule de 10 jours (2026-03-15 → 2026-03-05)', () => {
    const result = shiftDate({ startDate: '2026-03-15', amount: -10, unit: 'jours' });
    expect(result!.date).toBe('2026-03-05');
    expect(result!.totalDays).toBe(-10);
  });

  it('franchit une année (2026-12-25 + 10 jours → 2027-01-04)', () => {
    const result = shiftDate({ startDate: '2026-12-25', amount: 10, unit: 'jours' });
    expect(result!.date).toBe('2027-01-04');
  });

  it('gère le 29 février d’une année bissextile (2024-02-28 + 1 jour)', () => {
    const result = shiftDate({ startDate: '2024-02-28', amount: 1, unit: 'jours' });
    expect(result!.date).toBe('2024-02-29');
  });

  it('un décalage nul retourne la date de départ', () => {
    const result = shiftDate({ startDate: '2026-11-14', amount: 0, unit: 'jours' });
    expect(result!.date).toBe('2026-11-14');
    expect(result!.totalDays).toBe(0);
    expect(result!.businessDays).toBe(0);
  });
});

describe('shiftDate — jours ouvrés', () => {
  it('avance de 5 jours ouvrés depuis un lundi (2026-11-09 → 2026-11-16)', () => {
    // 2026-11-09 est un lundi ; +5 jours ouvrés = lundi suivant.
    const result = shiftDate({ startDate: '2026-11-09', amount: 5, unit: 'jours-ouvres' });
    expect(result!.date).toBe('2026-11-16');
    expect(result!.totalDays).toBe(7);
    expect(result!.businessDays).toBe(5);
  });

  it('saute le week-end (vendredi + 1 jour ouvré = lundi)', () => {
    // 2026-11-13 est un vendredi.
    const result = shiftDate({ startDate: '2026-11-13', amount: 1, unit: 'jours-ouvres' });
    expect(result!.date).toBe('2026-11-16');
    expect(result!.totalDays).toBe(3);
  });

  it('recule d’un jour ouvré depuis un lundi = vendredi précédent', () => {
    // 2026-11-16 est un lundi ; -1 jour ouvré = vendredi 13.
    const result = shiftDate({ startDate: '2026-11-16', amount: -1, unit: 'jours-ouvres' });
    expect(result!.date).toBe('2026-11-13');
    expect(result!.totalDays).toBe(-3);
    expect(result!.businessDays).toBe(-1);
  });

  it('un décalage de 0 jour ouvré ne bouge pas, même un samedi', () => {
    // 2026-11-14 est un samedi.
    const result = shiftDate({ startDate: '2026-11-14', amount: 0, unit: 'jours-ouvres' });
    expect(result!.date).toBe('2026-11-14');
    expect(result!.businessDays).toBe(0);
  });

  it('compte 10 jours ouvrés sur deux semaines pleines (lundi → vendredi +2 semaines)', () => {
    const result = shiftDate({ startDate: '2026-11-09', amount: 10, unit: 'jours-ouvres' });
    expect(result!.date).toBe('2026-11-23');
    expect(result!.totalDays).toBe(14);
  });
});

describe('shiftDate — mois et années (bornage du jour)', () => {
  it('ajoute 1 mois en bornant le jour (2026-01-31 → 2026-02-28)', () => {
    const result = shiftDate({ startDate: '2026-01-31', amount: 1, unit: 'mois' });
    expect(result!.date).toBe('2026-02-28');
  });

  it('ajoute 1 mois sur une année bissextile (2024-01-31 → 2024-02-29)', () => {
    const result = shiftDate({ startDate: '2024-01-31', amount: 1, unit: 'mois' });
    expect(result!.date).toBe('2024-02-29');
  });

  it('ajoute 3 mois (2026-11-14 → 2027-02-14)', () => {
    const result = shiftDate({ startDate: '2026-11-14', amount: 3, unit: 'mois' });
    expect(result!.date).toBe('2027-02-14');
  });

  it('recule de 2 mois (2026-03-31 → 2026-01-31)', () => {
    const result = shiftDate({ startDate: '2026-03-31', amount: -2, unit: 'mois' });
    expect(result!.date).toBe('2026-01-31');
  });

  it('ajoute 1 an (2026-11-14 → 2027-11-14)', () => {
    const result = shiftDate({ startDate: '2026-11-14', amount: 1, unit: 'annees' });
    expect(result!.date).toBe('2027-11-14');
    expect(result!.totalDays).toBe(365);
  });

  it('borne le 29 février sur une année non bissextile (2024-02-29 + 1 an → 2025-02-28)', () => {
    const result = shiftDate({ startDate: '2024-02-29', amount: 1, unit: 'annees' });
    expect(result!.date).toBe('2025-02-28');
  });
});

describe('shiftDate — jour de la semaine', () => {
  it('retourne le bon jour de semaine (2026-11-14 = samedi = 6)', () => {
    const result = shiftDate({ startDate: '2026-11-14', amount: 0, unit: 'jours' });
    expect(result!.weekday).toBe(6);
  });

  it('retourne lundi = 1 (2026-11-16)', () => {
    const result = shiftDate({ startDate: '2026-11-16', amount: 0, unit: 'jours' });
    expect(result!.weekday).toBe(1);
  });
});

describe('shiftDate — entrées invalides', () => {
  it('retourne null pour une date mal formée', () => {
    expect(shiftDate({ startDate: 'pas-une-date', amount: 1, unit: 'jours' })).toBeNull();
    expect(shiftDate({ startDate: '2026-1-1', amount: 1, unit: 'jours' })).toBeNull();
  });

  it('retourne null pour une date inexistante', () => {
    expect(shiftDate({ startDate: '2026-02-30', amount: 1, unit: 'jours' })).toBeNull();
    expect(shiftDate({ startDate: '2026-13-01', amount: 1, unit: 'jours' })).toBeNull();
  });

  it('retourne null pour un décalage non entier', () => {
    expect(shiftDate({ startDate: '2026-11-14', amount: 1.5, unit: 'jours' })).toBeNull();
  });

  it('retourne null pour NaN et Infinity', () => {
    expect(shiftDate({ startDate: '2026-11-14', amount: NaN, unit: 'jours' })).toBeNull();
    expect(shiftDate({ startDate: '2026-11-14', amount: Infinity, unit: 'jours' })).toBeNull();
  });

  it('retourne null pour une unité inconnue', () => {
    // @ts-expect-error — unité volontairement invalide
    expect(shiftDate({ startDate: '2026-11-14', amount: 1, unit: 'semaines' })).toBeNull();
  });

  it('retourne null au-delà des bornes de sécurité', () => {
    expect(shiftDate({ startDate: '2026-11-14', amount: 10_001, unit: 'jours' })).toBeNull();
    expect(shiftDate({ startDate: '2026-11-14', amount: 1_201, unit: 'mois' })).toBeNull();
    expect(shiftDate({ startDate: '2026-11-14', amount: 101, unit: 'annees' })).toBeNull();
  });

  it('accepte exactement la borne haute', () => {
    expect(shiftDate({ startDate: '2026-11-14', amount: 10_000, unit: 'jours' })).not.toBeNull();
  });
});
