import { describe, it, expect, beforeEach, vi } from 'vitest';
import { KdsQueue, type KdsStorage, type KdsTicket } from '../src/lib/kds-queue';
import { drainQueue, fetchKitchenTickets, KDS_ACTION_PATH } from '../src/lib/kds-sync';

/**
 * #8448 — la politique d'erreur de la synchronisation décide si la cuisine
 * perd des gestes. Ces tests figent les trois branches : 2xx acquitte,
 * 4xx rejette, 5xx/réseau conserve et s'arrête.
 */

class MemoryStorage implements KdsStorage {
  private value: string | null = null;
  read(): string | null {
    return this.value;
  }
  write(value: string): void {
    this.value = value;
  }
}

function ticket(overrides: Partial<KdsTicket> = {}): KdsTicket {
  return {
    orderId: 'order-1',
    reference: 'T-14',
    branchId: 7,
    status: 'received',
    lines: [{ id: 'l1', name: 'Poulet DG', quantity: 2 }],
    createdAt: '2026-10-29T10:00:00.000Z',
    updatedAt: '2026-10-29T10:00:00.000Z',
    ...overrides,
  };
}

function response(status: number, body: unknown = {}): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'Content-Type': 'application/json' },
  });
}

describe('drainQueue', () => {
  let queue: KdsQueue;

  beforeEach(() => {
    queue = new KdsQueue(new MemoryStorage(), () => Date.parse('2026-10-29T10:05:00.000Z'));
    queue.reconcile([ticket()]);
  });

  it('acknowledges a gesture the server accepted', async () => {
    queue.enqueue('order-1', 'start');
    const fetcher = vi.fn().mockResolvedValue(response(200));

    const result = await drainQueue(queue, { fetcher });

    expect(result.acknowledged).toHaveLength(1);
    expect(queue.load().pending).toHaveLength(0);
  });

  it('sends the client action id so the server can deduplicate', async () => {
    const state = queue.enqueue('order-1', 'start');
    const actionId = state!.pending[0].clientActionId;
    const fetcher = vi.fn().mockResolvedValue(response(200));

    await drainQueue(queue, { fetcher });

    const [url, init] = fetcher.mock.calls[0];
    expect(url).toBe(KDS_ACTION_PATH);
    expect(JSON.parse(init.body)).toEqual({
      client_action_id: actionId,
      order_id: 'order-1',
      action: 'start',
    });
  });

  it('rejects a 4xx definitively instead of retrying forever', async () => {
    // Un ticket supprimé ou une transition invalide ne deviendra pas valide en
    // réessayant : garder le geste bloquerait toute la file derrière lui.
    queue.enqueue('order-1', 'start');
    const fetcher = vi.fn().mockResolvedValue(response(422));

    const result = await drainQueue(queue, { fetcher });

    expect(result.rejected).toHaveLength(1);
    expect(queue.load().pending).toHaveLength(0);
  });

  it('keeps the gesture on a 5xx and stops the drain', async () => {
    // Un 5xx n'est PAS un refus : le serveur a pu appliquer le geste avant de
    // tomber, et l'idempotence couvre ce cas. On garde donc le geste.
    queue.enqueue('order-1', 'start');
    const fetcher = vi.fn().mockResolvedValue(response(503));

    const result = await drainQueue(queue, { fetcher });

    expect(result.retry).toHaveLength(1);
    expect(queue.load().pending).toHaveLength(1);
    expect(queue.load().pending[0].attempts).toBe(1);
  });

  it('keeps the gesture when the network fails', async () => {
    queue.enqueue('order-1', 'start');
    const fetcher = vi.fn().mockRejectedValue(new Error('network down'));

    const result = await drainQueue(queue, { fetcher });

    expect(result.retry).toHaveLength(1);
    expect(queue.load().pending).toHaveLength(1);
  });

  it('stops at the first transient failure to preserve ordering', async () => {
    // Deux gestes sur le même ticket : « prêt » ne doit jamais partir avant
    // « commencé », sinon le serveur reçoit une transition impossible.
    queue.enqueue('order-1', 'start');
    queue.enqueue('order-1', 'ready');
    const fetcher = vi.fn().mockResolvedValue(response(503));

    const result = await drainQueue(queue, { fetcher });

    expect(fetcher).toHaveBeenCalledTimes(1);
    expect(result.retry).toHaveLength(1);
    expect(queue.load().pending).toHaveLength(2);
  });

  it('drains several gestures in order when the server accepts them', async () => {
    queue.enqueue('order-1', 'start');
    queue.enqueue('order-1', 'ready');
    const fetcher = vi.fn().mockResolvedValue(response(200));

    const result = await drainQueue(queue, { fetcher });

    expect(result.acknowledged).toHaveLength(2);
    expect(queue.load().pending).toHaveLength(0);
    const actions = fetcher.mock.calls.map(([, init]) => JSON.parse(init.body).action);
    expect(actions).toEqual(['start', 'ready']);
  });

  it('does nothing when the queue is empty', async () => {
    const fetcher = vi.fn();

    const result = await drainQueue(queue, { fetcher });

    expect(fetcher).not.toHaveBeenCalled();
    expect(result).toEqual({ acknowledged: [], rejected: [], retry: [] });
  });
});

describe('fetchKitchenTickets', () => {
  it('returns the tickets from the versioned endpoint', async () => {
    const fetcher = vi.fn().mockResolvedValue(response(200, { data: [ticket()] }));

    const tickets = await fetchKitchenTickets(7, { fetcher });

    expect(tickets).toHaveLength(1);
    expect(fetcher.mock.calls[0][0]).toBe('/api/v1/restaurant/kitchen/orders?branch_id=7');
  });

  it('returns null on a non-ok response so the caller keeps its local state', async () => {
    const fetcher = vi.fn().mockResolvedValue(response(500));

    expect(await fetchKitchenTickets(7, { fetcher })).toBeNull();
  });

  it('returns null when the network fails', async () => {
    const fetcher = vi.fn().mockRejectedValue(new Error('offline'));

    expect(await fetchKitchenTickets(7, { fetcher })).toBeNull();
  });

  it('returns null when the payload has no data array', async () => {
    const fetcher = vi.fn().mockResolvedValue(response(200, { data: 'nope' }));

    expect(await fetchKitchenTickets(7, { fetcher })).toBeNull();
  });
});
