/**
 * #8760 [BOS-CHAT] — Tests du client de messagerie publique.
 *
 * Ces tests vérifient le CONTRAT HTTP tel qu'il est consommé par l'interface :
 * chaque code de statut doit produire un état explicite, jamais un faux succès.
 * C'est le point le plus facile à casser silencieusement — un `404` traité
 * comme un succès afficherait un fil vide au lieu de « conversation
 * indisponible », et un `401` comme une erreur réseau enverrait l'utilisateur
 * chercher un problème de connexion inexistant.
 */
import {
  CONVERSATION_MESSAGE_MAX,
  fetchConversation,
  fetchConversationMessages,
  fetchMyConversations,
  sendConversationMessage,
  startConversation,
} from '@/lib/public-conversation-api';

/**
 * Réponse HTTP simulée.
 *
 * On n'utilise PAS `new Response(...)` : l'environnement jest du dépôt est
 * `jsdom`, où `Response` n'est pas défini globalement. Le pattern du dépôt
 * (`contact-dialog.test.tsx`) est un objet minimal portant `status`, `ok` et
 * `json()` — c'est exactement ce que consomme le client d'API.
 */
function jsonResponse(status: number, body: unknown): Response {
  return {
    status,
    ok: status >= 200 && status < 300,
    json: async () => body,
  } as unknown as Response;
}

