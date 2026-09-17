/**
 * Environnement Node obligatoire : jsdom n'expose ni `Request` ni `Response`,
 * dont dépend `next/server`. Même contrainte que `lead-capture.test.ts`.
 * @jest-environment node
 */
// #7594 — câblage des barrières anti-bot SUR la route elle-même.
//
// Les tests unitaires de `_lib/antispam.test.ts` vérifient la RÈGLE ; ceux-ci
// vérifient le BRANCHEMENT : une route qui oublierait d'appeler la garde, ou qui
// la placerait APRÈS la validation, passerait les tests unitaires et serait
// pourtant spammable. C'est exactement le motif d'erreur de #7581/#7586 (un
// contrôle juste, mal câblé).

import { NextRequest } from 'next/server';

jest.mock('../../_lib/lead-capture', () => ({
  areFormsEnabled: jest.fn(() => true),
  formsDisabledResponse: jest.fn(
    () => new Response(JSON.stringify({ error: 'FORMS_DISABLED' }), { status: 503 }),
  ),
  getClientIp: jest.fn(() => '203.0.113.7'),
  captureMarketingLead: jest.fn(async () => ({ id: 'lead_1', emailForwarded: true, crmForwarded: true })),
}));

import { captureMarketingLead } from '../../_lib/lead-capture';
import { HONEYPOT_FIELD, FORM_RENDERED_AT_FIELD } from '../../_lib/antispam';
import { POST } from '../route';

const SITE = 'https://leopardo.example.com';
const mockedCapture = captureMarketingLead as jest.Mock;

function post(body: Record<string, unknown>, headers: Record<string, string> = {}): NextRequest {
  return new NextRequest(
    new Request(`${SITE}/api/forms/contact`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', ...headers },
      body: JSON.stringify(body),
    }),
  );
}

/** Soumission humaine plausible : formulaire rendu il y a 10 s. */
function humanBody(extra: Record<string, unknown> = {}): Record<string, unknown> {
  return {
    name: 'Fatima Meziane',
    email: 'fatima@example.com',
    subject: 'Demande de demonstration',
    message: 'Bonjour, nous souhaitons voir la paie algerienne.',
    [FORM_RENDERED_AT_FIELD]: new Date(Date.now() - 10_000).toISOString(),
    ...extra,
  };
}

beforeEach(() => {
  mockedCapture.mockClear();
});

describe('route /api/forms/contact — barrières anti-bot (#7594)', () => {
  it('capture une soumission humaine normale', async () => {
    const response = await POST(post(humanBody()));

    expect(response.status).toBe(201);
    expect(mockedCapture).toHaveBeenCalledTimes(1);
  });

  it('rejette un honeypot rempli, sans rien persister', async () => {
    const response = await POST(post(humanBody({ [HONEYPOT_FIELD]: 'https://spam.example' })));

    // Rejet SILENCIEUX : même forme qu'un succès, mais aucune capture.
    expect(response.status).toBe(201);
    expect((await response.json()).success).toBe(true);
    expect(mockedCapture).not.toHaveBeenCalled();
  });

  it('rejette une soumission instantanée (time-trap réel)', async () => {
    const response = await POST(
      post(humanBody({ [FORM_RENDERED_AT_FIELD]: new Date().toISOString() })),
    );

    expect(response.status).toBe(201);
    expect(mockedCapture).not.toHaveBeenCalled();
  });

  it('rejette une origine étrangère avec un 403 explicite', async () => {
    const response = await POST(post(humanBody(), { origin: 'https://spam.example' }));

    expect(response.status).toBe(403);
    expect((await response.json()).error).toBe('ORIGIN_NOT_ALLOWED');
    expect(mockedCapture).not.toHaveBeenCalled();
  });

  it('ne perd plus le champ company collecté par le formulaire de contact', async () => {
    await POST(post(humanBody({ company: 'TechCorp Algerie SARL' })));

    const payload = mockedCapture.mock.calls[0][1];
    expect(payload.data.company).toBe('TechCorp Algerie SARL');
  });
});
