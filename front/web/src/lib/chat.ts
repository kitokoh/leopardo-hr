/**
 * #8747 / #8749 / #8750 [BOS-CHAT] — client de la messagerie horizontale.
 *
 * Miroir STRICT des contrats servis par `ConversationController` et
 * `ConversationModerationController` (module `Notification`, BC-13 COMMS).
 * Aucune règle d'autorisation ici : le serveur décide (404 pour un
 * non-participant, 422 pour un payload invalide, 429 pour un débit excessif).
 * Ce fichier ne fait que typer, envoyer et paginer.
 *
 * ⚠️ Le module « Boîte connectée » (BC-29 COMMUNICATION, Gmail/IA) est un
 * système DIFFÉRENT, dans `lib/communication.ts`. Ne pas mélanger les deux :
 * `CommunicationThread` n'est pas un `ChatThread`.
 */

import { apiFetch } from '@/lib/api-client';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

export type ChatThreadKind = 'direct' | 'group';

export type ChatParticipantRole = 'creator' | 'admin' | 'member';

export interface ChatAttachment {
  original_name: string | null;
  mime_type: string | null;
  size: number | null;
  /** Route AUTHENTIFIÉE : le serveur rejoue le contrôle d'accès à chaque GET. */
  url: string;
}

export interface ChatMessageAuthor {
  id: number;
  name: string;
}

export interface ChatReplyPreview {
  id: number;
  author_id: number;
  body: string;
  deleted: boolean;
}

export interface ChatMessage {
  id: number;
  conversation_thread_id: number;
  author_id: number;
  author: ChatMessageAuthor | null;
  /** #8747 — curseur d'ORDRE. Le tri client se fait sur ce champ, jamais sur `created_at`. */
  seq: number | null;
  /** #8747 — clé d'idempotence, renvoyée telle quelle pour réconcilier un envoi local. */
  client_message_id: string | null;
  deleted: boolean;
  body: string;
  edited_at: string | null;
  reply_to: ChatReplyPreview | null;
  attachment: ChatAttachment | null;
  created_at: string;
}

export interface ChatThread {
  id: number;
  kind: ChatThreadKind;
  title: string;
  status: string;
  last_message_at: string | null;
  /** #8747 — dernier `seq` alloué : sert de curseur de polling/reprise. */
  last_message_seq: number;
  unread: boolean;
  [key: string]: unknown;
}

export interface ChatMessagePage {
  data: ChatMessage[];
  meta: {
    has_more: boolean;
    next_before_seq: number | null;
    last_seq: number;
    per_page: number;
    catch_up: boolean;
  };
}

export interface ChatReport {
  id: number;
  status: 'open' | 'reviewed' | 'dismissed';
  reason: string;
  details: string | null;
  conversation_thread_id: number | null;
  conversation_message_id: number | null;
  reported_employee_id: number | null;
  created_at: string | null;
  reviewed_at: string | null;
  resolution: string | null;
  reporter?: { id: number; name: string } | null;
  reviewed_by?: { id: number; name: string } | null;
}

export type ChatReportReason =
  | 'harassment'
  | 'spam'
  | 'inappropriate_content'
  | 'threat'
  | 'impersonation'
  | 'other';

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

const BASE = '/conversations';

/**
 * Clé d'idempotence côté client.
 *
 * `crypto.randomUUID()` n'existe pas dans les navigateurs anciens ni dans les
 * contextes non sécurisés ; le repli reste unique EN PRATIQUE (temps + aléa)
 * et, surtout, il est stable pour un même envoi : c'est tout ce dont le
 * serveur a besoin pour ne pas créer deux messages.
 */
export function newClientMessageId(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID();
  }

  return `c_${Date.now().toString(36)}_${Math.random().toString(36).slice(2, 10)}`;
}

/**
 * Fusionne deux listes de messages par `id`, en conservant l'ordre `seq`.
 *
 * Utilisé par le polling (#8749) : les messages reçus pendant une déconnexion
 * viennent s'INSÉRER dans l'historique, sans doublon et sans casser l'ordre.
 */
export function mergeMessages(existing: ChatMessage[], incoming: ChatMessage[]): ChatMessage[] {
  const byId = new Map<number, ChatMessage>();

  for (const message of existing) byId.set(message.id, message);
  for (const message of incoming) byId.set(message.id, message);

  return Array.from(byId.values()).sort((a, b) => {
    const seqA = a.seq ?? Number.MAX_SAFE_INTEGER;
    const seqB = b.seq ?? Number.MAX_SAFE_INTEGER;

    return seqA === seqB ? a.id - b.id : seqA - seqB;
  });
}

// ─────────────────────────────────────────────────────────────────────────────
// Fils
// ─────────────────────────────────────────────────────────────────────────────

export async function fetchThreads(): Promise<ChatThread[]> {
  const response = await apiFetch(`${BASE}?_cacheBust=true`);
  const payload = (await response.json()) as { data?: ChatThread[] } | ChatThread[];

  // Le contrôleur renvoie `{ data: [...] }`; on tolère la forme nue pour ne pas
  // casser si le contrat évolue vers une collection Laravel standard.
  return Array.isArray(payload) ? payload : (payload.data ?? []);
}

