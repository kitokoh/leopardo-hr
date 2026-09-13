/**
 * @jest-environment node
 */
import { NextRequest } from 'next/server';

import { POST } from '../route';

/**
 * Garde anti-régression — #7251.
 *
 * La vitrine envoie `requestedWorkflow: 'self_service'`, qui exige l'envoi d'un
 * code de vérification par e-mail. Quand le transport e-mail est indisponible,
 * l'API répond `TRIAL_OTP_SEND_FAILED` et le prospect perdait son essai : la
 * vitrine retombait sur une promesse de rappel « sous 24 h » alors qu'aucun
 * espace n'était provisionné. On vérifie ici le repli automatique vers le
 * parcours guidé (sans dépendance au mailer).
 */

jest.mock('@/lib/backend-url', () => ({
  resolveBackendBaseUrl: jest.fn(() => 'https://backend.example.com'),
}));

jest.mock('../../_lib/lead-capture', () => ({
  areFormsEnabled: jest.fn(() => true),
  formsDisabledResponse: jest.fn(),
  getClientIp: jest.fn(() => '203.0.113.10'),
  captureMarketingLead: jest.fn(async () => ({
    id: 'lead-test-1',
    emailForwarded: false,
    crmForwarded: false,
  })),
}));

const mockFetch = jest.fn();

beforeEach(() => {
  jest.clearAllMocks();
  global.fetch = mockFetch as unknown as typeof fetch;
});

function signupRequest(): NextRequest {
  return new NextRequest('https://web.example.com/api/forms/signup', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      email: 'prospect@example.com',
      company: 'Prospect SARL',
      country: 'DZ',
      requestedWorkflow: 'self_service',
    }),
  });
}

function jsonResponse(body: unknown, status: number): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'Content-Type': 'application/json' },
  });
}

function requestedWorkflows(): string[] {
  return mockFetch.mock.calls.map((call) => {
    const body = JSON.parse((call[1] as { body: string }).body) as {
      requestedWorkflow?: string;
    };
    return String(body.requestedWorkflow);
  });
}

describe('#7251 — repli sur le parcours guidé quand la vérification e-mail est indisponible', () => {
  it('bascule en guided_trial si l’envoi du code échoue, et rend un espace suivi', async () => {
    const provisioningToken = 'b'.repeat(64);

    mockFetch
      .mockResolvedValueOnce(
        jsonResponse(
          { success: false, error: 'TRIAL_OTP_SEND_FAILED' },
          503,
        ),
      )
      .mockResolvedValueOnce(
        jsonResponse(
          {
            success: true,
            data: {
              status: 'provisioning_sandbox',
              email: 'prospect@example.com',
              provisioning_token: provisioningToken,
            },
          },
          200,
        ),
      );

    const response = await POST(signupRequest());
    const payload = await response.json();

    expect(mockFetch).toHaveBeenCalledTimes(2);
    expect(requestedWorkflows()).toEqual(['self_service', 'guided_trial']);
    expect(response.status).toBe(200);
    expect(payload.success).toBe(true);
    expect(payload.data.nextStep).toBe('tracking');
    expect(payload.data.provisioning_token).toBe(provisioningToken);
  });

  it('ne relance pas en guided_trial sur une erreur de validation', async () => {
    mockFetch.mockResolvedValueOnce(
      jsonResponse({ success: false, error: 'VALIDATION_ERROR' }, 422),
    );

    const response = await POST(signupRequest());

    expect(mockFetch).toHaveBeenCalledTimes(1);
    expect(requestedWorkflows()).toEqual(['self_service']);
    expect(response.status).toBe(422);
  });

  it('conserve la vérification par e-mail quand elle fonctionne', async () => {
    mockFetch.mockResolvedValueOnce(
      jsonResponse(
        {
          success: true,
          data: { status: 'pending_verification', email: 'prospect@example.com' },
        },
        200,
      ),
    );

    const response = await POST(signupRequest());
    const payload = await response.json();

    expect(mockFetch).toHaveBeenCalledTimes(1);
    expect(payload.data.nextStep).toBe('verify');
  });
});
