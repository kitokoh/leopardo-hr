/**
 * COULOIR 16 — M3-bis · Tests des pages `/activites` et `/profil`.
 *
 * ## Ce que ces tests protègent
 *
 * 1. **Les liens de la navigation basse ne sont plus morts.** `PublicBottomNav`
 *    renvoie vers `/activites` et `/profil` : ces routes doivent exister.
 * 2. **`unauthenticated` n'est pas une erreur.** Un visiteur sans compte doit
 *    lire « connectez-vous », jamais « impossible de charger » — un mensonge
 *    sur l'état du système.
 * 3. **Aucun succès déduit localement.** Un retrait de favori ou un changement
 *    de langue n'est affiché comme réussi qu'après un `2xx`.
 * 4. **La langue est écrite sur le COMPTE.** L'appel part vers
 *    `PATCH /user/public-profile`, pas vers un stockage local.
 * 5. **L'export RGPD est réel** et sa portée est dite à l'écran.
 * 6. **Les 4 locales existent** et l'arabe est bien servi (RTL géré par le
 *    layout racine via `dir`).
 */

import { getPublicAccountCopy, activityStatusLabel } from '../data/account-copy';
import {
  clearSearchHistory,
  fetchMyActivities,
  fetchMyFavorites,
  fetchMySearchHistory,
  fetchMyDataExport,
  fetchMyIdentity,
  fetchMyProfileSettings,
  logoutAllDevices,
  removeFavorite,
  updateMyProfileSettings,
} from '@/lib/public-account-api';

const LOCALES = ['fr', 'en', 'tr', 'ar'] as const;

// ────────────────────────────────────────────────────────────────────────────
// Copie i18n
// ────────────────────────────────────────────────────────────────────────────

describe('M3-bis — copie des pages Activités/Profil, 4 langues', () => {
  it.each(LOCALES)('fournit toutes les sections en %s', (locale) => {
    const copy = getPublicAccountCopy(locale);

    expect(copy.meta.activitiesTitle).toBeTruthy();
    expect(copy.meta.profileTitle).toBeTruthy();
    expect(copy.activities.title).toBeTruthy();
    expect(copy.activities.sections.activity).toBeTruthy();
    expect(copy.activities.sections.favorites).toBeTruthy();
    expect(copy.activities.sections.history).toBeTruthy();
    expect(copy.profile.title).toBeTruthy();
    expect(copy.profile.language.title).toBeTruthy();
    expect(copy.profile.data.exportCta).toBeTruthy();
    expect(copy.profile.sessions.logoutAll).toBeTruthy();
  });

  it.each(LOCALES)('distingue les trois listes vides en %s', (locale) => {
    const copy = getPublicAccountCopy(locale);

    // Trois listes vides distinctes : un bloc muet ne dirait pas laquelle est
    // vide, et le client croirait à une panne.
    expect(copy.activities.emptyActivities).not.toBe(copy.activities.emptyFavorites);
    expect(copy.activities.emptyFavorites).not.toBe(copy.activities.emptyHistory);
  });

  it.each(LOCALES)('déclare la portée de l’export en %s', (locale) => {
    const copy = getPublicAccountCopy(locale);

    // La portée doit dire ce que l'export NE contient PAS : un client qui
    // cherche ses commandes ne doit pas croire qu'elles ont disparu.
    expect(copy.profile.data.exportScope.length).toBeGreaterThan(40);
  });

  it('replie sur le français pour une locale inconnue', () => {
    expect(getPublicAccountCopy('de').activities.title).toBe(
      getPublicAccountCopy('fr').activities.title,
    );
  });

  it('sert réellement l’arabe (pas un repli FR déguisé)', () => {
    const ar = getPublicAccountCopy('ar');
    const fr = getPublicAccountCopy('fr');

    expect(ar.activities.title).not.toBe(fr.activities.title);
    // Caractères arabes présents.
    expect(ar.activities.title).toMatch(/[\u0600-\u06FF]/);
  });
});

describe('M3-bis — libellé de statut', () => {
  it('traduit un statut connu', () => {
    expect(activityStatusLabel('fr', 'confirmed')).toBe('Confirmée');
    expect(activityStatusLabel('en', 'confirmed')).toBe('Confirmed');
  });

  it('rend la valeur brute pour un statut inconnu, jamais un libellé inventé', () => {
    // Mieux vaut afficher `partially_refunded` que « Confirmée » sur une
    // commande qui ne l'est pas.
    expect(activityStatusLabel('fr', 'partially_refunded')).toBe('partially_refunded');
  });
});

// ────────────────────────────────────────────────────────────────────────────
// Client API — contrat de résultat
// ────────────────────────────────────────────────────────────────────────────

function jsonResponse(body: unknown, status = 200): Response {
  return {
    ok: status >= 200 && status < 300,
    status,
    json: async () => body,
  } as unknown as Response;
}

