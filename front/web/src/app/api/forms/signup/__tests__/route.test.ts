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

function makeRequest(
  body: Record<string, unknown>,
  headers: Record<string, string> = {},
): NextRequest {
  return new NextRequest('http://localhost/api/forms/signup', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', ...headers },
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

  it("utilise le pays de l'en-tête plateforme `x-vercel-ip-country` (Vercel)", async () => {
    // ⚠️ Régression corrigée le 2026-09-13 : ce test fabriquait auparavant un
    // `request.geo`, propriété que **Next 16 ne fournit plus** (les extensions
    // `geo`/`ip` ont été retirées de `NextRequest`) — il validait donc une
    // fiction pendant que la production répondait COUNTRY_REQUIRED à 100 % des
    // visiteurs. La source réelle est l'en-tête injecté par l'hébergeur.
    const response = await POST(
      makeRequest(
        { email: 'fondateur@techcorp.dz', company: 'TechCorp' },
        { 'x-vercel-ip-country': 'tn' },
      ),
    );

    expect(response.status).toBe(200);
    expect(lastBackendBody(fetchMock).country).toBe('TN');
  });

  it("utilise aussi l'en-tête Cloudflare `cf-ipcountry`", async () => {
    const response = await POST(
      makeRequest(
        { email: 'fondateur@techcorp.dz', company: 'TechCorp' },
        { 'cf-ipcountry': 'MA' },
      ),
    );

    expect(response.status).toBe(200);
    expect(lastBackendBody(fetchMock).country).toBe('MA');
  });

  it("ignore un en-tête de pays invalide (pas un code ISO à 2 lettres)", async () => {
    // Une valeur non exploitable ne doit pas être transmise au backend (qui
    // rejetterait la demande) : on retombe sur COUNTRY_REQUIRED, donc sur le
    // sélecteur de pays côté UI.
    const response = await POST(
      makeRequest(
        { email: 'fondateur@techcorp.dz', company: 'TechCorp' },
        { 'x-vercel-ip-country': 'unknown' },
      ),
    );

    expect(response.status).toBe(422);
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it('conserve le pays envoyé par le formulaire (repli) comme priorité', async () => {
    const response = await POST(
      makeRequest(
        { email: 'fondateur@techcorp.dz', company: 'TechCorp', country: 'dz' },
        { 'x-vercel-ip-country': 'FR' },
      ),
    );

    expect(response.status).toBe(200);
    // Le choix explicite de l'utilisateur prime sur la détection.
    expect(lastBackendBody(fetchMock).country).toBe('DZ');
  });

  it('tolère encore un `geo` de runtime (compatibilité)', async () => {
    const request = makeRequest({ email: 'fondateur@techcorp.dz', company: 'TechCorp' });
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

describe('#7251 — repli sur le parcours guidé si la vérification e-mail est indisponible', () => {
  let fetchMock: jest.Mock;

  beforeEach(() => {
    jest.clearAllMocks();
    mockedLeadCapture.areFormsEnabled.mockReturnValue(true);
    mockedLeadCapture.getClientIp.mockReturnValue(`10.1.0.${Math.floor(Math.random() * 250) + 1}`);
    fetchMock = jest.fn();
    global.fetch = fetchMock as unknown as typeof fetch;
  });

  function trialSignupWorkflows(): string[] {
    return fetchMock.mock.calls
      .filter(([url]) => String(url).includes('/trial/signup'))
      .map(([, init]) => {
        const body = JSON.parse(String((init as RequestInit).body)) as {
          requestedWorkflow?: string;
        };
        return String(body.requestedWorkflow);
      });
  }

  it("bascule en guided_trial quand l'envoi du code échoue, et rend un espace suivi", async () => {
    const provisioningToken = 'b'.repeat(64);

    fetchMock
      .mockResolvedValueOnce(
        backendResponse({ success: false, error: 'TRIAL_OTP_SEND_FAILED' }, 503),
      )
      .mockResolvedValueOnce(
        backendResponse({
          success: true,
          data: {
            status: 'provisioning_sandbox',
            email: 'fondateur@techcorp.dz',
            provisioning_token: provisioningToken,
          },
        }),
      );

    const response = await POST(
      makeRequest({ email: 'fondateur@techcorp.dz', company: 'TechCorp', country: 'DZ' }),
    );
    const payload = (await response.json()) as {
      success: boolean;
      data: { nextStep: string; provisioning_token?: string };
    };

    expect(trialSignupWorkflows()).toEqual(['self_service', 'guided_trial']);
    expect(response.status).toBe(200);
    expect(payload.data.nextStep).toBe('tracking');
    expect(payload.data.provisioning_token).toBe(provisioningToken);
  });

  it('ne relance pas en guided_trial sur une erreur de validation', async () => {
    fetchMock.mockResolvedValueOnce(
      backendResponse({ success: false, error: 'VALIDATION_ERROR' }, 422),
    );

    const response = await POST(
      makeRequest({ email: 'fondateur@techcorp.dz', company: 'TechCorp', country: 'DZ' }),
    );

    expect(trialSignupWorkflows()).toEqual(['self_service']);
    expect(response.status).toBe(422);
  });

  it("conserve la vérification par e-mail quand l'envoi réussit", async () => {
    fetchMock.mockResolvedValueOnce(
      backendResponse({
        success: true,
        data: { email: 'fondateur@techcorp.dz', status: 'pending_verification' },
      }),
    );

    const response = await POST(
      makeRequest({ email: 'fondateur@techcorp.dz', company: 'TechCorp', country: 'DZ' }),
    );
    const payload = (await response.json()) as { data: { nextStep: string } };

    expect(trialSignupWorkflows()).toEqual(['self_service']);
    expect(payload.data.nextStep).toBe('verify');
  });
});
