/**
 * #8760 [BOS-CHAT] — Client de la BOÎTE DE RÉCEPTION publique (côté professionnel).
 *
 * Surface distincte de `public-conversation-api.ts` (qui parle au client grand
 * public avec le guard `user_api`) :
 *
 *   - **Professionnel** ici : `/api/v1/conversations/public`, `auth:sanctum` +
 *     middleware `tenant`. Le manager traite les demandes depuis SON Business OS,
 *     avec son identité et ses permissions.
 *
 * ⚠️ Aucune décision d'autorisation n'est prise ici. Le serveur renvoie **404**
 * pour un fil d'un autre tenant, pour un fil inexistant, ET pour un simple
 * employé du bon tenant : les trois cas sont indistinguables par conception
 * (un 403 confirmerait l'existence du fil). L'UI se contente de traduire ce
 * `404` en « conversation indisponible », sans supposer lequel des trois c'est.
 *
 * ⚠️ Pas de faux temps réel : le transport est le polling par curseur `seq`
 * (même contrat que le chat interne, #8747). Aucun WebSocket n'est ouvert.
 */

import { CONVERSATION_MESSAGE_MAX } from '@/lib/public-conversation-api';

/** Bornes miroir du backend (`PostPublicConversationMessageRequest`). */
export { CONVERSATION_MESSAGE_MAX };

export interface InboxThreadSummary {
  id: number;
  title: string;
  target_slug: string | null;
  status: string;
  last_message_at: string | null;
  last_message_seq: number;
  unread: boolean;
}

export interface InboxMessage {
  id: number;
  seq: number;
  from: 'client' | 'professional';
  body: string;
  deleted?: boolean;
  created_at: string;
}

export interface InboxThreadDetail {
  id: number;
  title: string;
  target_slug: string | null;
  status: string;
  last_message_seq: number;
  messages: InboxMessage[];
}

export interface InboxMessagePage {
  messages: InboxMessage[];
  has_more: boolean;
  next_before_seq: number | null;
  last_seq: number;
}

export type InboxResult<T> =
  | { status: 'ok'; data: T }
  | { status: 'unauthenticated' }
  | { status: 'not_found' }
  | { status: 'invalid'; fields?: Record<string, string[]> }
  | { status: 'error' };

interface Envelope<T> {
  data?: T;
  meta?: { has_more?: boolean; next_before_seq?: number | null; last_seq?: number };
  error?: string;
  errors?: Record<string, string[]>;
}

/**
 * Traduit une réponse HTTP en résultat explicite.
 *
 * `401` → `unauthenticated` : la session a expiré, ce n'est pas une panne.
 * `404` → `not_found` : fil d'un autre tenant, d'un autre manager, ou supprimé.
 * `429` → `error` : quota de messages atteint, le message n'est pas parti.
 */
async function toResult<T>(response: Response): Promise<InboxResult<T>> {
  if (response.status === 401) {
    return { status: 'unauthenticated' };
  }

  if (response.status === 404) {
    return { status: 'not_found' };
  }

  if (response.status === 422) {
    const body = (await response.json().catch(() => ({}))) as Envelope<T>;
    return { status: 'invalid', fields: body.errors };
  }

  if (!response.ok) {
    return { status: 'error' };
  }

  const body = (await response.json().catch(() => ({}))) as Envelope<T>;

  if (body.data === undefined) {
    return { status: 'error' };
  }

  return { status: 'ok', data: body.data };
}

/** GET /api/v1/conversations/public — demandes publiques du tenant courant. */
export async function fetchInboxThreads(
  options: { perPage?: number } = {},
  fetcher: typeof fetch = fetch,
): Promise<InboxResult<InboxThreadSummary[]>> {
  const query = options.perPage !== undefined ? `?per_page=${options.perPage}` : '';

  try {
    const response = await fetcher(`/api/v1/conversations/public${query}`, {
      headers: { Accept: 'application/json' },
      credentials: 'include',
    });

    return await toResult<InboxThreadSummary[]>(response);
  } catch {
    // Réseau coupé : état d'erreur explicite, jamais un écran vide trompeur.
    return { status: 'error' };
  }
}

/** GET /api/v1/conversations/public/{thread} — un fil + son historique. */
export async function fetchInboxThread(
  threadId: number,
  fetcher: typeof fetch = fetch,
): Promise<InboxResult<InboxThreadDetail>> {
  try {
    const response = await fetcher(`/api/v1/conversations/public/${threadId}`, {
      headers: { Accept: 'application/json' },
      credentials: 'include',
    });

    return await toResult<InboxThreadDetail>(response);
  } catch {
    return { status: 'error' };
  }
}

/** GET …/messages — pagination par curseur (`after_seq` / `before_seq`). */
export async function fetchInboxMessages(
  threadId: number,
  options: { afterSeq?: number; beforeSeq?: number; perPage?: number } = {},
  fetcher: typeof fetch = fetch,
): Promise<InboxResult<InboxMessagePage>> {
  const params = new URLSearchParams();

  if (options.afterSeq !== undefined) {
    params.set('after_seq', String(options.afterSeq));
  }

  if (options.beforeSeq !== undefined) {
    params.set('before_seq', String(options.beforeSeq));
  }

  if (options.perPage !== undefined) {
    params.set('per_page', String(options.perPage));
  }

  const query = params.toString();

  try {
    const response = await fetcher(
      `/api/v1/conversations/public/${threadId}/messages${query ? `?${query}` : ''}`,
      { headers: { Accept: 'application/json' }, credentials: 'include' },
    );

    if (response.status === 401) {
      return { status: 'unauthenticated' };
    }

    if (response.status === 404) {
      return { status: 'not_found' };
    }

    if (!response.ok) {
      return { status: 'error' };
    }

    const body = (await response.json().catch(() => ({}))) as Envelope<InboxMessage[]>;

    if (!Array.isArray(body.data)) {
      return { status: 'error' };
    }

    return {
      status: 'ok',
      data: {
        messages: body.data,
        has_more: body.meta?.has_more ?? false,
        next_before_seq: body.meta?.next_before_seq ?? null,
        last_seq: body.meta?.last_seq ?? 0,
      },
    };
  } catch {
    return { status: 'error' };
  }
}

/** POST …/messages — répondre au client. `201` requis pour afficher « envoyé ». */
export async function sendInboxMessage(
  threadId: number,
  body: string,
  fetcher: typeof fetch = fetch,
): Promise<InboxResult<InboxMessage>> {
  try {
    const response = await fetcher(`/api/v1/conversations/public/${threadId}/messages`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      credentials: 'include',
      body: JSON.stringify({ body }),
    });

    return await toResult<InboxMessage>(response);
  } catch {
    return { status: 'error' };
  }
}
