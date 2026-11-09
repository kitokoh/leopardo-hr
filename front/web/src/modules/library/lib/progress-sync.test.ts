import {
  fetchServerProgress,
  fromServer,
  mergeWithServer,
  pushProgress,
  shouldPush,
  syncProgress,
  type ServerProgress,
  type SyncOptions,
} from './progress-sync';
import { loadProgress, saveProgress, type ReadingProgress } from './progress';

/**
 * Tests de la synchronisation serveur de la progression (#9242).
 *
 * Le point critique est la FUSION : la position la plus récente gagne, et une
 * position locale plus ancienne ne doit jamais écraser une lecture faite sur un
 * autre appareil.
 */

const entry = (slug: string, page: number, updatedAt: number, totalPages = 100): ReadingProgress => ({
  slug,
  page,
  totalPages,
  updatedAt,
});

describe('fromServer', () => {
  it('convertit une entrée serveur en entrée locale', () => {
    const server: ServerProgress = {
      slug: 'guide',
      page: 12,
      total_pages: 200,
      updated_at: '2026-11-09T10:00:00+00:00',
    };

    const local = fromServer(server);

    expect(local.slug).toBe('guide');
    expect(local.page).toBe(12);
    expect(local.totalPages).toBe(200);
    expect(local.updatedAt).toBe(Date.parse('2026-11-09T10:00:00+00:00'));
  });

  it('traite un horodatage absent comme 0 (jamais NaN)', () => {
    const local = fromServer({ slug: 'x', page: 1, total_pages: 0, updated_at: null });
    expect(local.updatedAt).toBe(0);
    expect(Number.isNaN(local.updatedAt)).toBe(false);
  });
});

describe('mergeWithServer', () => {
  it('retient la version la plus récente par slug', () => {
    const local = [entry('a', 5, 1000)];
    const server = [entry('a', 9, 2000)];

    expect(mergeWithServer(local, server)).toEqual([entry('a', 9, 2000)]);
  });

  it('conserve la version locale quand elle est plus récente', () => {
    const local = [entry('a', 9, 3000)];
    const server = [entry('a', 5, 1000)];

    expect(mergeWithServer(local, server)).toEqual([entry('a', 9, 3000)]);
  });

  it('unit les slugs présents d’un seul côté', () => {
    const merged = mergeWithServer([entry('local', 1, 1000)], [entry('serveur', 2, 2000)]);

    expect(merged.map((item) => item.slug).sort()).toEqual(['local', 'serveur']);
  });

  it('trie du plus récent au plus ancien', () => {
    const merged = mergeWithServer(
      [entry('vieux', 1, 1000)],
      [entry('recent', 1, 5000), entry('moyen', 1, 3000)],
    );

    expect(merged.map((item) => item.slug)).toEqual(['recent', 'moyen', 'vieux']);
  });

  it('ne duplique jamais un slug', () => {
    const merged = mergeWithServer([entry('a', 1, 1000), entry('a', 2, 2000)], [entry('a', 3, 3000)]);
    expect(merged).toHaveLength(1);
  });
});

describe('shouldPush', () => {
  it('pousse quand le serveur ne connaît pas la ressource', () => {
    expect(shouldPush(entry('a', 1, 1000), null)).toBe(true);
  });

  it('pousse quand le local est strictement plus récent', () => {
    expect(shouldPush(entry('a', 1, 2000), entry('a', 1, 1000))).toBe(true);
  });

  it('ne pousse pas quand le serveur est plus récent', () => {
    expect(shouldPush(entry('a', 1, 1000), entry('a', 1, 2000))).toBe(false);
  });

  it('ne pousse pas quand les horodatages sont identiques', () => {
    expect(shouldPush(entry('a', 1, 1000), entry('a', 1, 1000))).toBe(false);
  });
});

describe('fetchServerProgress', () => {
  const options = (fetchImpl: typeof fetch): SyncOptions => ({
    baseUrl: 'https://api.test/api/v1',
    token: 'jeton',
    fetchImpl,
  });

  it('convertit la réponse du serveur', async () => {
    const fetchImpl = jest.fn().mockResolvedValue({
      ok: true,
      json: async () => ({
        data: [{ slug: 'guide', page: 3, total_pages: 50, updated_at: '2026-11-09T10:00:00Z' }],
      }),
    }) as unknown as typeof fetch;

    const result = await fetchServerProgress(options(fetchImpl));

    expect(result).toHaveLength(1);
    expect(result[0].slug).toBe('guide');
    expect(result[0].page).toBe(3);
  });

  it('envoie le jeton d’authentification', async () => {
    const fetchImpl = jest.fn().mockResolvedValue({
      ok: true,
      json: async () => ({ data: [] }),
    }) as unknown as typeof fetch;

    await fetchServerProgress(options(fetchImpl));

    const [, init] = (fetchImpl as jest.Mock).mock.calls[0];
    expect(init.headers.Authorization).toBe('Bearer jeton');
  });

  it('retourne un tableau vide sur une réponse non-ok', async () => {
    const fetchImpl = jest.fn().mockResolvedValue({ ok: false }) as unknown as typeof fetch;
    expect(await fetchServerProgress(options(fetchImpl))).toEqual([]);
  });

  it('retourne un tableau vide sur une erreur réseau', async () => {
    const fetchImpl = jest.fn().mockRejectedValue(new Error('offline')) as unknown as typeof fetch;
    expect(await fetchServerProgress(options(fetchImpl))).toEqual([]);
  });

  it('retourne un tableau vide si la charge utile est malformée', async () => {
    const fetchImpl = jest.fn().mockResolvedValue({
      ok: true,
      json: async () => ({ data: 'pas un tableau' }),
    }) as unknown as typeof fetch;

    expect(await fetchServerProgress(options(fetchImpl))).toEqual([]);
  });
});