describe('public-conversation-api', () => {
  describe('fetchMyConversations', () => {
    it('returns the list on 200', async () => {
      const fetcher = jest.fn().mockResolvedValue(
        jsonResponse(200, {
          data: [
            {
              company_id: 'c-1',
              thread_id: 7,
              title: 'Conversation avec Restaurant Leopardo',
              target_slug: 'resto-leopardo',
              last_message_at: '2026-10-20T10:00:00+00:00',
              last_message_seq: 3,
              unread: true,
            },
          ],
        }),
      );

      const result = await fetchMyConversations(fetcher as unknown as typeof fetch);

      expect(result.status).toBe('ok');
      if (result.status === 'ok') {
        expect(result.data).toHaveLength(1);
        expect(result.data[0].thread_id).toBe(7);
        expect(result.data[0].unread).toBe(true);
      }
    });

    it('maps 401 to unauthenticated, not to a network error', async () => {
      const fetcher = jest.fn().mockResolvedValue(jsonResponse(401, { message: 'Unauthenticated.' }));

      const result = await fetchMyConversations(fetcher as unknown as typeof fetch);

      expect(result.status).toBe('unauthenticated');
    });

    it('maps a network failure to error', async () => {
      const fetcher = jest.fn().mockRejectedValue(new Error('offline'));

      const result = await fetchMyConversations(fetcher as unknown as typeof fetch);

      expect(result.status).toBe('error');
    });

    it('maps a 500 to error rather than an empty list', async () => {
      const fetcher = jest.fn().mockResolvedValue(jsonResponse(500, { message: 'boom' }));

      const result = await fetchMyConversations(fetcher as unknown as typeof fetch);

      expect(result.status).toBe('error');
    });
  });

  describe('fetchConversation', () => {
    it('returns the thread with its messages on 200', async () => {
      const fetcher = jest.fn().mockResolvedValue(
        jsonResponse(200, {
          data: {
            company_id: 'c-1',
            thread_id: 7,
            title: 'Conversation avec Restaurant Leopardo',
            target_slug: 'resto-leopardo',
            last_message_at: '2026-10-20T10:00:00+00:00',
            last_message_seq: 2,
            unread: false,
            status: 'open',
            messages: [
              { id: 1, seq: 1, from: 'client', body: 'Bonjour', deleted: false, created_at: '2026-10-20T09:00:00+00:00' },
              { id: 2, seq: 2, from: 'professional', body: 'Bonjour, oui.', deleted: false, created_at: '2026-10-20T10:00:00+00:00' },
            ],
          },
        }),
      );

      const result = await fetchConversation('c-1', 7, fetcher as unknown as typeof fetch);

      expect(result.status).toBe('ok');
      if (result.status === 'ok') {
        expect(result.data.messages).toHaveLength(2);
        expect(result.data.messages[1].from).toBe('professional');
      }
    });

    it('maps 404 to not_found (another client, another tenant, or deleted)', async () => {
      const fetcher = jest.fn().mockResolvedValue(jsonResponse(404, { error: 'CONVERSATION_NOT_FOUND' }));

      const result = await fetchConversation('c-1', 7, fetcher as unknown as typeof fetch);

      expect(result.status).toBe('not_found');
    });

    it('encodes the company id in the URL', async () => {
      const fetcher = jest.fn().mockResolvedValue(jsonResponse(404, {}));

      await fetchConversation('c/../1', 7, fetcher as unknown as typeof fetch);

      const calledUrl = String(fetcher.mock.calls[0][0]);
      expect(calledUrl).toContain('c%2F..%2F1');
      expect(calledUrl).not.toContain('c/../1');
    });
  });

  describe('fetchConversationMessages', () => {
    it('sends the after_seq cursor and reads the meta block', async () => {
      const fetcher = jest.fn().mockResolvedValue(
        jsonResponse(200, {
          data: [{ id: 3, seq: 3, from: 'professional', body: 'Suite', deleted: false, created_at: '2026-10-20T11:00:00+00:00' }],
          meta: { has_more: false, next_before_seq: 3, last_seq: 3 },
        }),
      );

      const result = await fetchConversationMessages('c-1', 7, { afterSeq: 2 }, fetcher as unknown as typeof fetch);

      expect(String(fetcher.mock.calls[0][0])).toContain('after_seq=2');
      expect(result.status).toBe('ok');
      if (result.status === 'ok') {
        expect(result.data.last_seq).toBe(3);
        expect(result.data.messages[0].seq).toBe(3);
      }
    });

    it('sends the before_seq cursor when loading earlier messages', async () => {
      const fetcher = jest.fn().mockResolvedValue(
        jsonResponse(200, { data: [], meta: { has_more: false, next_before_seq: null, last_seq: 9 } }),
      );

      await fetchConversationMessages('c-1', 7, { beforeSeq: 5 }, fetcher as unknown as typeof fetch);

      expect(String(fetcher.mock.calls[0][0])).toContain('before_seq=5');
    });

    it('treats a malformed payload as an error, not as an empty page', async () => {
      const fetcher = jest.fn().mockResolvedValue(jsonResponse(200, { meta: { last_seq: 1 } }));

      const result = await fetchConversationMessages('c-1', 7, {}, fetcher as unknown as typeof fetch);

      expect(result.status).toBe('error');
    });
  });

  describe('startConversation', () => {
    it('posts the public target identifiers and returns the created thread', async () => {
      const fetcher = jest.fn().mockResolvedValue(
        jsonResponse(201, {
          data: {
            company_id: 'c-1',
            thread_id: 9,
            title: 'Conversation avec Restaurant Leopardo',
            target_slug: 'resto-leopardo',
            last_message_at: '2026-10-20T10:00:00+00:00',
            last_message_seq: 1,
            unread: false,
            status: 'open',
            messages: [],
          },
        }),
      );

      const result = await startConversation(
        { target_type: 'restaurant', target_slug: 'resto-leopardo', body: 'Bonjour' },
        fetcher as unknown as typeof fetch,
      );

      const body = JSON.parse(String(fetcher.mock.calls[0][1]?.body));
      expect(body.target_type).toBe('restaurant');
      expect(body.target_slug).toBe('resto-leopardo');
      // Le tenant n'est JAMAIS transmis : l'API le résout côté serveur.
      expect(body.company_id).toBeUndefined();
      expect(result.status).toBe('ok');
    });

    it('maps 404 to not_found when the profile is unpublished or suspended', async () => {
      const fetcher = jest.fn().mockResolvedValue(jsonResponse(404, { error: 'PROFILE_NOT_AVAILABLE' }));

      const result = await startConversation(
        { target_type: 'restaurant', target_slug: 'ferme', body: 'Bonjour' },
        fetcher as unknown as typeof fetch,
      );

      expect(result.status).toBe('not_found');
    });

    it('surfaces field errors on 422', async () => {
      const fetcher = jest.fn().mockResolvedValue(
        jsonResponse(422, { errors: { body: ['Le message ne peut pas être vide.'] } }),
      );

      const result = await startConversation(
        { target_type: 'restaurant', target_slug: 'resto', body: '' },
        fetcher as unknown as typeof fetch,
      );

      expect(result.status).toBe('invalid');
      if (result.status === 'invalid') {
        expect(result.fields?.body).toBeDefined();
      }
    });
  });

  describe('sendConversationMessage', () => {
    it('returns the persisted message on 201', async () => {
      const fetcher = jest.fn().mockResolvedValue(
        jsonResponse(201, {
          data: { id: 4, seq: 4, from: 'client', body: 'Je confirme.', deleted: false, created_at: '2026-10-20T12:00:00+00:00' },
        }),
      );

      const result = await sendConversationMessage('c-1', 7, 'Je confirme.', fetcher as unknown as typeof fetch);

      expect(result.status).toBe('ok');
      if (result.status === 'ok') {
        expect(result.data.seq).toBe(4);
        expect(result.data.from).toBe('client');
      }
    });

    it('does not report success when the server rejects the message', async () => {
      const fetcher = jest.fn().mockResolvedValue(jsonResponse(422, { errors: { body: ['trop long'] } }));

      const result = await sendConversationMessage('c-1', 7, 'x', fetcher as unknown as typeof fetch);

      expect(result.status).not.toBe('ok');
      expect(result.status).toBe('invalid');
    });

    it('keeps the message bound to the right thread in the URL', async () => {
      const fetcher = jest.fn().mockResolvedValue(jsonResponse(201, { data: { id: 1, seq: 1, from: 'client', body: 'a', deleted: false, created_at: '2026-10-20T12:00:00+00:00' } }));

      await sendConversationMessage('c-1', 42, 'a', fetcher as unknown as typeof fetch);

      expect(String(fetcher.mock.calls[0][0])).toBe('/api/v1/public/conversations/c-1/42/messages');
    });
  });

  it('exposes the same message bound as the backend', () => {
    expect(CONVERSATION_MESSAGE_MAX).toBe(2000);
  });
});
