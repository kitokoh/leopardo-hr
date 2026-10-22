/**
 * #8760 [BOS-CHAT] — tests du client de la BOÎTE DE RÉCEPTION publique.
 *
 * Ils portent sur la traduction HTTP → état d'interface, qui est exactement là
 * où un `404` peut être présenté à tort comme une panne : le serveur renvoie
 * 404 pour un fil d'un autre tenant, un fil supprimé ET un employé non
 * autorisé, indistinguables par conception. Le contrat d'autorisation lui-même
 * est vérifié côté API (`PublicConversationTest`), pas ici.
 */

import {
  fetchInboxMessages,
  fetchInboxThread,
  fetchInboxThreads,
  sendInboxMessage,
  type InboxMessage,
} from '@/lib/public-conversation-inbox';

function jsonResponse(status: number, body: unknown): Response {
  return {
    ok: status >= 200 && status < 300,
    status,
    json: async () => body,
  } as Response;
}

const MESSAGE: InboxMessage = {
  id: 7,
  seq: 3,
  from: 'professional',
  body: 'Bien sûr, à 20 h.',
  created_at: '2026-10-20T10:00:00Z',
};

describe('fetchInboxThreads', () => {
  it('affiche les demandes renvoyées par l’API', async () => {
    const fetcher = jest.fn().mockResolvedValue(
      jsonResponse(200, {
        data: [
          {
            id: 1,
            title: 'Conversation avec Restaurant Leopardo',
            target_slug: 'resto-test',
            status: 'open',
            last_message_at: '2026-10-20T10:00:00Z',
            last_message_seq: 3,
            unread: true,
          },
        ],
      }),
    );

    const result = await fetchInboxThreads({}, fetcher as unknown as typeof fetch);

    expect(result.status).toBe('ok');
    if (result.status === 'ok') {
      expect(result.data).toHaveLength(1);
      expect(result.data[0].unread).toBe(true);
    }
  });

  it('distingue une session expirée d’une panne (401)', async () => {
    const fetcher = jest.fn().mockResolvedValue(jsonResponse(401, {}));

    const result = await fetchInboxThreads({}, fetcher as unknown as typeof fetch);

    expect(result.status).toBe('unauthenticated');
  });

  it('renvoie une erreur explicite quand le réseau tombe', async () => {
    const fetcher = jest.fn().mockRejectedValue(new TypeError('Failed to fetch'));

    const result = await fetchInboxThreads({}, fetcher as unknown as typeof fetch);

    expect(result.status).toBe('error');
  });

  it('refuse une réponse sans `data` au lieu d’afficher une liste vide trompeuse', async () => {
    const fetcher = jest.fn().mockResolvedValue(jsonResponse(200, {}));

    const result = await fetchInboxThreads({}, fetcher as unknown as typeof fetch);

    expect(result.status).toBe('error');
  });
});

describe('fetchInboxThread', () => {
  it('renvoie `not_found` pour un fil inaccessible — jamais une panne', async () => {
    const fetcher = jest.fn().mockResolvedValue(jsonResponse(404, { error: 'CONVERSATION_NOT_FOUND' }));

    const result = await fetchInboxThread(42, fetcher as unknown as typeof fetch);

    expect(result.status).toBe('not_found');
  });

  it('expose les messages avec leur sens d’origine', async () => {
    const fetcher = jest.fn().mockResolvedValue(
      jsonResponse(200, {
        data: {
          id: 42,
          title: 'Conversation avec resto',
          target_slug: 'resto-test',
          status: 'open',
          last_message_seq: 3,
          messages: [{ ...MESSAGE, from: 'client' }, MESSAGE],
        },
      }),
    );

    const result = await fetchInboxThread(42, fetcher as unknown as typeof fetch);

    expect(result.status).toBe('ok');
    if (result.status === 'ok') {
      expect(result.data.messages.map((m) => m.from)).toEqual(['client', 'professional']);
    }
  });

  it('appelle bien la surface professionnelle (et non la surface client)', async () => {
    const fetcher = jest.fn().mockResolvedValue(jsonResponse(200, { data: { messages: [] } }));

    await fetchInboxThread(42, fetcher as unknown as typeof fetch);

    expect(fetcher).toHaveBeenCalledWith(
      '/api/v1/conversations/public/42',
      expect.objectContaining({ credentials: 'include' }),
    );
  });
});

describe('fetchInboxMessages', () => {
  it('transmet le curseur `after_seq` pour rattraper sans relire l’historique', async () => {
    const fetcher = jest.fn().mockResolvedValue(
      jsonResponse(200, {
        data: [MESSAGE],
        meta: { has_more: false, next_before_seq: null, last_seq: 3 },
      }),
    );

    const result = await fetchInboxMessages(42, { afterSeq: 2 }, fetcher as unknown as typeof fetch);

    expect(fetcher.mock.calls[0][0]).toBe('/api/v1/conversations/public/42/messages?after_seq=2');
    expect(result.status).toBe('ok');
    if (result.status === 'ok') {
      expect(result.data.last_seq).toBe(3);
    }
  });

  it('transmet `before_seq` pour remonter l’historique', async () => {
    const fetcher = jest.fn().mockResolvedValue(
      jsonResponse(200, { data: [], meta: { has_more: true, next_before_seq: 1, last_seq: 3 } }),
    );

    const result = await fetchInboxMessages(42, { beforeSeq: 5, perPage: 20 }, fetcher as unknown as typeof fetch);

    expect(fetcher.mock.calls[0][0]).toBe('/api/v1/conversations/public/42/messages?before_seq=5&per_page=20');
    expect(result.status).toBe('ok');
    if (result.status === 'ok') {
      expect(result.data.has_more).toBe(true);
    }
  });

  it('refuse un corps qui n’est pas un tableau de messages', async () => {
    const fetcher = jest.fn().mockResolvedValue(jsonResponse(200, { data: { oops: true } }));

    const result = await fetchInboxMessages(42, {}, fetcher as unknown as typeof fetch);

    expect(result.status).toBe('error');
  });
});

describe('sendInboxMessage', () => {
  it('ne considère le message envoyé qu’après un 201 du serveur', async () => {
    const fetcher = jest.fn().mockResolvedValue(jsonResponse(201, { data: MESSAGE }));

    const result = await sendInboxMessage(42, 'Bien sûr, à 20 h.', fetcher as unknown as typeof fetch);

    expect(result.status).toBe('ok');
    if (result.status === 'ok') {
      expect(result.data.seq).toBe(3);
    }
  });

  it('remonte les erreurs de validation champ par champ (422)', async () => {
    const fetcher = jest.fn().mockResolvedValue(
      jsonResponse(422, { errors: { body: ['Le message ne peut pas être vide.'] } }),
    );

    const result = await sendInboxMessage(42, '   ', fetcher as unknown as typeof fetch);

    expect(result.status).toBe('invalid');
    if (result.status === 'invalid') {
      expect(result.fields?.body).toHaveLength(1);
    }
  });

  it('traite un 429 (quota) comme une erreur, pas comme un succès', async () => {
    const fetcher = jest.fn().mockResolvedValue(jsonResponse(429, {}));

    const result = await sendInboxMessage(42, 'Bonjour', fetcher as unknown as typeof fetch);

    expect(result.status).toBe('error');
  });

  it('n’invente aucun succès quand le réseau échoue', async () => {
    const fetcher = jest.fn().mockRejectedValue(new TypeError('Failed to fetch'));

    const result = await sendInboxMessage(42, 'Bonjour', fetcher as unknown as typeof fetch);

    expect(result.status).toBe('error');
  });
});