describe('pushProgress', () => {
  const options = (fetchImpl: typeof fetch): SyncOptions => ({
    baseUrl: 'https://api.test/api/v1',
    token: 'jeton',
    fetchImpl,
  });

  it('envoie un PUT avec la page et le total', async () => {
    const fetchImpl = jest.fn().mockResolvedValue({ ok: true }) as unknown as typeof fetch;

    const ok = await pushProgress(entry('guide', 7, 1000, 40), options(fetchImpl));

    expect(ok).toBe(true);
    const [url, init] = (fetchImpl as jest.Mock).mock.calls[0];
    expect(url).toBe('https://api.test/api/v1/library/progress/guide');
    expect(init.method).toBe('PUT');
    expect(JSON.parse(init.body)).toEqual({ page: 7, total_pages: 40 });
  });

  it('encode le slug dans l’URL', async () => {
    const fetchImpl = jest.fn().mockResolvedValue({ ok: true }) as unknown as typeof fetch;

    await pushProgress(entry('guide été', 1, 1000), options(fetchImpl));

    const [url] = (fetchImpl as jest.Mock).mock.calls[0];
    expect(url).toContain('guide%20%C3%A9t%C3%A9');
  });

  it('retourne false sur une réponse non-ok', async () => {
    const fetchImpl = jest.fn().mockResolvedValue({ ok: false }) as unknown as typeof fetch;
    expect(await pushProgress(entry('a', 1, 1000), options(fetchImpl))).toBe(false);
  });

  it('retourne false sur une erreur réseau (jamais d’exception)', async () => {
    const fetchImpl = jest.fn().mockRejectedValue(new Error('offline')) as unknown as typeof fetch;
    expect(await pushProgress(entry('a', 1, 1000), options(fetchImpl))).toBe(false);
  });
});

describe('syncProgress', () => {
  beforeEach(() => {
    window.localStorage.clear();
  });

  it('fusionne, persiste localement et pousse les positions plus récentes', async () => {
    saveProgress(entry('local-recent', 5, 5000));
    saveProgress(entry('serveur-recent', 1, 1000));

    const fetchImpl = jest.fn(async (url: string, init?: RequestInit) => {
      if (!init || init.method !== 'PUT') {
        return {
          ok: true,
          json: async () => ({
            data: [
              { slug: 'serveur-recent', page: 9, total_pages: 100, updated_at: new Date(9000).toISOString() },
            ],
          }),
        };
      }
      return { ok: true };
    }) as unknown as typeof fetch;

    const merged = await syncProgress({
      baseUrl: 'https://api.test/api/v1',
      token: 'jeton',
      fetchImpl,
    });

    // La position serveur plus récente a remplacé la locale.
    const serveurRecent = merged.find((item) => item.slug === 'serveur-recent');
    expect(serveurRecent?.page).toBe(9);

    // La position locale plus récente a été conservée.
    const localRecent = merged.find((item) => item.slug === 'local-recent');
    expect(localRecent?.page).toBe(5);

    // Le localStorage reflète la fusion.
    const persisted = loadProgress();
    expect(persisted.find((item) => item.slug === 'serveur-recent')?.page).toBe(9);

    // Seule la position locale plus récente a été poussée.
    const puts = (fetchImpl as jest.Mock).mock.calls.filter(([, init]) => init?.method === 'PUT');
    expect(puts).toHaveLength(1);
    expect(puts[0][0]).toContain('local-recent');
  });

  it('ne pousse rien quand le serveur est à jour', async () => {
    saveProgress(entry('a', 1, 1000));

    const fetchImpl = jest.fn(async (_url: string, init?: RequestInit) => {
      if (!init || init.method !== 'PUT') {
        return {
          ok: true,
          json: async () => ({
            data: [{ slug: 'a', page: 1, total_pages: 100, updated_at: new Date(9000).toISOString() }],
          }),
        };
      }
      return { ok: true };
    }) as unknown as typeof fetch;

    await syncProgress({ baseUrl: 'https://api.test/api/v1', token: 'jeton', fetchImpl });

    const puts = (fetchImpl as jest.Mock).mock.calls.filter(([, init]) => init?.method === 'PUT');
    expect(puts).toHaveLength(0);
  });

  it('reste fonctionnel hors ligne (aucune exception)', async () => {
    saveProgress(entry('a', 3, 1000));

    const fetchImpl = jest.fn().mockRejectedValue(new Error('offline')) as unknown as typeof fetch;

    const merged = await syncProgress({
      baseUrl: 'https://api.test/api/v1',
      token: 'jeton',
      fetchImpl,
    });

    expect(merged.find((item) => item.slug === 'a')?.page).toBe(3);
  });
});
