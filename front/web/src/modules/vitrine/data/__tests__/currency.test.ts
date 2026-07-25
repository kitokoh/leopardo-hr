import { CURRENCY_OPTIONS, DEFAULT_CURRENCY_OPTION, convertEurPrice, findCurrencyOption } from '../currency';

describe('currency (PA2-MKT-003)', () => {
  it('exposes the EUR option as the default (no conversion)', () => {
    expect(DEFAULT_CURRENCY_OPTION.currency).toBe('EUR');
    expect(DEFAULT_CURRENCY_OPTION.rateFromEur).toBe(1);
  });

  it('covers the documented target markets (DZ, MA, TN, FR, TR, CA)', () => {
    const countries = CURRENCY_OPTIONS.map((o) => o.country);
    expect(countries).toEqual(expect.arrayContaining(['FR', 'DZ', 'MA', 'TN', 'TR', 'CA']));
  });

  it('falls back to EUR for an unknown country code', () => {
    expect(findCurrencyOption('ZZ')).toBe(DEFAULT_CURRENCY_OPTION);
    expect(findCurrencyOption(null)).toBe(DEFAULT_CURRENCY_OPTION);
  });

  it('resolves a known country case-insensitively', () => {
    expect(findCurrencyOption('dz').currency).toBe('DZD');
    expect(findCurrencyOption('TR').currency).toBe('TRY');
  });

  it('returns null for non-numeric plan prices (Enterprise "Sur devis" etc.)', () => {
    const dz = findCurrencyOption('DZ');
    expect(convertEurPrice('Sur devis', dz)).toBeNull();
    expect(convertEurPrice('Custom', dz)).toBeNull();
  });

  it('converts a numeric EUR price using the target rate', () => {
    const dz = findCurrencyOption('DZ');
    const result = convertEurPrice('99', dz);
    expect(result).not.toBeNull();
    // 99 * 148 ~= 14652, rounded to 0 decimals for a large-denomination currency
    expect(Number(result!.replace(/,/g, ''))).toBeGreaterThan(10000);
  });

  it('keeps EUR conversion as an identity mapping', () => {
    const fr = findCurrencyOption('FR');
    expect(convertEurPrice('29', fr)).toBe('29');
  });
});
