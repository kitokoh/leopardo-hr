import { canSync, deriveConnectionState } from '../connection-state';

/**
 * #8493 / #8494 (I-06, I-07) — etat de connexion clair (Partie 7).
 */
describe('connection-state (#8493/#8494)', () => {
  it('reports offline when the browser is offline, regardless of pending work', () => {
    expect(
      deriveConnectionState({ navigatorOnline: false, lastSyncFailed: false, hasPendingOperations: false }),
    ).toBe('offline');
    expect(
      deriveConnectionState({ navigatorOnline: false, lastSyncFailed: true, hasPendingOperations: true }),
    ).toBe('offline');
  });

  it('reports degraded when online but sync failed or work is pending', () => {
    expect(
      deriveConnectionState({ navigatorOnline: true, lastSyncFailed: true, hasPendingOperations: false }),
    ).toBe('degraded');
    expect(
      deriveConnectionState({ navigatorOnline: true, lastSyncFailed: false, hasPendingOperations: true }),
    ).toBe('degraded');
  });

  it('reports online only when connected and up to date', () => {
    expect(
      deriveConnectionState({ navigatorOnline: true, lastSyncFailed: false, hasPendingOperations: false }),
    ).toBe('online');
  });

  it('never attempts a sync while offline', () => {
    expect(canSync('offline')).toBe(false);
    expect(canSync('degraded')).toBe(true);
    expect(canSync('online')).toBe(true);
  });
});
