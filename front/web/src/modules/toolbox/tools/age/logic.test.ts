import { ageAt } from './logic';

/**
 * Tests âge — cas connus calculés à la main + cas limites.
 */
describe('ageAt', () => {
  it('calcule un âge exact (né 1990-05-15, au 2026-05-15 = 36 ans)', () => {
    const result = ageAt('1990-05-15', '2026-05-15');
    expect(result).toEqual({ years: 36, months: 0, days: 0, totalDays: 13149 });
  });

  it('calcule un âge avec mois et jours (né 1990-05-15, au 2026-11-08)', () => {
    const result = ageAt('1990-05-15', '2026-11-08');
    expect(result?.years).toBe(36);
    expect(result?.months).toBe(5);
    expect(result?.days).toBe(24);
  });

  it('gère le jour d’anniversaire non encore atteint (né 2000-12-31, au 2026-01-01)', () => {
    const result = ageAt('2000-12-31', '2026-01-01');
    expect(result).toEqual({ years: 25, months: 0, days: 1, totalDays: 9132 });
  });

  it('gère une naissance un 29 février (né 2000-02-29, au 2026-02-28)', () => {
    const result = ageAt('2000-02-29', '2026-02-28');
    expect(result).toEqual({ years: 26, months: 0, days: 0, totalDays: 9496 });
  });

  it('retourne null pour une date de naissance dans le futur', () => {
    expect(ageAt('2030-01-01', '2026-11-08')).toBeNull();
  });

  it('retourne null pour une date invalide', () => {
    expect(ageAt('pas-une-date', '2026-11-08')).toBeNull();
    expect(ageAt('1990-05-15', 'invalide')).toBeNull();
  });
});
