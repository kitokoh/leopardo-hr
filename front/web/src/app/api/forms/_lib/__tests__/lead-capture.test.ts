/**
 * Environnement Node obligatoire : jsdom n'expose ni `Request` ni `Response`,
 * dont dépend `next/server` (et donc le client HTTP de capture de leads).
 * @jest-environment node
 */
// #7301 — robustesse de la persistance durable des leads marketing.
//
// Ce que ces tests verrouillent (régression = perte de leads silencieuse) :
//   1. un 5xx transitoire (cold start Render) est RÉESSAYÉ ;
//   2. un 4xx définitif (signature invalide, contrat refusé) ne l'est PAS ;
//   3. l'épuisement des essais émet une ALERTE (niveau erreur) — l'échec n'est
//      plus avalé par un `console.info` ;
//   4. l'alerte est relayée au webhook configuré (`MARKETING_ALERT_WEBHOOK_URL`) ;
//   5. la réponse n'attend pas un cold start : au-delà du budget, l'écriture
//      part en tâche de fond et l'état renvoyé est `pending` (jamais un faux
//      « persisté »).
//
// ⚠️ Les réglages sont lus à l'IMPORT du module : ils sont donc posés ici,
// avant l'import dynamique de `beforeAll`.

import type { NextRequest } from 'next/server';

process.env.MARKETING_LEAD_PERSIST_BACKOFF_MS = '10,10';
process.env.MARKETING_LEAD_FAST_PATH_BUDGET_MS = '60';
process.env.MARKETING_LEAD_PERSIST_TIMEOUT_MS = '500';
delete process.env.MARKETING_CRM_WEBHOOK_URL;
delete process.env.MARKETING_EMAIL_WEBHOOK_URL;
delete process.env.MARKETING_ALERT_WEBHOOK_URL;

type LeadCaptureModule = typeof import('../lead-capture');

let leadCapture: LeadCaptureModule;
let fetchMock: jest.Mock;
let errorSpy: jest.SpyInstance;

const LEAD = {
  id: 'signup_test_1',
  type: 'signup',
  email: 'lead@example.com',
  locale: 'fr',
  page: '/signup',
  source: 'signup_form',
  timestamp: '2026-09-13T00:00:00.000Z',
  ip: '203.0.113.7',
  data: {},
};

function jsonResponse(status: number): Response {
  return new Response(JSON.stringify({ ok: status < 400 }), {
    status,
    headers: { 'Content-Type': 'application/json' },
  });
}

function fakeRequest(): NextRequest {
  return {
    headers: { get: () => null },
  } as unknown as NextRequest;
}

function persistCalls(): string[] {
  return fetchMock.mock.calls
    .map((call) => String(call[0]))
    .filter((url) => url.includes('/marketing/leads'));
}

beforeAll(async () => {
  leadCapture = await import('../lead-capture');
});

beforeEach(() => {
  fetchMock = jest.fn();
  global.fetch = fetchMock as unknown as typeof fetch;
  errorSpy = jest.spyOn(console, 'error').mockImplementation(() => undefined);
});

afterEach(() => {
  jest.restoreAllMocks();
  delete process.env.MARKETING_ALERT_WEBHOOK_URL;
});

