import { convertTemperature, convertUnit } from './logic';

/**
 * Tests convertisseur d'unités — facteurs exacts + cas limites.
 */
describe('convertUnit', () => {
  it('convertit des longueurs (1 in = 2,54 cm)', () => {
    expect(convertUnit('longueur', 'in', 'cm', 1)).toBeCloseTo(2.54, 10);
  });

  it('convertit des longueurs (1 mi = 1,609344 km)', () => {
    expect(convertUnit('longueur', 'mi', 'km', 1)).toBeCloseTo(1.609344, 10);
  });

  it('convertit des masses (1 lb = 0,45359237 kg)', () => {
    expect(convertUnit('masse', 'lb', 'kg', 1)).toBeCloseTo(0.45359237, 10);
  });

  it('convertit des volumes (1 gal US = 3,785411784 L)', () => {
    expect(convertUnit('volume', 'gal_us', 'l', 1)).toBeCloseTo(3.785411784, 10);
  });

  it('convertit des surfaces (1 ha = 10 000 m²)', () => {
    expect(convertUnit('surface', 'ha', 'm2', 1)).toBeCloseTo(10000, 6);
  });

  it('convertit des vitesses (36 km/h = 10 m/s)', () => {
    expect(convertUnit('vitesse', 'kmh', 'mps', 36)).toBeCloseTo(10, 10);
  });

  it('retourne la même valeur pour une conversion identique', () => {
    expect(convertUnit('longueur', 'm', 'm', 42)).toBeCloseTo(42, 10);
  });

  it('retourne null pour une unité inconnue', () => {
    expect(convertUnit('longueur', 'parsec', 'm', 1)).toBeNull();
  });

  it('retourne null pour une valeur non finie', () => {
    expect(convertUnit('longueur', 'm', 'km', Number.NaN)).toBeNull();
    expect(convertUnit('longueur', 'm', 'km', Number.POSITIVE_INFINITY)).toBeNull();
  });

  it('gère les valeurs négatives (températures, altitudes)', () => {
    expect(convertUnit('longueur', 'm', 'cm', -1)).toBeCloseTo(-100, 10);
  });
});

describe('convertTemperature', () => {
  it('convertit 0 °C = 32 °F', () => {
    expect(convertTemperature('c', 'f', 0)).toBeCloseTo(32, 10);
  });

  it('convertit 100 °C = 212 °F', () => {
    expect(convertTemperature('c', 'f', 100)).toBeCloseTo(212, 10);
  });

  it('convertit 0 °C = 273,15 K', () => {
    expect(convertTemperature('c', 'k', 0)).toBeCloseTo(273.15, 10);
  });

  it('convertit -40 °C = -40 °F (point de croisement)', () => {
    expect(convertTemperature('c', 'f', -40)).toBeCloseTo(-40, 10);
  });

  it('convertit 32 °F = 0 °C', () => {
    expect(convertTemperature('f', 'c', 32)).toBeCloseTo(0, 10);
  });

  it('retourne null pour une unité de température inconnue', () => {
    expect(convertTemperature('x', 'c', 0)).toBeNull();
  });
});
