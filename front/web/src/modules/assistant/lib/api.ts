/**
 * BOS-035 (#8224) — client API du panneau Assistant Leo IA (portail tenant).
 *
 * Miroir strict du contrat backend `/api/v1/ai/*` (vérifié dans le code
 * Laravel) : le front n'a AUCUNE logique de permission — il affiche
 * uniquement ce que l'API retourne (tools filtrés par rôle côté serveur,
 * confirmations d'actions one-shot avec TTL de 15 minutes).
 */

import { apiFetch } from '@/lib/api-client';

/** Longueur max d'un message (validation serveur : `max:2000`). */
export const ASSISTANT_MESSAGE_MAX_LENGTH = 2000;

/** Action d'écriture proposée par l'assistant, en attente de confirmation. */
export type AssistantPendingConfirmation = {
  status: 'confirmation_required';
  /** UUID de l'action en attente (one-shot, TTL 15 min). */
  pending_action_id: string;
  tool: string;
  summary: string;
  arguments: Record<string, unknown>;
};

export type AssistantChatResponse = {
  conversation_id: number;
  response: string;
  tools_used: string[];
  pending_confirmations: AssistantPendingConfirmation[];
  tokens: { input: number; output: number };
};

export type AssistantConversationSummary = {
  id: number;
  title: string | null;
  token_count: number;
  created_at: string | null;
  updated_at: string | null;
};

export type AssistantConfirmResult = {
  status: 'executed';
  tool: string;
  result: unknown;
};

export type AssistantRejectResult = {
  status: 'rejected';
  tool: string;
};

export async function sendAssistantMessage(
  message: string,
  conversationId: number | null,
): Promise<AssistantChatResponse> {
  const response = await apiFetch('/ai/chat', {
    method: 'POST',
    body: JSON.stringify({ message, conversation_id: conversationId }),
  });
  const payload = (await response.json()) as { data: AssistantChatResponse };
  return payload.data;
}

/**
 * Historique des conversations de l'utilisateur (titres + compteurs).
 * ⚠️ L'API tenant n'expose PAS les messages d'une conversation passée :
 * la continuité du fil est assurée par le cache local (`./storage`).
 */
export async function getAssistantHistory(perPage = 20): Promise<AssistantConversationSummary[]> {
  const response = await apiFetch(`/ai/chat/history?per_page=${perPage}`);
  const payload = (await response.json()) as { data?: AssistantConversationSummary[] };
  return payload.data ?? [];
}

export async function deleteAssistantConversation(conversationId: number): Promise<void> {
  // 404 si la conversation est inconnue (autre utilisateur/société) — ApiError.
  await apiFetch(`/ai/chat/${conversationId}`, { method: 'DELETE' });
}

export async function confirmAssistantAction(pendingActionId: string): Promise<AssistantConfirmResult> {
  const response = await apiFetch(`/ai/actions/${pendingActionId}/confirm`, { method: 'POST' });
  const payload = (await response.json()) as { data: AssistantConfirmResult };
  return payload.data;
}

export async function rejectAssistantAction(pendingActionId: string): Promise<AssistantRejectResult> {
  const response = await apiFetch(`/ai/actions/${pendingActionId}/reject`, { method: 'POST' });
  const payload = (await response.json()) as { data: AssistantRejectResult };
  return payload.data;
}
