/**
 * @jest-environment node
 */
import { NextRequest } from 'next/server';

import { GET } from '../route';

/**
 * #7491 — Session glissante 30 jours : le proxy doit faire survivre la
 * rotation Sanctum (TokenAutoRefreshMiddleware).
 *
 * Constat avant le fix : quand l'API pivotait le token (fenêtre des 24 h
 * avant échéance), le nouveau token était injecté dans le corps JSON
 * (`_auth.token`) MAIS le cookie httpOnly `leopardo_token` conservait
 * l'ancien token RÉVOQUÉ. La session web mourrait donc à l'échéance malgré
 * la rotation, et le token en clair atteignait le JS de la page.
 *
 * Contrat verrouillé :
 * 1. `X-Token-Refreshed: true` + `_auth.token` valide → cookie re-posé avec
 *    le nouveau token (httpOnly, SameSite=Strict, Max-Age 30 j) ;
 * 2. le corps relayé au navigateur ne contient JAMAIS le token en clair ;
 * 3. en-tête présent mais corps non conforme → réponse relayée telle quelle,
 *    aucun cookie modifié ;
 * 4. pas d'en-tête de pivot → comportement inchangé, aucun cookie modifié.
 */

jest.mock('@/lib/backend-url', () => ({
  resolveBackendBaseUrl: jest.fn(() => 'https://backend.example.com/api/v1'),
}));

jest.mock('@/lib/site', () => ({
  getSiteUrl: jest.fn(() => 'https://web.example.com'),
}));

const mockCookieStore = {
  set: jest.fn(),
  get: jest.fn(() => undefined),
};

jest.mock('next/headers', () => ({
  cookies: jest.fn(async () => mockCookieStore),
}));

const mockFetch = jest.fn();

beforeEach(() => {
  jest.clearAllMocks();
  global.fetch = mockFetch as unknown as typeof fetch;
});

const NEW_TOKEN = '991|abcdefghijklmnop1234567890abcd';

function meRequest(): NextRequest {
  return new NextRequest('https://web.example.com/api/v1/auth/me', {
    method: 'GET',
    headers: { Accept: 'application/json' },
  });
}

function rotatedResponse(): Response {
  return new Response(
    JSON.stringify({
      data: { id: 1 },
      _auth: {
        token_refreshed: true,
        token: NEW_TOKEN,
        expires_at: '2026-10-15T00:00:00.000000Z',
      },
    }),
    {
      status: 200,
      headers: {
        'Content-Type': 'application/json',
        'X-Token-Refreshed': 'true',
        'X-Token-Expires-At': '2026-10-15T00:00:00.000000Z',
      },
    },
  );
}

describe('proxy /api/v1/[...path] — rotation de token (#7491)', () => {
  it('re-pose le cookie httpOnly avec le nouveau token et retire le token du corps relayé', async () => {
    mockFetch.mockResolvedValue(rotatedResponse());

    const res = await GET(meRequest(), {
      params: Promise.resolve({ path: ['auth', 'me'] }),
    });

    expect(res.status).toBe(200);

    const setCookie = res.headers.get('set-cookie') ?? '';
    expect(setCookie).toContain('leopardo_token=');
    expect(setCookie).toContain('HttpOnly');
    expect(setCookie).toContain('SameSite=strict');
    expect(setCookie).toContain(`Max-Age=${60 * 60 * 24 * 30}`);

    const body = (await res.json()) as Record<string, unknown> & {
      _auth?: Record<string, unknown>;
    };
    expect(body.data).toEqual({ id: 1 });
    expect(body._auth?.token_refreshed).toBe(true);
    expect(JSON.stringify(body)).not.toContain(NEW_TOKEN);
  });

  it('en-tête de pivot mais corps non conforme → réponse relayée sans toucher au cookie', async () => {
    mockFetch.mockResolvedValue(
      new Response(JSON.stringify({ data: { id: 1 } }), {
        status: 200,
        headers: {
          'Content-Type': 'application/json',
          'X-Token-Refreshed': 'true',
        },
      }),
    );

    const res = await GET(meRequest(), {
      params: Promise.resolve({ path: ['auth', 'me'] }),
    });

    expect(res.status).toBe(200);
    expect(res.headers.get('set-cookie')).toBeNull();
    expect((await res.json()).data).toEqual({ id: 1 });
  });

  it('sans pivot, aucun cookie n’est modifié', async () => {
    mockFetch.mockResolvedValue(
      new Response(JSON.stringify({ data: { id: 1 } }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      }),
    );

    const res = await GET(meRequest(), {
      params: Promise.resolve({ path: ['auth', 'me'] }),
    });

    expect(res.headers.get('set-cookie')).toBeNull();
    expect((await res.json()).data).toEqual({ id: 1 });
  });

  it('refuse un token de forme invalide (jamais en cookie)', async () => {
    mockFetch.mockResolvedValue(
      new Response(
        JSON.stringify({
          data: { id: 1 },
          _auth: { token_refreshed: true, token: 'trop-court' },
        }),
        {
          status: 200,
          headers: {
            'Content-Type': 'application/json',
            'X-Token-Refreshed': 'true',
          },
        },
      ),
    );

    const res = await GET(meRequest(), {
      params: Promise.resolve({ path: ['auth', 'me'] }),
    });

    expect(res.headers.get('set-cookie')).toBeNull();
  });
});
