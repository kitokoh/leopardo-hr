import { canSyncProgress, pushProgress, pullProgress, persistProgress } from '../lib/progress-sync';
import { getProgressFor } from '../lib/progress';

/**
 * Tests de la synchronisation de progression (local + serveur).
 *
 * ⚠️ Le comportement critique est le REPLI : quelle que soit la panne serveur
 * (réseau, 401, 500, JSON invalide), la progression locale doit rester
 * intacte et aucune exception ne doit remonter au lecteur.
 */

jest.mock('@/lib/api-client', () => ({
  apiFetch: jest.fn(),
}));

jest.mock('@/lib/auth-session', () => ({
  getStoredUser: jest.fn(),
}));

const { apiFetch } = require('@/lib/api-client') as { apiFetch: jest.Mock };
const { getStoredUser } = require('@/lib/auth-session') as { getStoredUser: jest.Mock };

const entry = { slug: 'livre-test', page: 4, totalPages: 20, updatedAt: 1_700_000_000_000 };

beforeEach(() => {
  jest.clearAllMocks();
  window.localStorage.clear();
});

describe('progress-sync — canSyncProgress', () => {
  it('est faux pour un visiteur anonyme', () => {
    getStoredUser.mockReturnValue(null);
    expect(canSyncProgress()).toBe(false);
  });

  it('est vrai pour un utilisateur connecté', () => {
    getStoredUser.mockReturnValue({ id: 1, name: 'Test' });
    expect(canSyncProgress()).toBe(true);
  });
});

describe('progress-sync — pushProgress', () => {
  it('n’appelle pas l’API pour un visiteur anonyme', async () => {
    getStoredUser.mockReturnValue(null);

    await expect(pushProgress(entry)).resolves.toBe(false);
    expect(apiFetch).not.toHaveBeenCalled();
  });

  it('envoie la position et retourne true si le serveur accepte', async () => {
    getStoredUser.mockReturnValue({ id: 1 });
    apiFetch.mockResolvedValue({ ok: true });

    await expect(pushProgress(entry)).resolves.toBe(true);

    expect(apiFetch).toHaveBeenCalledWith(
      '/library/progress/livre-test',
      expect.objectContaining({ method: 'PUT' }),
    );
    const body = JSON.parse(apiFetch.mock.calls[0][1].body);
    expect(body).toEqual({ page: 4, total_pages: 20 });
  });

  it('envoie total_pages null quand le total est inconnu', async () => {
    getStoredUser.mockReturnValue({ id: 1 });
    apiFetch.mockResolvedValue({ ok: true });

    await pushProgress({ ...entry, totalPages: 0 });

    const body = JSON.parse(apiFetch.mock.calls[0][1].body);
    expect(body.total_pages).toBeNull();
  });

  it('retourne false sans lever si le réseau échoue', async () => {
    getStoredUser.mockReturnValue({ id: 1 });
    apiFetch.mockRejectedValue(new Error('network down'));

    await expect(pushProgress(entry)).resolves.toBe(false);
  });

  it('retourne false si le serveur répond en erreur', async () => {
    getStoredUser.mockReturnValue({ id: 1 });
    apiFetch.mockResolvedValue({ ok: false, status: 500 });

    await expect(pushProgress(entry)).resolves.toBe(false);
  });
});

describe('progress-sync — pullProgress', () => {
  it('retourne null pour un visiteur anonyme', async () => {
    getStoredUser.mockReturnValue(null);
    await expect(pullProgress('livre-test')).resolves.toBeNull();
    expect(apiFetch).not.toHaveBeenCalled();
  });

  it('écrit la position serveur en local et la retourne', async () => {
    getStoredUser.mockReturnValue({ id: 1 });
    apiFetch.mockResolvedValue({
      ok: true,
      json: async () => ({
        data: {
          resource_id: 7,
          page: 12,
          total_pages: 30,
          updated_at: '2026-11-09T10:00:00+00:00',
        },
      }),
    });

    const result = await pullProgress('livre-test');

    expect(result?.page).toBe(12);
    expect(result?.totalPages).toBe(30);
    expect(getProgressFor('livre-test')?.page).toBe(12);
  });

  it('retourne null si le serveur n’a aucune progression', async () => {
    getStoredUser.mockReturnValue({ id: 1 });
    apiFetch.mockResolvedValue({ ok: true, json: async () => ({ data: null }) });

    await expect(pullProgress('livre-test')).resolves.toBeNull();
  });

  it('retourne null sans lever si le réseau échoue', async () => {
    getStoredUser.mockReturnValue({ id: 1 });
    apiFetch.mockRejectedValue(new Error('offline'));

    await expect(pullProgress('livre-test')).resolves.toBeNull();
  });

  it('retourne null si la réponse n’est pas exploitable', async () => {
    getStoredUser.mockReturnValue({ id: 1 });
    apiFetch.mockResolvedValue({ ok: true, json: async () => ({ data: { page: 'x' } }) });

    await expect(pullProgress('livre-test')).resolves.toBeNull();
  });
});

describe('progress-sync — persistProgress', () => {
  it('écrit toujours en local, même si le serveur échoue', () => {
    getStoredUser.mockReturnValue({ id: 1 });
    apiFetch.mockRejectedValue(new Error('offline'));

    persistProgress(entry);

    expect(getProgressFor('livre-test')?.page).toBe(4);
  });

  it('écrit en local pour un visiteur anonyme sans appeler l’API', () => {
    getStoredUser.mockReturnValue(null);

    persistProgress(entry);

    expect(getProgressFor('livre-test')?.page).toBe(4);
    expect(apiFetch).not.toHaveBeenCalled();
  });
});
