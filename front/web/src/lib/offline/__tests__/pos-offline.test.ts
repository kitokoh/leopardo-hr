import {
  InMemoryKeyValueStorage,
  POS_PAYMENT_KIND,
  PosCatalogCache,
  buildPaymentOperation,
  createPosSender,
  summarizeQueue,
  toApiCall,
} from '../pos-offline';
import type { OfflineOperation } from '../offline-queue';

/**
 * #8449 — preuves du POS offline-first.
 *
 * Vérifie les critères d'acceptation de l'issue : catalogue encaissable hors
 * ligne (cache local), encaissement mis en file, synchronisation idempotente
 * (zéro doublon), et diagnostic des erreurs.
 */
describe('pos-offline (#8449)', () => {
  describe('PosCatalogCache', () => {
    it('keeps the last known catalog so the POS is usable offline', () => {
      const storage = new InMemoryKeyValueStorage();
      const cache = new PosCatalogCache<{ products: string[] }>(storage);

      cache.save(7, { products: ['Café', 'Croissant'] });

      expect(cache.load(7)?.data.products).toEqual(['Café', 'Croissant']);
    });

    it('scopes the cache per branch (no cross-branch leakage)', () => {
      const storage = new InMemoryKeyValueStorage();
      const cache = new PosCatalogCache<{ products: string[] }>(storage);

      cache.save(1, { products: ['A'] });
      cache.save(2, { products: ['B'] });

      expect(cache.load(1)?.data.products).toEqual(['A']);
      expect(cache.load(2)?.data.products).toEqual(['B']);
      expect(cache.load(3)).toBeNull();
    });

    it('reports staleness without discarding the catalog', () => {
      const storage = new InMemoryKeyValueStorage();
      let now = 1_000;
      const cache = new PosCatalogCache<{ products: string[] }>(storage, () => now, 60_000);

      cache.save(1, { products: ['A'] });
      expect(cache.isFresh(1)).toBe(true);

      now += 120_000;
      expect(cache.isFresh(1)).toBe(false);
      // Périmé mais toujours servi : un service sans réseau ne repart pas vide.
      expect(cache.load(1)?.data.products).toEqual(['A']);
    });

    it('survives corrupted storage instead of throwing', () => {
      const storage = new InMemoryKeyValueStorage();
      storage.setItem('leopardo.pos.catalog.1', '{not json');
      const cache = new PosCatalogCache<{ products: string[] }>(storage);

      expect(cache.load(1)).toBeNull();
    });
  });

  describe('encaissement hors ligne', () => {
    const payload = {
      orderId: 42,
      amountMinor: 1250,
      providerCode: 'cash',
      reference: 'CMD-42',
    };

    it('produces a stable idempotency key for the same payment', () => {
      const a = buildPaymentOperation(payload);
      const b = buildPaymentOperation({ ...payload, reference: 'CMD-42-bis' });

      // La référence lisible ne doit PAS changer la clé : sinon un double clic
      // avec un libellé différent créerait un second encaissement.
      expect(a.id).toBe(b.id);
      expect(a.kind).toBe(POS_PAYMENT_KIND);
    });

    it('produces a different key for a different amount', () => {
      const a = buildPaymentOperation(payload);
      const b = buildPaymentOperation({ ...payload, amountMinor: 1300 });

      expect(a.id).not.toBe(b.id);
    });

    it('maps an operation to the server payment contract', () => {
      const operation = buildPaymentOperation(payload);
      const call = toApiCall({
        ...operation,
        createdAt: 0,
        attempts: 0,
        status: 'pending',
      } as OfflineOperation);

      expect(call.path).toBe('/restaurant/orders/42/pay');
      expect(call.body).toEqual({
        provider_code: 'cash',
        amount_minor: 1250,
        idempotency_key: operation.id,
      });
    });

    it('replays the same idempotency key after a network failure', async () => {
      const sent: Array<{ path: string; body: unknown }> = [];
      let failOnce = true;

      const sender = createPosSender(async (path, body) => {
        if (failOnce) {
          failOnce = false;
          throw new Error('network down');
        }
        sent.push({ path, body });
      });

      const operation = {
        ...buildPaymentOperation(payload),
        createdAt: 0,
        attempts: 0,
        status: 'pending',
      } as OfflineOperation;

      await expect(sender(operation)).rejects.toThrow('network down');
      await sender(operation);

      expect(sent).toHaveLength(1);
      expect((sent[0].body as { idempotency_key: string }).idempotency_key).toBe(operation.id);
    });

    it('rejects an unknown operation kind instead of sending garbage', () => {
      expect(() =>
        toApiCall({
          id: 'x',
          kind: 'pos.unknown',
          payload: {},
          createdAt: 0,
          attempts: 0,
          status: 'pending',
        } as OfflineOperation),
      ).toThrow('unsupported_pos_operation:pos.unknown');
    });
  });

  describe('summarizeQueue', () => {
    it('exposes pending, failed and the last diagnosable error', () => {
      const base = { createdAt: 0, attempts: 0, payload: {} };
      const summary = summarizeQueue([
        { ...base, id: 'a', kind: 'k', status: 'pending' },
        { ...base, id: 'b', kind: 'k', status: 'failed', lastError: 'network down' },
        { ...base, id: 'c', kind: 'k', status: 'synced' },
      ] as OfflineOperation[]);

      expect(summary.pendingCount).toBe(1);
      expect(summary.failedCount).toBe(1);
      expect(summary.lastError).toBe('network down');
    });
  });
});
