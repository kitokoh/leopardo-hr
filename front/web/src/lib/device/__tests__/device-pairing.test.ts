import {
  consumePairingCode,
  generatePairingCode,
  isDeviceActive,
  isPairingCodeUsable,
  pairDevice,
  revokeDevice,
} from '../device-pairing';

/**
 * #8499 (I-12) — preuves du noyau d'enrolement : code a usage unique et
 * expirant, affectation, revocation, et aucune creance reutilisable.
 */
describe('device-pairing (#8499)', () => {
  const rng = () => 0.5; // deterministe

  it('generates a code from the unambiguous alphabet with the right length', () => {
    const record = generatePairingCode(1000, 60_000, rng);
    expect(record.code).toHaveLength(8);
    expect(record.code).toMatch(/^[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{8}$/);
    expect(record.expiresAt).toBe(61_000);
  });

  it('is usable before expiry and not after', () => {
    const record = generatePairingCode(1000, 60_000, rng);
    expect(isPairingCodeUsable(record, 30_000)).toBe(true);
    expect(isPairingCodeUsable(record, 61_000)).toBe(false);
  });

  it('is one-time: a consumed code can never be reused', () => {
    const record = generatePairingCode(1000, 60_000, rng);
    const consumed = consumePairingCode(record, 5_000);
    expect(consumed.usedAt).toBe(5_000);
    expect(isPairingCodeUsable(consumed, 6_000)).toBe(false);
    expect(() => consumePairingCode(consumed, 7_000)).toThrow('pairing_code_not_usable');
  });

  it('refuses an expired code (fail-closed)', () => {
    const record = generatePairingCode(1000, 60_000, rng); // expiresAt = 61_000
    expect(() => consumePairingCode(record, 61_000)).toThrow('pairing_code_not_usable');
  });

  it('pairs a device to an establishment and a role, then revokes it', () => {
    const device = pairDevice('dev-1', 'est-42', 'pos', 10_000);
    expect(device).toMatchObject({ establishmentId: 'est-42', role: 'pos', status: 'active' });
    expect(isDeviceActive(device)).toBe(true);

    const revoked = revokeDevice(device);
    expect(revoked.status).toBe('revoked');
    expect(isDeviceActive(revoked)).toBe(false);
  });
});
