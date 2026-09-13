/**
 * Route serveur : environnement Node (jsdom n'expose pas `Request`/`Response`).
 * @jest-environment node
 */
import { NextRequest } from 'next/server';
import { POST } from '../route';
import * as leadCapture from '../../_lib/lead-capture';

const cookieSet = jest.fn();

jest.mock('../../_lib/lead-capture', () => ({
  areFormsEnabled: jest.fn(() => true),
  formsDisabledResponse: jest.fn(),
  getClientIp: jest.fn(() => '10.0.1.1'),
}));

jest.mock('next/headers', () => ({
  cookies: jest.fn(async () => ({ set: cookieSet })),
}));

const mockedLeadCapture = leadCapture as jest.Mocked<typeof leadCapture>;

function makeRequest(body: Record<string, unknown>): NextRequest {
  return new NextRequest('http://localhost/api/forms/verify', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  });
}

function backendResponse(payload: unknown, status = 201): Response {
  return {
    ok: status >= 200 && status < 300,
    status,
    json: async () => payload,
  } as unknown as Response;
}

describe('POST /api/forms/verify — auto-connexion après vérification du code', () => {
  let fetchMock: jest.Mock;

  beforeEach(() => {
    jest.clearAllMocks();
    mockedLeadCapture.areFormsEnabled.mockReturnValue(true);
    mockedLeadCapture.getClientIp.mockReturnValue(`10.0.1.${Math.floor(Math.random() * 250) + 1}`);
    fetchMock = jest.fn().mockResolvedValue(
      backendResponse({
        success: true,
        message: 'Espace prêt',
        data: {
          token: '42|plainTextTokenValue1234567890',
          company: { id: 'c-1', name: 'TechCorp', slug: 'techcorp' },
        },
      }),
    );
    global.fetch = fetchMock as unknown as typeof fetch;
  });

  it('pose le cookie de session httpOnly et NE renvoie PAS le jeton au client', async () => {
    const response = await POST(makeRequest({ email: 'fondateur@techcorp.dz', code: '123456' }));
    const payload = (await response.json()) as {
      data: Record<string, unknown>;
    };

    // Le jeton vit dans le cookie httpOnly — jamais dans le JSON.
    expect(cookieSet).toHaveBeenCalledTimes(1);
    const [name, value, options] = cookieSet.mock.calls[0] as [string, string, Record<string, unknown>];
    expect(name).toBe('leopardo_token');
    expect(value).toBe('42|plainTextTokenValue1234567890');
    expect(options.httpOnly).toBe(true);
    expect(options.sameSite).toBe('strict');
    expect(options.path).toBe('/');

    expect(payload.data.token).toBeUndefined();
    expect(payload.data.sessionEstablished).toBe(true);
    // Les données utiles restent présentes pour l'écran de succès.
    expect(payload.data.company).toBeDefined();
  });

  it('ne pose aucun cookie si le backend ne renvoie pas de jeton (repli)', async () => {
    fetchMock.mockResolvedValue(
      backendResponse({ success: true, message: 'Espace prêt', data: { company: { id: 'c-1' } } }),
    );

    const response = await POST(makeRequest({ email: 'fondateur@techcorp.dz', code: '123456' }));
    const payload = (await response.json()) as { data: { sessionEstablished: boolean } };

    expect(cookieSet).not.toHaveBeenCalled();
    expect(payload.data.sessionEstablished).toBe(false);
  });

  it('propage l’erreur du backend sans poser de cookie (code invalide)', async () => {
    fetchMock.mockResolvedValue(
      backendResponse(
        { success: false, error: 'INVALID_OR_EXPIRED_CODE', message: 'Code invalide ou expiré.' },
        400,
      ),
    );

    const response = await POST(makeRequest({ email: 'fondateur@techcorp.dz', code: '000000' }));

    expect(response.status).toBe(400);
    expect(cookieSet).not.toHaveBeenCalled();
    const payload = (await response.json()) as { error: string };
    expect(payload.error).toBe('INVALID_OR_EXPIRED_CODE');
  });
});
