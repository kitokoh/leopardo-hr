import { hourlyRate, monthlyGrossFromHourly } from './logic';

/**
 * Tests taux horaire — cas connus calculés à la main + cas limites.
 */
describe('hourlyRate', () => {
  it('calcule un taux horaire (2000 € / 35 h par semaine)', () => {
    // 35 × 52 / 12 = 151,666… h/mois ; 2000 / 151,666… = 13,1868…
    expect(hourlyRate(2000, 35)).toBeCloseTo(13.1868, 3);
  });

  it('calcule un taux horaire (3000 € / 39 h par semaine)', () => {
    // 39 × 52 / 12 = 169 h/mois ; 3000 / 169 = 17,7514…
    expect(hourlyRate(3000, 39)).toBeCloseTo(17.7515, 3);
  });

  it('gère un temps partiel (1000 € / 20 h par semaine)', () => {
    // 20 × 52 / 12 = 86,666… h/mois ; 1000 / 86,666… = 11,5384…
    expect(hourlyRate(1000, 20)).toBeCloseTo(11.5385, 3);
  });

  it('retourne null pour 0 heure par semaine (division par zéro)', () => {
    expect(hourlyRate(2000, 0)).toBeNull();
  });

  it('retourne null pour un brut négatif ou non fini', () => {
    expect(hourlyRate(-100, 35)).toBeNull();
    expect(hourlyRate(Number.NaN, 35)).toBeNull();
    expect(hourlyRate(2000, Number.POSITIVE_INFINITY)).toBeNull();
  });
});

describe('monthlyGrossFromHourly', () => {
  it('inverse le calcul (13,1868 €/h × 35 h = 2000 €)', () => {
    expect(monthlyGrossFromHourly(13.1868, 35)).toBeCloseTo(1999.998, 2);
  });

  it('retourne null pour un taux négatif ou 0 heure', () => {
    expect(monthlyGrossFromHourly(-5, 35)).toBeNull();
    expect(monthlyGrossFromHourly(15, 0)).toBeNull();
  });
});
