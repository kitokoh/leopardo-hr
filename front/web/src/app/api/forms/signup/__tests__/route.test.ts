/**
 * Route serveur : environnement Node (jsdom n'expose pas `Request`/`Response`).
 * @jest-environment node
 */
import { NextRequest } from 'next/server';
import { POST } from '../route';
import * as leadCapture from '../../_lib/lead-capture';

jest.mock('../../_lib/lead-capture', () => ({
  areFormsEnabled: jest.fn(() => true),
  formsDisabledResponse: jest.fn(),
  getClientIp: jest.fn(() => '10.0.0.1'),
  captureMarketingLead: jest.fn(async () => ({
    id: 'signup_test',
    emailForwarded: false,
    crmForwarded: false,
  })),
}));

const mockedLeadCapture = leadCapture as jest.Mocked<typeof leadCapture>;

function makeRequest(body: Record<string, unknown>): NextRequest {
  return new NextRequest('http://localhost/api/forms/signup', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  });
}

function backendResponse(payload: unknown, status = 200): Response {
  return {
    ok: status >= 200 && status < 300,
    status,
    json: async () => payload,
  } as unknown as Response;
}

/** Corps JSON envoyé au backend lors du dernier appel /trial/signup. */
function lastBackendBody(fetchMock: jest.Mock): Record<string, unknown> {
  const call = fetchMock.mock.calls.find(([url]) => String(url).includes('/trial/signup'));
  if (!call) throw new Error('Aucun appel /trial/signup émis');
  return JSON.parse(String((call[1] as RequestInit).body)) as Record<string, unknown>;
}

describe('POST /api/forms/signup — contrat de la demande d’essai', () => {
  let fetchMock: jest.Mock;

  beforeEach(() => {
    jest.clearAllMocks();
    mockedLeadCapture.areFormsEnabled.mockReturnValue(true);
    mockedLeadCapture.getClientIp.mockReturnValue(`10.0.0.${Math.floor(Math.random() * 250) + 1}`);
    fetchMock = jest.fn().mockResolvedValue(
      backendResponse({
        success: true,
        data: { email: 'fondateur@techcorp.dz', status: 'pending_verification' },
      }),
    );
    global.fetch = fetchMock as unknown as typeof fetch;
  });

  it("transmet la langue choisie par l'utilisateur au backend (langue de l'e-mail OTP)", async () => {
    const response = await POST(
      makeRequest({
        email: 'fondateur@techcorp.dz',
        company: 'TechCorp',
        country: 'DZ',
        // L'utilisateur navigue en turc : l'e-mail OTP doit partir en turc,
        // même si le pays détecté (DZ) a le français pour langue par défaut.
        locale: 'tr',
      }),
    );

    expect(response.status).toBe(200);
    expect(lastBackendBody(fetchMock).locale).toBe('tr');
  });

  it('utilise le pays détecté (géolocalisation) quand le formulaire n’en envoie aucun', async () => {
    const request = makeRequest({ email: 'fondateur@techcorp.dz', company: 'TechCorp' });
    // Vercel enrichit la requête avec `geo` — le formulaire simplifié ne
    // demande plus le pays à l'utilisateur.
    Object.assign(request as unknown as Record<string, unknown>, {
      geo: { country: 'tn' },
    });

    const response = await POST(request);

    expect(response.status).toBe(200);
    expect(lastBackendBody(fetchMock).country).toBe('TN');
  });

  it('expose le flux vérifié (pending_verification → nextStep: verify)', async () => {
    const response = await POST(
      makeRequest({ email: 'fondateur@techcorp.dz', company: 'TechCorp', country: 'DZ', locale: 'fr' }),
    );
    const payload = (await response.json()) as {
      provisioned: boolean;
      data: { nextStep: string; status: string };
    };

    expect(payload.provisioned).toBe(true);
    expect(payload.data.nextStep).toBe('verify');
    expect(payload.data.status).toBe('pending_verification');
  });

  it('répond COUNTRY_REQUIRED (422) quand la géolocalisation est indisponible', async () => {
    // Ni pays dans la requête, ni `geo` fourni par la plateforme : le
    // formulaire simplifié ne demande plus le pays, on doit donc le signaler
    // par un code dédié pour que l'UI n'affiche le sélecteur que dans ce cas.
    const response = await POST(makeRequest({ email: 'fondateur@techcorp.dz', company: 'TechCorp' }));

    expect(response.status).toBe(422);
    const payload = (await response.json()) as { error: string };
    expect(payload.error).toBe('COUNTRY_REQUIRED');
    // Aucun appel backend inutile : le 422 est détecté en amont.
    expect(fetchMock).not.toHaveBeenCalled();
  });
});
