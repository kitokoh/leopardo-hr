/**
 * Tests de l'adaptateur réseau du tunnel public — paiement et e-billet.
 *
 * Ce que ces tests protègent (et pourquoi ils existent) :
 *
 * - **Un échec ne doit jamais ressembler à un succès.** `initiatePayment` et
 *   `fetchTicketPdf` lèvent sur erreur HTTP : un `null` silencieux ferait
 *   croire au voyageur qu'un paiement est engagé ou qu'un billet est
 *   téléchargeable alors que rien n'existe côté serveur.
 * - **403 = code de validation invalide.** Le code du billet est le secret
 *   partagé ; l'API répond 403 et l'UI doit pouvoir le distinguer d'un 404.
 * - **Idempotence.** Un rejeu réseau (même `idempotencyKey`) doit renvoyer le
 *   MÊME paiement, sans créer de seconde transaction.
 * - **L'URL signée n'est jamais stockée.** Elle expire en 30 minutes : la
 *   garder en cache ou en `localStorage` produirait un lien mort.
 */

import {
  fetchTicketPdf,
  initiatePayment,
  ONLINE_PAYMENT_PROVIDERS,
  TravelApiError,
} from '../travel/infrastructure/booking-gateway';

const API_BASE = 'https://api.test.local';

function jsonResponse(body: unknown, status = 200): Response {
  return {
    ok: status >= 200 && status < 300,
    status,
    text: async () => JSON.stringify(body),
  } as unknown as Response;
}

function emptyResponse(status: number): Response {
  return {
    ok: status >= 200 && status < 300,
    status,
    text: async () => '',
  } as unknown as Response;
}

describe('initiatePayment', () => {
  const originalFetch = global.fetch;

  afterEach(() => {
    global.fetch = originalFetch;
    jest.restoreAllMocks();
  });

  it('envoie la référence, le moyen de paiement et la clé d’idempotence', async () => {
    const fetchMock = jest.fn().mockResolvedValue(
      jsonResponse({
        data: {
          reference: 'PAY-1',
          provider_reference: 'PVIT-ABC',
          status: 'pending',
        },
      }),
    );
    global.fetch = fetchMock as unknown as typeof fetch;

    const result = await initiatePayment({
      bookingReference: 'BK-42',
      provider: 'momo',
      idempotencyKey: 'idem-1',
    });

    expect(result).toEqual({
      reference: 'PAY-1',
      providerReference: 'PVIT-ABC',
      status: 'pending',
    });

    const [url, init] = fetchMock.mock.calls[0] as [string, RequestInit];
    expect(url).toContain('public/travel/marketplace/payments/initiate');
    expect(init.method).toBe('POST');
    expect(JSON.parse(String(init.body))).toEqual({
      booking_reference: 'BK-42',
      provider_code: 'momo',
      idempotency_key: 'idem-1',
    });
  });

  it('ne propose jamais `cash` en ligne (encaissable au guichet seulement)', () => {
    expect(ONLINE_PAYMENT_PROVIDERS).not.toContain('cash');
    expect([...ONLINE_PAYMENT_PROVIDERS]).toEqual(['momo', 'card', 'pvit']);
  });

  it('lève une TravelApiError 403 quand le code de validation est invalide', async () => {
    global.fetch = jest
      .fn()
      .mockResolvedValue(jsonResponse({ message: 'Code de validation invalide.' }, 403)) as unknown as typeof fetch;

    await expect(
      initiatePayment({
        bookingReference: 'BK-42',
        provider: 'card',
        idempotencyKey: 'idem-403',
      }),
    ).rejects.toMatchObject({ name: 'TravelApiError', status: 403 });

    await expect(
      initiatePayment({
        bookingReference: 'BK-42',
        provider: 'card',
        idempotencyKey: 'idem-403',
      }),
    ).rejects.toBeInstanceOf(TravelApiError);
  });

  it('lève sur 404 (réservation introuvable) au lieu de renvoyer un paiement vide', async () => {
    global.fetch = jest
      .fn()
      .mockResolvedValue(emptyResponse(404)) as unknown as typeof fetch;

    await expect(
      initiatePayment({
        bookingReference: 'BK-UNKNOWN',
        provider: 'pvit',
        idempotencyKey: 'idem-404',
      }),
    ).rejects.toMatchObject({ status: 404 });
  });

  it('est idempotent : deux appels avec la même clé renvoient le même paiement', async () => {
    const fetchMock = jest.fn().mockResolvedValue(
      jsonResponse({
        data: { reference: 'PAY-9', provider_reference: 'PVIT-9', status: 'pending' },
      }),
    );
    global.fetch = fetchMock as unknown as typeof fetch;

    const params = {
      bookingReference: 'BK-9',
      provider: 'momo' as const,
      idempotencyKey: 'same-key',
    };

    const first = await initiatePayment(params);
    const second = await initiatePayment(params);

    expect(second).toEqual(first);
    expect(fetchMock).toHaveBeenCalledTimes(2);

    // La clé transmise est identique : c'est elle qui garantit l'idempotence
    // côté serveur (un rejeu ne crée pas de seconde transaction).
    const bodies = fetchMock.mock.calls.map(
      (call) => JSON.parse(String((call as [string, RequestInit])[1].body)).idempotency_key,
    );
    expect(bodies).toEqual(['same-key', 'same-key']);
  });
});

