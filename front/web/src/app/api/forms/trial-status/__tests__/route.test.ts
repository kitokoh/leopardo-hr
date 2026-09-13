/**
 * @jest-environment node
 */
import { NextRequest } from 'next/server';

import { GET } from '../route';

/**
 * Garde anti-régression — #7265.
 *
 * Le proxy ne recopiait que `status`, `login_url` et `message`. Le backend
 * renvoie pourtant `password_set` : le composant d'inscription lisait
 * `res.data.password_set === true`, obtenait toujours `undefined`, et
 * réaffichait l'écran « définir mon mot de passe » alors qu'il était déjà
 * défini — la resoumission tombait alors sur `409 TRIAL_PASSWORD_ALREADY_SET`
 * (constaté en live).
 */

jest.mock('@/lib/backend-url', () => ({
  resolveBackendBaseUrl: jest.fn(() => 'https://backend.example.com'),
}));

const TOKEN = 'a'.repeat(64);

const mockFetch = jest.fn();

beforeEach(() => {
  jest.clearAllMocks();
  global.fetch = mockFetch as unknown as typeof fetch;
});

function backendResponse(data: Record<string, unknown>): Response {
  return new Response(JSON.stringify({ success: true, data }), {
    status: 200,
    headers: { 'Content-Type': 'application/json' },
  });
}

function trialStatusRequest(): NextRequest {
  return new NextRequest(
    `https://web.example.com/api/forms/trial-status?token=${TOKEN}`,
  );
}

describe('#7265 — le proxy de suivi relaie l’état réel du mot de passe', () => {
  it('relaie password_set et access_sent quand le backend les expose', async () => {
    mockFetch.mockResolvedValueOnce(
      backendResponse({
        status: 'ready',
        login_url: '/auth/login',
        password_set: true,
        access_sent: true,
      }),
    );

    const response = await GET(trialStatusRequest());
    const payload = await response.json();

    expect(response.status).toBe(200);
    expect(payload.data.status).toBe('ready');
    expect(payload.data.login_url).toBe('/auth/login');
    expect(payload.data.password_set).toBe(true);
    expect(payload.data.access_sent).toBe(true);
  });

  it("n'invente pas les booléens absents de la réponse backend", async () => {
    mockFetch.mockResolvedValueOnce(backendResponse({ status: 'pending' }));

    const payload = await (await GET(trialStatusRequest())).json();

    expect(payload.data).not.toHaveProperty('password_set');
    expect(payload.data).not.toHaveProperty('access_sent');
  });

  it('ne relaie jamais une valeur non booléenne à la place de password_set', async () => {
    mockFetch.mockResolvedValueOnce(
      backendResponse({ status: 'ready', password_set: 'true', access_sent: 1 }),
    );

    const payload = await (await GET(trialStatusRequest())).json();

    expect(payload.data).not.toHaveProperty('password_set');
    expect(payload.data).not.toHaveProperty('access_sent');
  });
});
