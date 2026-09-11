/**
 * @jest-environment node
 */
import { NextRequest } from 'next/server';

import { POST } from '../route';

/**
 * Garde anti-régression — activation de compte (incident 2026-09-10).
 *
 * Next.js 15+ passe `params` comme une **Promise** aux Route Handlers. La
 * version précédente déstructurait `params` sans `await` : `token` valait donc
 * `undefined`, l'URL amont devenait
 * `/api/v1/onboarding/invitation/undefined/activate` et le backend répondait
 * 404 `INVITATION_NOT_FOUND`. Résultat : **toute activation de compte invitée
 * échouait** via le portail web, en production comme en dev.
 *
 * Le test vérifie le contrat d'URL (le token réel est transmis, jamais
 * « undefined »), la non-exposition du token Sanctum, et la propagation des
 * codes d'erreur backend.
 */

jest.mock('@/lib/backend-url', () => ({
  resolveBackendBaseUrl: jest.fn(() => 'https://backend.example.com'),
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

function activateRequest(): NextRequest {
  return new NextRequest(
    'https://web.example.com/api/v1/onboarding/invitation/tok-123/activate',
    {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ password: 'Abcd1234', password_confirmation: 'Abcd1234' }),
    },
  );
}

function jsonResponse(body: unknown, status: number): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'Content-Type': 'application/json' },
  });
}

describe('POST /api/v1/onboarding/invitation/[token]/activate (proxy)', () => {
  it('transmet le token de l’URL au backend — jamais "undefined"', async () => {
    mockFetch.mockResolvedValue(
      jsonResponse({ data: { token: 'sanctum-token', user: { id: 1 } } }, 201),
    );

    const response = await POST(activateRequest(), {
      params: Promise.resolve({ token: 'tok-123' }),
    });

    expect(mockFetch).toHaveBeenCalledTimes(1);
    const calledUrl = String(mockFetch.mock.calls[0][0]);
    expect(calledUrl).toBe(
      'https://backend.example.com/api/v1/onboarding/invitation/tok-123/activate',
    );
    expect(calledUrl).not.toContain('undefined');
    expect(response.status).toBe(201);
  });

  it('pose le token Sanctum en cookie httpOnly et ne le renvoie jamais dans le corps', async () => {
    mockFetch.mockResolvedValue(
      jsonResponse({ data: { token: 'sanctum-secret', user: { id: 1 } } }, 201),
    );

    const response = await POST(activateRequest(), {
      params: Promise.resolve({ token: 'tok-123' }),
    });

    expect(mockCookieStore.set).toHaveBeenCalledWith(
      'leopardo_token',
      'sanctum-secret',
      expect.objectContaining({ httpOnly: true, path: '/' }),
    );

    const body = (await response.json()) as { data?: Record<string, unknown> };
    expect(body.data?.token).toBeUndefined();
    expect(body.data?.user).toEqual({ id: 1 });
  });

  it('propage le code d’erreur du backend (invitation expirée → 410)', async () => {
    mockFetch.mockResolvedValue(jsonResponse({ error: 'INVITATION_EXPIRED' }, 410));

    const response = await POST(activateRequest(), {
      params: Promise.resolve({ token: 'tok-123' }),
    });

    expect(response.status).toBe(410);
    expect(mockCookieStore.set).not.toHaveBeenCalled();
  });
});
