import {
  InMemoryOfflineStorage,
  OfflineQueue,
  makeOperationId,
  resolveConflict,
} from '../offline-queue';

/**
 * #8493 / #8494 (I-06, I-07) — preuves du noyau hors-ligne.
 *
 * Ces tests verifient les exigences de la Partie 7 : idempotence (pas de
 * double commande), operations en attente visibles, erreurs diagnostiquables,
 * reprise apres echec, persistance locale et regle de conflit explicite.
 */
describe('offline-queue (#8493/#8494)', () => {
  it('produces a stable idempotency key independent of key order', () => {
    const a = makeOperationId('order.create', { table: 4, items: [{ sku: 'A', qty: 2 }] });
    const b = makeOperationId('order.create', { items: [{ qty: 2, sku: 'A' }], table: 4 });
    expect(a).toBe(b);
  });

  it('never duplicates an operation with the same id (no double order)', () => {
    const queue = new OfflineQueue();
    expect(queue.enqueue('order.create', { table: 4 })).toBe('enqueued');
    expect(queue.enqueue('order.create', { table: 4 })).toBe('duplicate');
    expect(queue.size()).toBe(1);
  });

  it('tracks pending, synced and failed states with diagnosable errors', () => {
    const queue = new OfflineQueue();
    const id = makeOperationId('order.create', { table: 1 });
    queue.enqueue('order.create', { table: 1 });
    expect(queue.pending()).toHaveLength(1);

    queue.markSyncing(id);
    queue.markFailed(id, 'network down');
    const failed = queue.all()[0];
    expect(failed.status).toBe('failed');
    expect(failed.attempts).toBe(1);
    expect(failed.lastError).toBe('network down');

    queue.retry(id);
    expect(queue.all()[0].status).toBe('pending');

    queue.markSynced(id);
    expect(queue.pending()).toHaveLength(0);
  });

  it('persists through the injected storage (survives a reload)', () => {
    const storage = new InMemoryOfflineStorage();
    const first = new OfflineQueue(storage);
    first.enqueue('order.create', { table: 7 });

    const second = new OfflineQueue(storage);
    expect(second.size()).toBe(1);
    expect(second.all()[0].payload).toEqual({ table: 7 });
  });

  it('resolves conflicts with the server as the authority', () => {
    expect(resolveConflict({ id: 'x', status: 'pending' }, false)).toBe('keep-local');
    expect(resolveConflict({ id: 'x', status: 'pending' }, true)).toBe('accept-server');
    expect(resolveConflict({ id: 'x', status: 'synced' }, true)).toBe('accept-server');
  });
});
