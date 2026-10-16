import { renderHook, act, waitFor } from '@testing-library/react';
import { useOfflineSync } from '../use-offline-sync';

/**
 * #8493 / #8494 (I-06, I-07) — preuves du câblage hors-ligne partagé :
 *  - hors ligne, l'opération est mise en file (pas d'envoi) ;
 *  - à la reconnexion, la file est rejouée ;
 *  - idempotence : deux dispatch identiques ne créent pas deux opérations ;
 *  - un échec d'envoi est conservé (diagnostic) et rejouable.
 */

function setOnline(value: boolean) {
  Object.defineProperty(window.navigator, 'onLine', { value, configurable: true });
}

describe('useOfflineSync (#8493/#8494)', () => {
  beforeEach(() => {
    setOnline(true);
  });

  it('sends immediately when online', async () => {
    const sender = jest.fn().mockResolvedValue(undefined);
    const { result } = renderHook(() => useOfflineSync(sender));

    let outcome: string | undefined;
    await act(async () => {
      outcome = await result.current.dispatch('kitchen.transition', { orderId: 1, action: 'ready' });
    });

    expect(outcome).toBe('sent');
    expect(sender).toHaveBeenCalledTimes(1);
    expect(result.current.pendingCount).toBe(0);
  });

  it('queues the operation when offline and replays on reconnect', async () => {
    setOnline(false);
    const sender = jest.fn().mockResolvedValue(undefined);
    const { result } = renderHook(() => useOfflineSync(sender));

    await act(async () => {
      await result.current.dispatch('kitchen.transition', { orderId: 2, action: 'start' });
    });

    expect(sender).not.toHaveBeenCalled();
    expect(result.current.pendingCount).toBe(1);
    expect(result.current.connectionState).toBe('offline');

    // Reconnexion → rejeu automatique.
    await act(async () => {
      setOnline(true);
      window.dispatchEvent(new Event('online'));
    });

    await waitFor(() => expect(sender).toHaveBeenCalledTimes(1));
    await waitFor(() => expect(result.current.pendingCount).toBe(0));
  });

  it('is idempotent: two identical dispatches do not duplicate', async () => {
    setOnline(false);
    const sender = jest.fn().mockResolvedValue(undefined);
    const { result } = renderHook(() => useOfflineSync(sender));

    await act(async () => {
      await result.current.dispatch('kitchen.transition', { orderId: 3, action: 'ready' });
      await result.current.dispatch('kitchen.transition', { orderId: 3, action: 'ready' });
    });

    expect(result.current.pendingCount).toBe(1);
  });

  it('keeps a failed send for retry (diagnosable)', async () => {
    const sender = jest.fn().mockRejectedValue(new Error('network down'));
    const { result } = renderHook(() => useOfflineSync(sender));

    await act(async () => {
      await result.current.dispatch('kitchen.transition', { orderId: 4, action: 'ready' });
    });

    expect(result.current.pendingCount).toBe(1);
    expect(result.current.pending[0].lastError).toBe('network down');
    expect(result.current.connectionState).toBe('degraded');
  });
});
