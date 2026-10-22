/**
 * #8760 [BOS-CHAT] — Client du contrat de messagerie publique.
 *
 * Deux surfaces distinctes, deux contrats :
 *
 *   - **Client** (`/api/v1/public/conversations`, guard `user_api`) : un
 *     utilisateur connecté retrouve ses conversations et répond. Le tenant
 *     n'est JAMAIS transmis pour démarrer un fil — l'API le résout depuis
 *     (`target_type`, `target_slug`), exactement comme le contact public.
 *   - **Professionnel** (`/api/v1/conversations/public`, Sanctum + tenant) :
 *     la boîte de réception du Business OS. Hors périmètre de ce module.
 *
 * ⚠️ Pas de faux temps réel : le transport est le polling par curseur `seq`
 * (même contrat que le chat interne, #8747). Aucun WebSocket n'est ouvert —
 * l'hébergement actuel ne le garantit pas, et un faux « temps réel » serait
 * pire qu'un rafraîchissement explicite.
 *
 * ⚠️ Aucun état de succès n'est déduit d'une réponse locale : un message n'est
 * affiché comme envoyé qu'après un `201` du serveur.
 */

/** Bornes miroir du backend (`PostPublicConversationMessageRequest`). */
export const CONVERSATION_MESSAGE_MAX = 2000;

export interface PublicConversationSummary {
  company_id: string;
  thread_id: number;
  title: string;
  target_slug: string | null;
  last_message_at: string | null;
  last_message_seq: number;
  unread: boolean;
}

export interface PublicConversationMessage {
  id: number;
  seq: number;
  from: 'client' | 'professional';
  body: string;
  deleted: boolean;
  created_at: string;
}

export interface PublicConversationDetail extends PublicConversationSummary {
  status: string;
  messages: PublicConversationMessage[];
}

export interface PublicConversationPage {
  messages: PublicConversationMessage[];
  has_more: boolean;
  next_before_seq: number | null;
  last_seq: number;
}

export type PublicConversationResult<T> =
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
 * `401` → `unauthenticated` (le client doit se connecter, pas une erreur) ;
 * `404` → `not_found` (fil d'un autre client, autre tenant, ou inexistant —
 * indistinguables par conception) ; `422` → `invalid` ; le reste → `error`.
 */
async function toResult<T>(response: Response): Promise<PublicConversationResult<T>> {
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

/** GET /api/v1/public/conversations — « mes conversations ». */
export async function fetchMyConversations(
  fetcher: typeof fetch = fetch,
): Promise<PublicConversationResult<PublicConversationSummary[]>> {
  try {
    const response = await fetcher('/api/v1/public/conversations', {
      headers: { Accept: 'application/json' },
      credentials: 'include',
    });

    return await toResult<PublicConversationSummary[]>(response);
  } catch {
    // Réseau coupé (connexions limitées) : état d'erreur explicite + retry.
    return { status: 'error' };
  }
}

/** GET /api/v1/public/conversations/{companyId}/{thread} — un fil + son historique. */
export async function fetchConversation(
  companyId: string,
  threadId: number,
  fetcher: typeof fetch = fetch,
): Promise<PublicConversationResult<PublicConversationDetail>> {
  try {
    const response = await fetcher(
      `/api/v1/public/conversations/${encodeURIComponent(companyId)}/${threadId}`,
      { headers: { Accept: 'application/json' }, credentials: 'include' },
    );

    return await toResult<PublicConversationDetail>(response);
  } catch {
    return { status: 'error' };
  }
}

/**
 * GET .../messages — pagination par curseur.
 *
 * `afterSeq` rattrape ce qui est arrivé pendant une déconnexion ;
 * `beforeSeq` remonte l'historique. Les deux sont exclusifs côté usage.
 */
export async function fetchConversationMessages(
  companyId: string,
  threadId: number,
  options: { afterSeq?: number; beforeSeq?: number; perPage?: number } = {},
  fetcher: typeof fetch = fetch,
): Promise<PublicConversationResult<PublicConversationPage>> {
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
      `/api/v1/public/conversations/${encodeURIComponent(companyId)}/${threadId}/messages${query ? `?${query}` : ''}`,
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

    const body = (await response.json().catch(() => ({}))) as Envelope<PublicConversationMessage[]>;

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

/**
 * POST /api/v1/public/conversations — démarrer (ou reprendre) une conversation.
 *
 * Idempotent côté serveur par (client, tenant, profil) : un client qui revient
 * sur la même fiche retrouve son fil au lieu d'en créer un second.
 */
export async function startConversation(
  payload: { target_type: string; target_slug: string; body: string },
  fetcher: typeof fetch = fetch,
): Promise<PublicConversationResult<PublicConversationDetail>> {
  try {
    const response = await fetcher('/api/v1/public/conversations', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      credentials: 'include',
      body: JSON.stringify(payload),
    });

    return await toResult<PublicConversationDetail>(response);
  } catch {
    return { status: 'error' };
  }
}

/** POST .../messages — répondre dans un fil existant. */
export async function sendConversationMessage(
  companyId: string,
  threadId: number,
  body: string,
  fetcher: typeof fetch = fetch,
): Promise<PublicConversationResult<PublicConversationMessage>> {
  try {
    const response = await fetcher(
      `/api/v1/public/conversations/${encodeURIComponent(companyId)}/${threadId}/messages`,
      {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ body }),
      },
    );

    return await toResult<PublicConversationMessage>(response);
  } catch {
    return { status: 'error' };
  }
}