describe('fetchTicketPdf', () => {
  const originalFetch = global.fetch;

  afterEach(() => {
    global.fetch = originalFetch;
    jest.restoreAllMocks();
  });

  it('encode le code dans l’URL et renvoie le lien signé', async () => {
    const fetchMock = jest.fn().mockResolvedValue(
      jsonResponse({
        data: {
          ticket_number: 'TK-001',
          pdf_url: 'https://storage.test/ticket.pdf?sig=abc',
          expires_in_minutes: 30,
        },
      }),
    );
    global.fetch = fetchMock as unknown as typeof fetch;

    const result = await fetchTicketPdf(7, 'code avec espaces&plus');

    expect(result).toEqual({
      ticketNumber: 'TK-001',
      pdfUrl: 'https://storage.test/ticket.pdf?sig=abc',
      expiresInMinutes: 30,
    });

    const [url] = fetchMock.mock.calls[0] as [string];
    expect(url).toContain('public/travel/marketplace/tickets/7/pdf');
    // Le code est un secret : il doit être encodé, jamais concaténé brut.
    expect(url).toContain('code=code%20avec%20espaces%26plus');
  });

  it('lève une TravelApiError 403 sur un code invalide', async () => {
    global.fetch = jest
      .fn()
      .mockResolvedValue(jsonResponse({ message: 'Code de validation invalide.' }, 403)) as unknown as typeof fetch;

    await expect(fetchTicketPdf(7, 'mauvais-code')).rejects.toMatchObject({
      name: 'TravelApiError',
      status: 403,
    });
  });

  it('lève sur 410 quand le billet a été révoqué', async () => {
    global.fetch = jest
      .fn()
      .mockResolvedValue(jsonResponse({ message: 'Ce billet a été révoqué.' }, 410)) as unknown as typeof fetch;

    await expect(fetchTicketPdf(7, 'code')).rejects.toMatchObject({ status: 410 });
  });

  it('ne met pas l’URL signée en cache (elle expire en 30 minutes)', async () => {
    const fetchMock = jest.fn().mockResolvedValue(
      jsonResponse({
        data: {
          ticket_number: 'TK-002',
          pdf_url: 'https://storage.test/ticket.pdf?sig=first',
          expires_in_minutes: 30,
        },
      }),
    );
    global.fetch = fetchMock as unknown as typeof fetch;

    await fetchTicketPdf(8, 'code');

    const [, init] = fetchMock.mock.calls[0] as [string, RequestInit];
    expect(init.cache).toBe('no-store');

    // Aucune écriture dans le stockage navigateur : un lien signé périmé
    // servi depuis un cache serait un billet mort.
    expect(window.localStorage.length).toBe(0);
    expect(window.sessionStorage.length).toBe(0);
  });
});
