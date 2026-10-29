import {
  MAX_SNAPSHOT_AGE_MS,
  clearSnapshot,
  describeSnapshotAge,
  loadSnapshot,
  saveSnapshot,
  shouldDisplaySnapshot,
  snapshotKey,
  type SnapshotStorage,
} from '../kitchen-snapshot';

/**
 * #8448 — instantané local de la file cuisine.
 *
 * Ces tests couvrent le critère d'acceptation réel : « coupure internet
 * pendant le service → la cuisine continue ». La file d'opérations (#8493)
 * rejoue les transitions ; ce module rend la FILE VISIBLE après un
 * rechargement pendant la coupure. On vérifie donc surtout les cas où
 * l'instantané pourrait MENTIR : mauvaise branche, trop vieux, corrompu.
 */

function memoryStorage(): SnapshotStorage & { dump(): Map<string, string> } {
  const map = new Map<string, string>();
  return {
    getItem: (key) => map.get(key) ?? null,
    setItem: (key, value) => {
      map.set(key, value);
    },
    removeItem: (key) => {
      map.delete(key);
    },
    dump: () => map,
  };
}

const NOW = 1_800_000_000_000;

describe('kitchen-snapshot (#8448)', () => {
  it('round-trips the kitchen queue for a branch', () => {
    const storage = memoryStorage();
    const orders = [{ id: 1, reference: 'CMD-1' }, { id: 2, reference: 'CMD-2' }];

    expect(saveSnapshot(storage, 7, orders, NOW)).toBe(true);

    const loaded = loadSnapshot<{ id: number; reference: string }>(storage, 7, NOW);
    expect(loaded).not.toBeNull();
    expect(loaded?.orders).toEqual(orders);
    expect(loaded?.branchId).toBe(7);
    expect(loaded?.capturedAt).toBe(NOW);
  });

  it('returns null when nothing was stored', () => {
    expect(loadSnapshot(memoryStorage(), 7, NOW)).toBeNull();
  });

  it('never returns another branch snapshot', () => {
    // Tablette partagée entre deux points de vente : afficher la file du
    // voisin serait pire que ne rien afficher.
    const storage = memoryStorage();
    saveSnapshot(storage, 7, [{ id: 1 }], NOW);

    expect(loadSnapshot(storage, 8, NOW)).toBeNull();
  });

  it('rejects an expired snapshot', () => {
    const storage = memoryStorage();
    saveSnapshot(storage, 7, [{ id: 1 }], NOW - MAX_SNAPSHOT_AGE_MS - 1);

    expect(loadSnapshot(storage, 7, NOW)).toBeNull();
  });

  it('accepts a snapshot at the age limit', () => {
    const storage = memoryStorage();
    saveSnapshot(storage, 7, [{ id: 1 }], NOW - MAX_SNAPSHOT_AGE_MS);

    expect(loadSnapshot(storage, 7, NOW)).not.toBeNull();
  });

  it('allows disabling the age check explicitly', () => {
    const storage = memoryStorage();
    saveSnapshot(storage, 7, [{ id: 1 }], NOW - MAX_SNAPSHOT_AGE_MS - 1);

    expect(loadSnapshot(storage, 7, NOW, 0)).not.toBeNull();
  });

  it('drops a corrupted snapshot instead of throwing', () => {
    const storage = memoryStorage();
    storage.setItem(snapshotKey(7), '{not json');

    expect(loadSnapshot(storage, 7, NOW)).toBeNull();
    // Le corrompu est purgé : pas de relecture à chaque tour.
    expect(storage.dump().has(snapshotKey(7))).toBe(false);
  });

  it('rejects a payload whose shape is wrong', () => {
    const storage = memoryStorage();
    storage.setItem(snapshotKey(7), JSON.stringify({ orders: 'nope', capturedAt: NOW, branchId: 7 }));

    expect(loadSnapshot(storage, 7, NOW)).toBeNull();
  });

  it('rejects a missing capturedAt', () => {
    const storage = memoryStorage();
    storage.setItem(snapshotKey(7), JSON.stringify({ orders: [], branchId: 7 }));

    expect(loadSnapshot(storage, 7, NOW)).toBeNull();
  });

  it('survives a storage that throws (quota, private mode)', () => {
    // Un échec d'écriture ne doit jamais casser le KDS en ligne.
    const throwing: SnapshotStorage = {
      getItem: () => {
        throw new Error('denied');
      },
      setItem: () => {
        throw new Error('quota');
      },
      removeItem: () => {
        throw new Error('denied');
      },
    };

    expect(saveSnapshot(throwing, 7, [{ id: 1 }], NOW)).toBe(false);
    expect(loadSnapshot(throwing, 7, NOW)).toBeNull();
    expect(() => clearSnapshot(throwing, 7)).not.toThrow();
  });

  it('overwrites the previous snapshot instead of stacking versions', () => {
    const storage = memoryStorage();
    saveSnapshot(storage, 7, [{ id: 1 }], NOW);
    saveSnapshot(storage, 7, [{ id: 2 }], NOW + 1000);

    expect(loadSnapshot<{ id: number }>(storage, 7, NOW + 1000)?.orders).toEqual([{ id: 2 }]);
    expect(storage.dump().size).toBe(1);
  });

  it('clears a branch snapshot', () => {
    const storage = memoryStorage();
    saveSnapshot(storage, 7, [{ id: 1 }], NOW);
    clearSnapshot(storage, 7);

    expect(loadSnapshot(storage, 7, NOW)).toBeNull();
  });

  describe('shouldDisplaySnapshot', () => {
    const snapshot = { orders: [{ id: 1 }], capturedAt: NOW, branchId: 7 };

    it('never overlays a server answer, even an empty-looking one', () => {
      // Un instantané ne doit jamais RECOUVRIR une réponse serveur : il
      // pourrait masquer une commande annulée ou un changement de table.
      expect(shouldDisplaySnapshot(3, snapshot)).toBe(false);
    });

    it('displays the snapshot when the server returned nothing', () => {
      expect(shouldDisplaySnapshot(0, snapshot)).toBe(true);
    });

    it('does nothing without a snapshot or with an empty one', () => {
      expect(shouldDisplaySnapshot(0, null)).toBe(false);
      expect(shouldDisplaySnapshot(0, { orders: [], capturedAt: NOW, branchId: 7 })).toBe(false);
    });
  });

  describe('describeSnapshotAge', () => {
    it('says it is offline under a minute', () => {
      expect(describeSnapshotAge(NOW - 30_000, NOW)).toContain('moins d’une minute');
    });

    it('reports minutes', () => {
      expect(describeSnapshotAge(NOW - 5 * 60_000, NOW)).toContain('5 min');
    });

    it('reports hours and minutes', () => {
      expect(describeSnapshotAge(NOW - (2 * 60 + 15) * 60_000, NOW)).toContain('2 h 15 min');
    });

    it('reports whole hours without a dangling zero', () => {
      expect(describeSnapshotAge(NOW - 3 * 60 * 60_000, NOW)).toContain('3 h');
      expect(describeSnapshotAge(NOW - 3 * 60 * 60_000, NOW)).not.toContain('0 min');
    });

    it('never reports a negative age when clocks skew', () => {
      expect(describeSnapshotAge(NOW + 60_000, NOW)).toContain('moins d’une minute');
    });
  });
});
