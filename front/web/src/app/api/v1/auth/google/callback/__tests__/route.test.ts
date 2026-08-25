/**
 * @jest-environment node
 */
import { NextRequest } from 'next/server';
import { GET } from '../route';

/**
 * Issue #5174 — test d'intégration du callback Google OAuth côté vitrine
 * (`.specify/features/web-google-oauth-proxy`, fix #2277).
 *
 * La mécanique testée ici est le cœur du flux :
 *   code valide → cookie `leopardo_token` (httpOnly) + redirect /dashboard
 *   code invalide (backend ≥ 400) → redirect ?error=google_auth_failed
 *   backend injoignable → redirect ?error=google_network
 *   payload sans token → redirect ?error=google_no_account
 *   code absent → redirect ?error=google
 *
 * Le fetch backend est mocké : le test est hermétique (aucune dépendance
 * réseau / aucun Socialite réel).
 */

jest.mock('@/lib/backend-url', () => ({
  resolveBackendBaseUrl: jest.fn(() => 'https://backend.example.com/api/v1'),
}));

const mockCookieStore = {
  set: jest.fn(),
  get: jest.fn(() => undefined),
};

jest.mock('next/headers', () => ({
  cookies: jest.fn(() => mockCookieStore),
}));

const mockFetch = jest.fn();

beforeEach(() => {
  jest.clearAllMocks();
  global.fetch = mockFetch as unknown as typeof fetch;
  mockCookieStore.set.mockClear();
  mockCookieStore.get.mockClear();
});

function callbackRequest(code?: string): NextRequest {
  const url = code
    ? `http://localhost:3000/api/v1/auth/google/callback?code=${code}`
    : 'http://localhost:3000/api/v1/auth/google/callback';
  return new NextRequest(url);
}

describe('GET /api/v1/auth/google/callback', () => {
  it('redirige vers ?error=google quand le code est absent', async () => {
    const response = await GET(callbackRequest());

    expect(response.status).toBe(307);
    expect(response.headers.get('location')).toContain('/auth/login?error=google');
    expect(mockCookieStore.set).not.toHaveBeenCalled();
    expect(mockFetch).not.toHaveBeenCalled();
  });

  it('pose le cookie httpOnly et redirige vers /dashboard pour un code valide', async () => {
    mockFetch.mockResolvedValueOnce(
      new Response(JSON.stringify({ token: 'valid-token-123' }), {
        status: 200,
        headers: { 'content-type': 'application/json' },
      })
    );

    const response = await GET(callbackRequest('valid-code'));

    expect(response.status).toBe(307);
    expect(response.headers.get('location')).toContain('/dashboard');
    expect(mockCookieStore.set).toHaveBeenCalledWith(
      'leopardo_token',
      'valid-token-123',
      expect.objectContaining({ httpOnly: true, path: '/' })
    );
    // Le fetch cible bien le backend, pas une URL Render en dur.
    const backendUrl = mockFetch.mock.calls[0]?.[0] as string;
    expect(backendUrl).toContain('https://backend.example.com/api/v1/auth/google/callback');
  });

  it('redirige vers ?error=google_no_account quand le backend répond 401 UNKNOWN_ACCOUNT', async () => {
    // Issue #5171 : email Google inconnu → message dédié « demandez une
    // invitation » (labels.login.errors.googleNoAccount), pas l'erreur générique.
    mockFetch.mockResolvedValueOnce(
      new Response(JSON.stringify({ error: 'UNKNOWN_ACCOUNT' }), { status: 401 })
    );

    const response = await GET(callbackRequest('revoked-code'));

    expect(response.status).toBe(307);
    expect(response.headers.get('location')).toContain('/auth/login?error=google_no_account');
    expect(mockCookieStore.set).not.toHaveBeenCalled();
  });

  it('redirige vers ?error=google_auth_failed quand le backend répond ≥ 400 (erreur générique)', async () => {
    mockFetch.mockResolvedValueOnce(
      new Response(JSON.stringify({ error: 'SERVER_ERROR' }), { status: 500 })
    );

    const response = await GET(callbackRequest('revoked-code'));

    expect(response.status).toBe(307);
    expect(response.headers.get('location')).toContain('/auth/login?error=google_auth_failed');
    expect(mockCookieStore.set).not.toHaveBeenCalled();
  });

  it('redirige vers ?error=google_auth_failed quand le backend 401 a un corps non JSON', async () => {
    mockFetch.mockResolvedValueOnce(new Response('Bad Gateway', { status: 502 }));

    const response = await GET(callbackRequest('revoked-code'));

    expect(response.status).toBe(307);
    expect(response.headers.get('location')).toContain('/auth/login?error=google_auth_failed');
    expect(mockCookieStore.set).not.toHaveBeenCalled();
  });

  it('redirige vers ?error=google_network quand le backend est injoignable', async () => {
    mockFetch.mockRejectedValueOnce(new TypeError('fetch failed'));

    const response = await GET(callbackRequest('any-code'));

    expect(response.status).toBe(307);
    expect(response.headers.get('location')).toContain('/auth/login?error=google_network');
  });

  it('redirige vers ?error=google_no_account quand le payload ne contient pas de token', async () => {
    mockFetch.mockResolvedValueOnce(
      new Response(JSON.stringify({ success: false }), { status: 200 })
    );

    const response = await GET(callbackRequest('unlinked-code'));

    expect(response.status).toBe(307);
    expect(response.headers.get('location')).toContain('/auth/login?error=google_no_account');
    expect(mockCookieStore.set).not.toHaveBeenCalled();
  });
});
