import { durationBetween } from './logic';

/**
 * Tests durée entre deux dates — cas connus calculés à la main + cas limites.
 */
describe('durationBetween', () => {
  it('calcule une durée simple (2026-01-01 → 2026-01-31 = 30 jours)', () => {
    const result = durationBetween('2026-01-01', '2026-01-31');
    expect(result).toEqual({ years: 0, months: 0, days: 30, totalDays: 30 });
  });

  it('calcule une année complète (2025-03-15 → 2026-03-15 = 1 an)', () => {
    const result = durationBetween('2025-03-15', '2026-03-15');
    expect(result).toEqual({ years: 1, months: 0, days: 0, totalDays: 365 });
  });

  it('gère une année bissextile (2024-02-29 → 2025-02-28 = 1 an)', () => {
    const result = durationBetween('2024-02-29', '2025-02-28');
    expect(result).toEqual({ years: 1, months: 0, days: 0, totalDays: 365 });
  });

  it('borne le jour au mois d’arrivée (2026-01-31 → 2026-02-28 = 1 mois)', () => {
    const result = durationBetween('2026-01-31', '2026-02-28');
    expect(result).toEqual({ years: 0, months: 1, days: 0, totalDays: 28 });
  });

  it('emprunte les jours du mois précédent (2026-01-31 → 2026-03-01)', () => {
    // 31 janv → 1 mars : 1 mois + 1 jour (février 2026 = 28 jours)
    const result = durationBetween('2026-01-31', '2026-03-01');
    expect(result).toEqual({ years: 0, months: 1, days: 1, totalDays: 29 });
  });

  it('normalise l’ordre (dates inversées donnent la même durée)', () => {
    const forward = durationBetween('2026-01-01', '2026-06-15');
    const backward = durationBetween('2026-06-15', '2026-01-01');
    expect(backward).toEqual(forward);
  });

  it('retourne zéro pour des dates identiques', () => {
    expect(durationBetween('2026-11-08', '2026-11-08')).toEqual({
      years: 0,
      months: 0,
      days: 0,
      totalDays: 0,
    });
  });

  it('retourne null pour une date invalide', () => {
    expect(durationBetween('pas-une-date', '2026-01-01')).toBeNull();
    expect(durationBetween('2026-01-01', '2026-13-01')).toBeNull();
    expect(durationBetween('2026-02-30', '2026-03-01')).toBeNull();
  });
});