export interface CreateThreadInput {
  title: string;
  body: string;
  recipient_id?: number;
  subject_type?: string;
  subject_id?: number;
  context_key?: string;
  attachment?: File | null;
}

export async function createThread(input: CreateThreadInput): Promise<ChatThread> {
  const body = new FormData();
  body.set('title', input.title);
  body.set('body', input.body);

  if (input.recipient_id !== undefined) body.set('recipient_id', String(input.recipient_id));
  if (input.subject_type !== undefined) body.set('subject_type', input.subject_type);
  if (input.subject_id !== undefined) body.set('subject_id', String(input.subject_id));
  if (input.context_key !== undefined) body.set('context_key', input.context_key);
  if (input.attachment) body.set('attachment', input.attachment);

  const response = await apiFetch(BASE, { method: 'POST', body });
  const payload = (await response.json()) as { data: ChatThread };

  return payload.data;
}

// ─────────────────────────────────────────────────────────────────────────────
// Messages
// ─────────────────────────────────────────────────────────────────────────────

export interface HistoryQuery {
  before_seq?: number;
  after_seq?: number;
  per_page?: number;
}

export async function fetchMessages(threadId: number, query: HistoryQuery = {}): Promise<ChatMessagePage> {
  const params = new URLSearchParams();

  if (query.before_seq !== undefined) params.set('before_seq', String(query.before_seq));
  if (query.after_seq !== undefined) params.set('after_seq', String(query.after_seq));
  if (query.per_page !== undefined) params.set('per_page', String(query.per_page));

  const suffix = params.toString();
  // `_cacheBust` : un GET d'historique doit refléter l'état réel, pas une
  // réponse mémorisée par le cache ETag de `apiFetch`.
  const url = `${BASE}/${threadId}/messages${suffix ? `?${suffix}` : '?_cacheBust=true'}`;

  const response = await apiFetch(url, { _cacheBust: true } as RequestInit);
  return (await response.json()) as ChatMessagePage;
}

export interface SendMessageInput {
  body: string;
  attachment?: File | null;
  client_message_id?: string;
  reply_to_message_id?: number | null;
}

export interface SendMessageResult {
  message: ChatMessage;
  /** `false` quand le serveur a reconnu un rejeu (200 au lieu de 201). */
  created: boolean;
}

export async function sendMessage(threadId: number, input: SendMessageInput): Promise<SendMessageResult> {
  const form = new FormData();

  if (input.body !== '') form.set('body', input.body);
  if (input.attachment) form.set('attachment', input.attachment);
  if (input.reply_to_message_id != null) {
    form.set('reply_to_message_id', String(input.reply_to_message_id));
  }
  // Clé fournie par l'appelant pour pouvoir réutiliser EXACTEMENT la même
  // valeur si la requête échoue puis est renvoyée (retry réseau).
  if (input.client_message_id) form.set('client_message_id', input.client_message_id);

  const response = await apiFetch(`${BASE}/${threadId}/messages`, { method: 'POST', body: form });
  const payload = (await response.json()) as { data: ChatMessage };

  return { message: payload.data, created: response.status === 201 };
}

export async function editMessage(threadId: number, messageId: number, body: string): Promise<ChatMessage> {
  const response = await apiFetch(`${BASE}/${threadId}/messages/${messageId}`, {
    method: 'PATCH',
    body: JSON.stringify({ body }),
  });
  const payload = (await response.json()) as { data: ChatMessage };

  return payload.data;
}

export async function deleteMessage(threadId: number, messageId: number): Promise<void> {
  await apiFetch(`${BASE}/${threadId}/messages/${messageId}`, { method: 'DELETE', _idempotent: true } as RequestInit);
}

/**
 * URL de téléchargement d'une pièce jointe.
 *
 * Le fichier n'est PAS servi en public : ce chemin repasse par le proxy
 * authentifié et le serveur revérifie l'appartenance au fil à chaque appel.
 */
export function attachmentUrl(threadId: number, messageId: number): string {
  return `/api/v1${BASE}/${threadId}/messages/${messageId}/attachment`;
}

// ─────────────────────────────────────────────────────────────────────────────
// Modération (#8750)
// ─────────────────────────────────────────────────────────────────────────────

export async function blockEmployee(employeeId: number, reason?: string): Promise<void> {
  await apiFetch(`${BASE}/blocks`, {
    method: 'POST',
    body: JSON.stringify(reason ? { employee_id: employeeId, reason } : { employee_id: employeeId }),
  });
}

export async function unblockEmployee(employeeId: number): Promise<void> {
  await apiFetch(`${BASE}/blocks/${employeeId}`, { method: 'DELETE', _idempotent: true } as RequestInit);
}

export async function reportMessage(
  threadId: number,
  messageId: number,
  reason: ChatReportReason,
  details?: string,
): Promise<ChatReport> {
  const response = await apiFetch(`${BASE}/${threadId}/reports`, {
    method: 'POST',
    body: JSON.stringify({ message_id: messageId, reason, details }),
  });
  const payload = (await response.json()) as { data: ChatReport };

  return payload.data;
}
