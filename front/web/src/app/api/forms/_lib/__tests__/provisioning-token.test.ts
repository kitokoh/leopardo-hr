import { isValidProvisioningToken } from '../provisioning-token';

describe('isValidProvisioningToken', () => {
  it('accepts a Laravel Str::random-style base62 token', () => {
    expect(isValidProvisioningToken('Aa09Zz'.repeat(10) + 'Aa09')).toBe(true);
  });

  it('rejects tokens with the wrong length', () => {
    expect(isValidProvisioningToken('a'.repeat(63))).toBe(false);
    expect(isValidProvisioningToken('a'.repeat(65))).toBe(false);
  });

  it('rejects characters outside the base62 alphabet', () => {
    expect(isValidProvisioningToken('a'.repeat(63) + '-')).toBe(false);
    expect(isValidProvisioningToken('a'.repeat(63) + ' ')).toBe(false);
  });
});
