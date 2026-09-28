/**
 * BOS-035 (#8224) — persistance locale du panneau Assistant Leo IA.
 *
 * L'API tenant ne fournit AUCUN endpoint de relecture des messages d'une
 * conversation (seul l'historique des titres existe) : le fil de la
 * conversation active est donc conservé en `localStorage` (continuité « même
 * appareil » uniquement — jamais de donnée inventée côté serveur).
 *
 * Pattern du repo : `useState` + `localStorage` (pas de lib d'état).
 */

import type { AssistantPendingConfirmation } from './api';

const CURRENT_CONVERSATION_KEY = 'leopardo_assistant_current';
const MESSAGES_KEY_PREFIX = 'leopardo_assistant_msgs_';

/** État local d'une carte de confirmation (one-shot par action). */
export type AssistantCardState = 'pending' | 'executed' | 'rejected' | 'expired' | 'failed';

export type AssistantPendingItem = AssistantPendingConfirmation & {
  state: AssistantCardState;
  /** Résultat renvoyé par l'exécution (`POST …/confirm`). */
  result?: unknown;
  /** Message d'échec d'exécution (422 `{error, data}`). */
  errorMessage?: string;
};

export type AssistantMessage = {
  role: 'user' | 'assistant';
  content: string;
  tools_used?: string[];
  timestamp: number;
  pending?: AssistantPendingItem[];
};

function storage(): Storage | null {
  if (typeof window === 'undefined') {
    return null;
  }
  try {
    return window.localStorage;
  } catch {
    return null;
  }
}

/** Identifiant de la conversation courante (survit à un rechargement). */
export function readCurrentConversationId(): number | null {
  const raw = storage()?.getItem(CURRENT_CONVERSATION_KEY);
  if (!raw) {
    return null;
  }
  const parsed = Number.parseInt(raw, 10);
  return Number.isFinite(parsed) && parsed > 0 ? parsed : null;
}

export function writeCurrentConversationId(conversationId: number | null): void {
  const store = storage();
  if (!store) {
    return;
  }
  try {
    if (conversationId === null) {
      store.removeItem(CURRENT_CONVERSATION_KEY);
    } else {
      store.setItem(CURRENT_CONVERSATION_KEY, String(conversationId));
    }
  } catch {
    // localStorage indisponible (quota/mode privé) — session volatile.
  }
}

/** Messages cachés d'une conversation (tableau vide si absents/illisibles). */
export function readConversationMessages(conversationId: number): AssistantMessage[] {
  const raw = storage()?.getItem(`${MESSAGES_KEY_PREFIX}${conversationId}`);
  if (!raw) {
    return [];
  }
  try {
    const parsed = JSON.parse(raw) as unknown;
    if (!Array.isArray(parsed)) {
      return [];
    }
    return parsed.filter(
      (entry): entry is AssistantMessage =>
        !!entry &&
        typeof entry === 'object' &&
        ((entry as AssistantMessage).role === 'user' || (entry as AssistantMessage).role === 'assistant') &&
        typeof (entry as AssistantMessage).content === 'string',
    );
  } catch {
    return [];
  }
}

export function writeConversationMessages(conversationId: number, messages: AssistantMessage[]): void {
  const store = storage();
  if (!store) {
    return;
  }
  try {
    store.setItem(`${MESSAGES_KEY_PREFIX}${conversationId}`, JSON.stringify(messages));
  } catch {
    // Quota dépassé : la conversation reste visible tant que l'onglet vit.
  }
}

export function clearConversationMessages(conversationId: number): void {
  try {
    storage()?.removeItem(`${MESSAGES_KEY_PREFIX}${conversationId}`);
  } catch {
    // Sans effet si le stockage est indisponible.
  }
}