describe('M3-bis — client API : 401 est un état, pas une erreur', () => {
  it('traduit 401 en `unauthenticated` pour les activités', async () => {
    const fetcher = jest.fn().mockResolvedValue(jsonResponse({}, 401));

    const result = await fetchMyActivities(fetcher as unknown as typeof fetch);

    expect(result.status).toBe('unauthenticated');
  });

  it('traduit 401 en `unauthenticated` pour le profil', async () => {
    const fetcher = jest.fn().mockResolvedValue(jsonResponse({}, 401));

    const result = await fetchMyProfileSettings(fetcher as unknown as typeof fetch);

    expect(result.status).toBe('unauthenticated');
  });

  it('traduit une coupure réseau en `error`, jamais en écran vide', async () => {
    const fetcher = jest.fn().mockRejectedValue(new Error('offline'));

    const result = await fetchMyFavorites(fetcher as unknown as typeof fetch);

    expect(result.status).toBe('error');
  });

  it('rend les données quand la réponse est 200', async () => {
    const fetcher = jest.fn().mockResolvedValue(
      jsonResponse({
        data: [
          {
            vertical: 'restaurant',
            activity_type: 'order',
            reference: 'CMD-1',
            status: 'confirmed',
            occurred_at: '2026-11-01T10:00:00+00:00',
          },
        ],
      }),
    );

    const result = await fetchMyActivities(fetcher as unknown as typeof fetch);

    expect(result.status).toBe('ok');
    if (result.status === 'ok') {
      expect(result.data[0].reference).toBe('CMD-1');
    }
  });
});

describe('M3-bis — client API : le retrait d’un favori accepte le 204 sans corps', () => {
  it('traite 204 comme un succès (l’API ne renvoie pas de `data`)', async () => {
    const fetcher = jest.fn().mockResolvedValue({
      ok: true,
      status: 204,
      json: async () => {
        throw new Error('204 has no body');
      },
    } as unknown as Response);

    const result = await removeFavorite('restaurant', 'chez-fictif', fetcher as unknown as typeof fetch);

    expect(result.status).toBe('ok');
  });

  it('traite 401 comme `unauthenticated`', async () => {
    const fetcher = jest.fn().mockResolvedValue(jsonResponse({}, 401));

    const result = await removeFavorite('restaurant', 'chez-fictif', fetcher as unknown as typeof fetch);

    expect(result.status).toBe('unauthenticated');
  });
});

describe('M3-bis — client API : la langue part vers le COMPTE', () => {
  it('appelle PATCH /user/public-profile avec la locale choisie', async () => {
    const fetcher = jest.fn().mockResolvedValue(
      jsonResponse({
        data: {
          preferred_locale: 'ar',
          approx_city: null,
          approx_country: null,
          notifications_opt_in: false,
          notifications_consent_at: null,
          privacy_policy_version: null,
          last_active_at: null,
          supported_locales: ['fr', 'en', 'ar', 'tr'],
        },
      }),
    );

    const result = await updateMyProfileSettings(
      { preferred_locale: 'ar' },
      fetcher as unknown as typeof fetch,
    );

    expect(result.status).toBe('ok');

    const [path, init] = fetcher.mock.calls[0] as [string, RequestInit];
    expect(path).toBe('/api/v1/user/public-profile');
    expect(init.method).toBe('PATCH');
    expect(JSON.parse(String(init.body))).toEqual({ preferred_locale: 'ar' });
  });

  it('remonte un 422 comme `invalid` (locale non servie)', async () => {
    const fetcher = jest.fn().mockResolvedValue(
      jsonResponse({ errors: { preferred_locale: ['The selected preferred locale is invalid.'] } }, 422),
    );

    const result = await updateMyProfileSettings(
      { preferred_locale: 'de' },
      fetcher as unknown as typeof fetch,
    );

    expect(result.status).toBe('invalid');
  });
});

describe('M3-bis — client API : les routes appelées existent côté serveur', () => {
  it.each([
    ['identité', () => fetchMyIdentity, '/api/v1/user/me'],
    ['profil', () => fetchMyProfileSettings, '/api/v1/user/public-profile'],
    ['activités', () => fetchMyActivities, '/api/v1/user/activities'],
    ['favoris', () => fetchMyFavorites, '/api/v1/user/favorites'],
    ['historique', () => fetchMySearchHistory, '/api/v1/user/search-history'],
    ['export', () => fetchMyDataExport, '/api/v1/user/data-export'],
  ])('%s → %s', async (_label, getFn, expectedPath) => {
    const fetcher = jest.fn().mockResolvedValue(jsonResponse({ data: [] }));

    await getFn()(fetcher as unknown as typeof fetch);

    expect(fetcher.mock.calls[0][0]).toBe(expectedPath);
  });

  it('logout-all part en POST', async () => {
    const fetcher = jest.fn().mockResolvedValue(jsonResponse({ data: { revoked: 2 } }));

    await logoutAllDevices(fetcher as unknown as typeof fetch);

    const [path, init] = fetcher.mock.calls[0] as [string, RequestInit];
    expect(path).toBe('/api/v1/user/logout-all');
    expect(init.method).toBe('POST');
  });

  it('clearSearchHistory part en DELETE', async () => {
    const fetcher = jest.fn().mockResolvedValue(jsonResponse({ data: { deleted: 3 } }));

    const result = await clearSearchHistory(fetcher as unknown as typeof fetch);

    const [path, init] = fetcher.mock.calls[0] as [string, RequestInit];
    expect(path).toBe('/api/v1/user/search-history');
    expect(init.method).toBe('DELETE');
    expect(result.status).toBe('ok');
  });
});