describe('#7301 — réessais de la persistance', () => {
  it('réessaie un 5xx transitoire et finit par écrire le lead', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse(503)).mockResolvedValueOnce(jsonResponse(201));

    const ok = await leadCapture.persistLeadWithRetry(LEAD, false, false);

    expect(ok).toBe(true);
    expect(persistCalls()).toHaveLength(2);
    expect(errorSpy).not.toHaveBeenCalled();
  });

  it('ne réessaie PAS un 4xx définitif mais alerte quand même', async () => {
    fetchMock.mockResolvedValue(jsonResponse(400));

    const ok = await leadCapture.persistLeadWithRetry(LEAD, false, false);

    expect(ok).toBe(false);
    // Un seul appel : retenter un 400 (signature invalide) ne sert à rien.
    expect(persistCalls()).toHaveLength(1);
    expect(errorSpy).toHaveBeenCalledTimes(1);
    expect(String(errorSpy.mock.calls[0][0])).toContain('marketing.lead.persist_alert');
  });

  it('épuise les essais sur 5xx répété, puis ALERTE (plus de perte silencieuse)', async () => {
    fetchMock.mockResolvedValue(jsonResponse(503));

    const ok = await leadCapture.persistLeadWithRetry(LEAD, false, false);

    expect(ok).toBe(false);
    expect(persistCalls()).toHaveLength(3);
    const alert = String(errorSpy.mock.calls[0][0]);
    expect(alert).toContain('marketing.lead.persist_alert');
    expect(alert).toContain('"severity":"error"');
    expect(alert).toContain('lead@example.com');
  });

  it('relaie l’alerte au webhook configuré', async () => {
    process.env.MARKETING_ALERT_WEBHOOK_URL = 'https://alerts.example.test/hook';
    fetchMock.mockResolvedValue(jsonResponse(500));

    await leadCapture.persistLeadWithRetry(LEAD, false, false);
    // Laisse partir le relais fire-and-forget.
    await new Promise((resolve) => setTimeout(resolve, 20));

    const alertCalls = fetchMock.mock.calls.filter(
      (call) => String(call[0]) === 'https://alerts.example.test/hook'
    );
    expect(alertCalls).toHaveLength(1);
    expect(String(alertCalls[0][1].body)).toContain('marketing.lead.persist_alert');
  });

  it('ne fait jamais échouer la capture si la persistance est indisponible', async () => {
    fetchMock.mockRejectedValue(new Error('ECONNREFUSED'));

    await expect(leadCapture.persistLeadWithRetry(LEAD, false, false)).resolves.toBe(false);
  });
});

describe('#7301 — la réponse n’attend pas un cold start', () => {
  it('renvoie `persisted` quand l’API répond dans le budget', async () => {
    fetchMock.mockResolvedValue(jsonResponse(201));

    const result = await leadCapture.captureMarketingLead(fakeRequest(), {
      type: 'signup',
      email: 'lead@example.com',
    });

    expect(result.persisted).toBe('persisted');
    expect(result.id).toMatch(/^signup_/);
  });

  it('renvoie `pending` (et poursuit l’écriture) quand l’API dépasse le budget', async () => {
    // Réponse plus lente que le budget de 60 ms : la réponse part sans attendre.
    fetchMock.mockImplementation(
      () =>
        new Promise((resolve) => {
          setTimeout(() => resolve(jsonResponse(201)), 150);
        })
    );

    const startedAt = Date.now();
    const result = await leadCapture.captureMarketingLead(fakeRequest(), {
      type: 'signup',
      email: 'lead@example.com',
    });
    const elapsed = Date.now() - startedAt;

    expect(result.persisted).toBe('pending');
    expect(elapsed).toBeLessThan(120);
    // L'écriture se poursuit malgré la réponse déjà envoyée.
    await new Promise((resolve) => setTimeout(resolve, 200));
    expect(persistCalls().length).toBeGreaterThan(0);
  });

  it('renvoie `failed` quand toutes les tentatives échouent dans le budget', async () => {
    fetchMock.mockResolvedValue(jsonResponse(400));

    const result = await leadCapture.captureMarketingLead(fakeRequest(), {
      type: 'newsletter',
      email: 'lead@example.com',
    });

    expect(result.persisted).toBe('failed');
    expect(errorSpy).toHaveBeenCalled();
  });
});

describe('#7301 — settleWithin', () => {
  it('rend `pending` si la tâche dépasse le budget', async () => {
    const slow = new Promise<boolean>((resolve) => {
      setTimeout(() => resolve(true), 200);
    });

    await expect(leadCapture.settleWithin(slow, 20)).resolves.toBe('pending');
  });

  it('rend `failed` si la tâche échoue', async () => {
    await expect(leadCapture.settleWithin(Promise.resolve(false), 200)).resolves.toBe('failed');
  });
});
