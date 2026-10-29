import {
  PersistentOfflineStorage,
  browserStorage,
  sanitize,
  storageKey,
  type KeyValueStore,
} from '../persistent-offline-storage';
import { OfflineQueue } from '../offline-queue';

/**
 * #8448 — critère d'acceptation : « coupure internet pendant le service → la
 * cuisine continue, resync sans perte ».
 *
 * Le noyau existant (#8493/#8494) était correct mais sa file vivait en
 * mémoire : un rafraîchissement de la tablette pendant la coupure effaçait les
 * transitions déjà saisies. Ces tests verrouillent la durabilité.
 */

/** Faux `localStorage` : reproduit le vrai contrat, sans jsdom. */
class FakeStore implements KeyValueStore {
  public readonly data = new Map<string, string>();

  public failOnSet = false;

  getItem(key: string): string | null {
    return this.data.get(key) ?? null;
  }

  setItem(key: string, value: string): void {
    if (this.failOnSet) {
      throw new Error('QuotaExceededError');
    }
    this.data.set(key, value);
  }

  removeItem(key: string): void {
    this.data.delete(key);
  }
}

describe('PersistentOfflineStorage (#8448)', () => {
  it('survit à un rechargement : la file retrouve ses opérations', () => {
    const store = new FakeStore();

    // Premier montage : le cuisinier coche deux commandes hors ligne.
    const first = new OfflineQueue(new PersistentOfflineStorage(store));
    first.enqueue('kitchen.transition', { orderId: 7, action: 'start' });
    first.enqueue('kitchen.transition', { orderId: 8, action: 'ready' });
    expect(first.pending()).toHaveLength(2);

    // Rechargement de la tablette : nouveau stockage, même `localStorage`.
    const second = new OfflineQueue(new PersistentOfflineStorage(store));
    const replayed = second.pending();

    expect(replayed).toHaveLength(2);
    expect(replayed.map((op) => op.payload)).toEqual([
      { orderId: 7, action: 'start' },
      { orderId: 8, action: 'ready' },
    ]);
  });

  it("rejoue les opérations d'une file rechargée sans créer de doublon", () => {
    const store = new FakeStore();
    const queue1 = new OfflineQueue(new PersistentOfflineStorage(store));
    queue1.enqueue('kitchen.transition', { orderId: 7, action: 'start' });

    // Après rechargement, un second clic identique doit être reconnu comme
    // doublon : la clé d'idempotence est déterministe et la file est rechargée.
    const queue2 = new OfflineQueue(new PersistentOfflineStorage(store));
    expect(queue2.enqueue('kitchen.transition', { orderId: 7, action: 'start' })).toBe('duplicate');
    expect(queue2.size()).toBe(1);
  });

  it('remet en attente une opération retrouvée en cours d’envoi', () => {
    // Une opération `syncing` au moment du crash n'est ni en vol ni aboutie :
    // on la rejoue (l'idempotence serveur évite le double effet).
    const store = new FakeStore();
    store.setItem(
      storageKey(),
      JSON.stringify([
        { id: 'kitchen.transition:abc', kind: 'kitchen.transition', payload: { orderId: 1 }, createdAt: 1, attempts: 1, status: 'syncing' },
      ]),
    );

    const queue = new OfflineQueue(new PersistentOfflineStorage(store));
    expect(queue.pending()).toHaveLength(1);
    expect(queue.pending()[0].status).toBe('pending');
  });

  it('ne plante pas sur une valeur corrompue et le signale', () => {
    const store = new FakeStore();
    store.setItem(storageKey(), '{ pas du json');

    const storage = new PersistentOfflineStorage(store);
    expect(storage.load()).toEqual([]);
    expect(storage.lastStorageIssue).toBe('corrupt');
  });

  it('ignore les entrées inexploitables sans jeter les valides', () => {
    const valid = { id: 'k:1', kind: 'k', payload: {}, createdAt: 1, attempts: 0, status: 'pending' };
    const result = sanitize([
      valid,
      null,
      'bouh',
      { id: '', kind: 'k' },
      { id: 'k:2', kind: 'k', status: 'inconnu' },
    ]);

    expect(result).toHaveLength(1);
    expect(result[0].id).toBe('k:1');
  });

  it('signale un quota dépassé au lieu de perdre les gestes en silence', () => {
    const store = new FakeStore();
    store.failOnSet = true;

    const storage = new PersistentOfflineStorage(store);
    storage.save([{ id: 'k:1', kind: 'k', payload: {}, createdAt: 1, attempts: 0, status: 'pending' }]);

    // L'écriture échoue : l'écran doit pouvoir dire que la file n'est PAS
    // durable (l'opération reste en mémoire, pas perdue tout de suite).
    expect(storage.lastStorageIssue).toBe('quota');
  });

  it('reste utilisable quand aucun stockage n’est disponible (SSR, privé strict)', () => {
    const storage = new PersistentOfflineStorage(null);
    expect(storage.load()).toEqual([]);
    storage.save([{ id: 'k:1', kind: 'k', payload: {}, createdAt: 1, attempts: 0, status: 'pending' }]);
    expect(storage.lastStorageIssue).toBe('unavailable');
  });

  it('isole deux postes cuisine du même navigateur', () => {
    const store = new FakeStore();
    const stationA = new OfflineQueue(new PersistentOfflineStorage(store, storageKey(undefined, 'kds-1')));
    const stationB = new OfflineQueue(new PersistentOfflineStorage(store, storageKey(undefined, 'kds-2')));

    stationA.enqueue('kitchen.transition', { orderId: 1, action: 'start' });

    expect(stationA.pending()).toHaveLength(1);
    expect(stationB.pending()).toHaveLength(0);
  });

  it('purge la file persistée', () => {
    const store = new FakeStore();
    const storage = new PersistentOfflineStorage(store);
    const queue = new OfflineQueue(storage);
    queue.enqueue('kitchen.transition', { orderId: 1, action: 'start' });

    storage.clear();

    expect(new OfflineQueue(new PersistentOfflineStorage(store)).size()).toBe(0);
  });

  it('fournit un localStorage réel sous jsdom', () => {
    // Le hook se rabat sur ce stockage en production : il doit être exploitable.
    expect(browserStorage()).not.toBeNull();
  });
});
