/**
 * #8747 / #8749 [BOS-CHAT] — tests du client de messagerie.
 *
 * Ces tests portent sur la logique PURE (fusion par `seq`, clé d'idempotence) :
 * c'est elle qui garantit qu'une reconnexion ne produit ni doublon ni message
 * affiché dans le désordre. Le comportement réseau est vérifié côté API
 * (`ConversationMessagesTest`), pas ici.
 */

import { mergeMessages, newClientMessageId, type ChatMessage } from '@/lib/chat';

function message(overrides: Partial<ChatMessage> & Pick<ChatMessage, 'id' | 'seq'>): ChatMessage {
  return {
    conversation_thread_id: 1,
    author_id: 1,
    author: { id: 1, name: 'Test' },
    client_message_id: null,
    deleted: false,
    body: 'corps',
    edited_at: null,
    reply_to: null,
    attachment: null,
    created_at: '2026-10-19T10:00:00Z',
    ...overrides,
  };
}

describe('mergeMessages', () => {
  it('insère les messages rattrapés à leur place, dans l’ordre du seq', () => {
    const existing = [message({ id: 1, seq: 1 }), message({ id: 4, seq: 4 })];
    const incoming = [message({ id: 2, seq: 2 }), message({ id: 3, seq: 3 })];

    const merged = mergeMessages(existing, incoming);

    expect(merged.map((m) => m.seq)).toEqual([1, 2, 3, 4]);
  });

  it('ne duplique pas un message reçu deux fois (rejeu ou tick concurrent)', () => {
    const existing = [message({ id: 1, seq: 1 }), message({ id: 2, seq: 2 })];
    const incoming = [message({ id: 2, seq: 2 }), message({ id: 3, seq: 3 })];

    const merged = mergeMessages(existing, incoming);

    expect(merged).toHaveLength(3);
    expect(merged.map((m) => m.id)).toEqual([1, 2, 3]);
  });

  it('remplace un message mis à jour par sa version entrante', () => {
    const existing = [message({ id: 1, seq: 1, body: 'avant' })];
    const incoming = [message({ id: 1, seq: 1, body: 'après', edited_at: '2026-10-19T11:00:00Z' })];

    const merged = mergeMessages(existing, incoming);

    expect(merged).toHaveLength(1);
    expect(merged[0].body).toBe('après');
    expect(merged[0].edited_at).not.toBeNull();
  });

  it('tolère un seq nul (message historique avant backfill) sans casser l’ordre', () => {
    const existing = [message({ id: 5, seq: null }), message({ id: 1, seq: 1 })];

    const merged = mergeMessages(existing, []);

    // Les messages sans `seq` passent en fin : ils ne peuvent pas être placés
    // de façon fiable, et les reléguer est moins trompeur que de les mélanger.
    expect(merged.map((m) => m.id)).toEqual([1, 5]);
  });
});

describe('newClientMessageId', () => {
  it('produit des clés distinctes', () => {
    const keys = new Set(Array.from({ length: 50 }, () => newClientMessageId()));

    expect(keys.size).toBe(50);
  });

  it('reste compatible avec la contrainte serveur (64 caractères, jeu restreint)', () => {
    const key = newClientMessageId();

    expect(key.length).toBeLessThanOrEqual(64);
    // Même expression que la règle de validation Laravel côté API.
    expect(key).toMatch(/^[A-Za-z0-9_.:-]+$/);
  });
});
