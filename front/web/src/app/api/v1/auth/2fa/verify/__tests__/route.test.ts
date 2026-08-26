/**
 * @jest-environment node
 */
import { NextRequest } from 'next/server';
import { POST } from '../route';

/**
 * Issue #5612 — test d'intégration du route handler 2FA verify.
 *
 * Mécanique testée :
 *   challenge valide → cookie httpOnly `leopardo_token` posé + payload sans
 *   token brut renvoyé au navigateur ;
 *   remember_device=true → cookie `mfa_remember_*` du backend re-transmis ;
 *   code invalide / challenge expiré → erreur backend pass-through ;
 *   backend injoignable → 502 NETWORK_ERROR.
 *
 * Le fetch backend est mocké : test hermétique (aucune dépendance réseau).
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
});

function verifyRequest(body: unknown): NextRequest {
  const url = 'https://app.example.com/api/v1/auth/2fa/verify';
  const request = new NextRequest(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'Accept-Language': 'fr' },
    body: JSON.stringify(body),
  });

  return request;
}

function jsonResponse(body: unknown, status: number, headers: Record<string, string> = {}): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'Content-Type': 'application/json', ...headers },
  });
}

describe('POST /api/v1/auth/2fa/verify', () => {
  it('stores the Sanctum token in the httpOnly cookie and strips it from the payload', async () => {
    mockFetch.mockResolvedValueOnce(
      jsonResponse(
        {
          data: { id: 42, email: 'manager@company.test' },
          token: 'sanctum-token-abc',
          token_type: 'Bearer',
          token_expires_at: '2026-09-02T00:00:00Z',
        },
        200,
      ),
    );

    const response = await POST(
      verifyRequest({ challenge_token: 'ch-123', code: '123456' }),
    );

    expect(response.status).toBe(200);
    const body = await response.json();
    expect(body.token).toBeUndefined();
    expect(body.data).toEqual({ id: 42, email: 'manager@company.test' });

    const cookieCall = mockCookieStore.set.mock.calls.find(([name]) => name === 'leopardo_token');
    expect(cookieCall).toBeDefined();
    expect(cookieCall?.[1]).toBe('sanctum-token-abc');
    expect(cookieCall?.[2]).toEqual(
      expect.objectContaining({ httpOnly: true, sameSite: 'strict', path: '/' }),
    );
  });

  it('forwards the mfa_remember_* cookie when remember_device=true', async () => {
    mockFetch.mockResolvedValueOnce(
      jsonResponse(
        {
          data: { id: 7, email: 'karim@company.test' },
          token: 'tok',
          token_type: 'Bearer',
          token_expires_at: null,
        },
        200,
        { 'Set-Cookie': 'mfa_remember_7=abc123; expires=Wed, 25 Sep 2026 00:00:00 GMT; path=/; httponly' },
      ),
    );

    const response = await POST(
      verifyRequest({ challenge_token: 'ch-456', code: '654321', remember_device: true }),
    );

    expect(response.status).toBe(200);
    const rememberCall = mockCookieStore.set.mock.calls.find(([name]) => String(name).startsWith('mfa_remember_'));
    expect(rememberCall).toBeDefined();
    expect(rememberCall?.[0]).toBe('mfa_remember_7');
    expect(rememberCall?.[1]).toBe('abc123');
    expect(rememberCall?.[2]).toEqual(
      expect.objectContaining({ httpOnly: true, maxAge: 60 * 60 * 24 * 30 }),
    );
  });

  it('passes through backend errors (invalid code / expired challenge)', async () => {
    mockFetch.mockResolvedValueOnce(
      jsonResponse({ error: 'TWO_FA_INVALID', message: 'Code invalide.' }, 422),
    );

    const response = await POST(
      verifyRequest({ challenge_token: 'ch-expired', recovery_code: 'AAAA-BBBB-CCCC' }),
    );

    expect(response.status).toBe(422);
    const body = await response.json();
    expect(body.error).toBe('TWO_FA_INVALID');
    expect(mockCookieStore.set).not.toHaveBeenCalled();
  });

  it('returns 502 when the backend is unreachable', async () => {
    mockFetch.mockRejectedValueOnce(new TypeError('fetch failed'));

    const response = await POST(verifyRequest({ challenge_token: 'ch', code: '123456' }));

    expect(response.status).toBe(502);
    const body = await response.json();
    expect(body.error).toBe('NETWORK_ERROR');
    expect(mockCookieStore.set).not.toHaveBeenCalled();
  });
});
