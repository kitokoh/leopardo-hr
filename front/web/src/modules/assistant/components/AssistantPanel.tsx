'use client';

import { useCallback, useEffect, useState, useSyncExternalStore } from 'react';
import { AlertTriangle, History, Plus, Sparkles } from 'lucide-react';

import { ApiError } from '@/lib/api-client';
import { usePanelDismiss } from '@/components/layout/panel-dismiss';
import { getPreferredLocale, type AppLocale } from '@/lib/i18n';
import {
  confirmAssistantAction,
  deleteAssistantConversation,
  getAssistantHistory,
  rejectAssistantAction,
  sendAssistantMessage,
  type AssistantConversationSummary,
} from '../lib/api';
import { ta } from '../lib/i18n';
import {
  clearConversationMessages,
  readConversationMessages,
  readCurrentConversationId,
  writeConversationMessages,
  writeCurrentConversationId,
  type AssistantMessage,
  type AssistantPendingItem,
} from '../lib/storage';
import { ChatComposer } from './ChatComposer';
import { ChatThread } from './ChatThread';
import { ConversationHistory } from './ConversationHistory';

const emptySubscribe = () => () => {};

/** Bannières d'erreur du panneau (codes d'erreur canoniques du contrat AI). */
type PanelError = 'disabled' | 'credits' | 'budget' | 'rate_limited' | 'generic' | null;

function mapSendError(error: unknown): PanelError {
  if (error instanceof ApiError) {
    if (error.status === 403 && error.code === 'AI_FEATURE_DISABLED') {
      return 'disabled';
    }
    if (error.status === 422 && error.code === 'AI_CREDITS_EXHAUSTED') {
      return 'credits';
    }
    if (error.status === 422 && error.code === 'AI_TOKEN_BUDGET_EXCEEDED') {
      return 'budget';
    }
    if (error.status === 429) {
      return 'rate_limited';
    }
  }
  return 'generic';
}

/**
 * BOS-035 (#8224) — panneau principal de l'assistant Leo IA du portail
 * tenant : chat, badges des tools utilisés, cartes de confirmation des
 * actions en attente et historique des conversations.
 *
 * Sécurité multi-tenant : AUCUNE permission n'est calculée côté front —
 * le panneau affiche uniquement ce que l'API retourne (403 → état désactivé,
 * tools filtrés par rôle côté serveur, jamais de carte hors
 * `pending_confirmations`).
 */
export function AssistantPanel() {
  const locale = useSyncExternalStore<AppLocale>(emptySubscribe, getPreferredLocale, () => 'fr');

  const [conversations, setConversations] = useState<AssistantConversationSummary[]>([]);
  const [historyLoading, setHistoryLoading] = useState(true);
  const [historyError, setHistoryError] = useState(false);

  const [conversationId, setConversationId] = useState<number | null>(null);
  const [messages, setMessages] = useState<AssistantMessage[]>([]);
  const [input, setInput] = useState('');
  const [sending, setSending] = useState(false);
  const [panelError, setPanelError] = useState<PanelError>(null);
  const [historyOpen, setHistoryOpen] = useState(false);

  usePanelDismiss(historyOpen, () => setHistoryOpen(false));

  const refreshHistory = useCallback(async () => {
    setHistoryError(false);
    try {
      const items = await getAssistantHistory();
      setConversations(items);
    } catch {
      setHistoryError(true);
    } finally {
      setHistoryLoading(false);
    }
  }, []);

  // Montage : historique + restauration de la conversation courante
  // (continuité « même appareil » via localStorage, cf. lib/storage.ts).
  useEffect(() => {
    void refreshHistory();
    const restoredId = readCurrentConversationId();
    if (restoredId !== null) {
      setConversationId(restoredId);
      setMessages(readConversationMessages(restoredId));
    }
  }, [refreshHistory]);

  const persistMessages = useCallback((targetId: number, next: AssistantMessage[]) => {
    writeConversationMessages(targetId, next);
  }, []);

  const startNewConversation = useCallback(() => {
    setConversationId(null);
    setMessages([]);
    setPanelError(null);
    setInput('');
    writeCurrentConversationId(null);
  }, []);

  const selectConversation = useCallback((targetId: number) => {
    setConversationId(targetId);
    writeCurrentConversationId(targetId);
    setMessages(readConversationMessages(targetId));
    setPanelError(null);
    setHistoryOpen(false);
  }, []);

  const handleDeleteConversation = useCallback(
    async (targetId: number) => {
      try {
        await deleteAssistantConversation(targetId);
      } catch (error) {
        // 404 = conversation déjà absente côté serveur (autre appareil) :
        // le retrait local reste cohérent, toute autre erreur remonte.
        if (!(error instanceof ApiError && error.status === 404)) {
          throw error;
        }
      }
      clearConversationMessages(targetId);
      setConversations((current) => current.filter((item) => item.id !== targetId));
      if (conversationId === targetId) {
        startNewConversation();
      }
    },
    [conversationId, startNewConversation],
  );

  const updatePendingItem = useCallback(
    (messageIndex: number, pendingActionId: string, patch: Partial<AssistantPendingItem>) => {
      setMessages((current) => {
        const next = current.map((message, index) => {
          if (index !== messageIndex || !message.pending) {
            return message;
          }
          return {
            ...message,
            pending: message.pending.map((item) =>
              item.pending_action_id === pendingActionId ? { ...item, ...patch } : item,
            ),
          };
        });
        if (conversationId !== null) {
          persistMessages(conversationId, next);
        }
        return next;
      });
    },
    [conversationId, persistMessages],
  );

  const handleConfirm = useCallback(
    async (messageIndex: number, item: AssistantPendingItem) => {
      try {
        const result = await confirmAssistantAction(item.pending_action_id);
        updatePendingItem(messageIndex, item.pending_action_id, {
          state: 'executed',
          result: result.result,
        });
      } catch (error) {
        if (error instanceof ApiError && error.status === 404) {
          // PENDING_ACTION_NOT_FOUND — TTL 15 min écoulé, autre user/company
          // ou action déjà consommée : la carte devient non actionable.
          updatePendingItem(messageIndex, item.pending_action_id, { state: 'expired' });
          return;
        }
        updatePendingItem(messageIndex, item.pending_action_id, {
          state: 'failed',
          errorMessage: error instanceof ApiError ? error.message : undefined,
        });
      }
    },
    [updatePendingItem],
  );

  const handleReject = useCallback(
    async (messageIndex: number, item: AssistantPendingItem) => {
      try {
        await rejectAssistantAction(item.pending_action_id);
        updatePendingItem(messageIndex, item.pending_action_id, { state: 'rejected' });
      } catch (error) {
        if (error instanceof ApiError && error.status === 404) {
          updatePendingItem(messageIndex, item.pending_action_id, { state: 'expired' });
          return;
        }
        updatePendingItem(messageIndex, item.pending_action_id, {
          state: 'failed',
          errorMessage: error instanceof ApiError ? error.message : undefined,
        });
      }
    },
    [updatePendingItem],
  );

  const handleSend = useCallback(async () => {
    const text = input.trim();
    if (!text || sending) {
      return;
    }
    setSending(true);
    setPanelError(null);
    const previous = messages;
    const userMessage: AssistantMessage = { role: 'user', content: text, timestamp: Date.now() };
    const optimistic = [...previous, userMessage];
    setMessages(optimistic);
    try {
      const reply = await sendAssistantMessage(text, conversationId);
      const assistantMessage: AssistantMessage = {
        role: 'assistant',
        content: reply.response,
        tools_used: reply.tools_used ?? [],
        timestamp: Date.now(),
        pending: (reply.pending_confirmations ?? []).map((confirmation) => ({
          ...confirmation,
          state: 'pending' as const,
        })),
      };
      const next = [...optimistic, assistantMessage];
      setMessages(next);
      setInput('');
      if (reply.conversation_id !== conversationId) {
        setConversationId(reply.conversation_id);
        writeCurrentConversationId(reply.conversation_id);
      }
      persistMessages(reply.conversation_id, next);
      // Le titre/compteur de tokens de la conversation a changé côté serveur.
      void refreshHistory();
    } catch (error) {
      // Le message n'a jamais été traité : on restaure le fil et le texte.
      setMessages(previous);
      setPanelError(mapSendError(error));
    } finally {
      setSending(false);
    }
  }, [conversationId, input, messages, persistMessages, refreshHistory, sending]);

  const historyList = (
    <ConversationHistory
      conversations={conversations}
      loading={historyLoading}
      loadError={historyError}
      currentId={conversationId}
      locale={locale}
      onSelect={selectConversation}
      onDelete={handleDeleteConversation}
    />
  );

  return (
    <div data-testid="assistant-panel" className="grid gap-5 md:grid-cols-[280px_minmax(0,1fr)]">
      {/* Historique — colonne fixe desktop, tiroir mobile (pattern panneau
          notifications du shell : backdrop + Échap via usePanelDismiss). */}
      <aside className="hidden overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm md:block md:max-h-[70vh]">
        {historyList}
      </aside>
      {historyOpen ? (
        <div className="fixed inset-0 z-40 md:hidden">
          <div
            className="absolute inset-0 bg-slate-900/40"
            aria-hidden="true"
            data-testid="assistant-history-backdrop"
            onClick={() => setHistoryOpen(false)}
          />
          <div className="absolute inset-y-0 start-0 w-80 max-w-[85vw] overflow-y-auto bg-white shadow-xl">
            {historyList}
          </div>
        </div>
      ) : null}

      <section className="flex min-h-[60vh] flex-col overflow-hidden rounded-2xl border border-slate-200 bg-slate-50/60 shadow-sm md:max-h-[70vh]">
        <div className="flex items-center justify-between gap-2 border-b border-slate-200 bg-white px-4 py-3">
          <p className="flex min-w-0 items-center gap-2 text-sm font-bold text-slate-900">
            <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-ia to-ia-dark">
              <Sparkles className="h-4 w-4 text-white" aria-hidden="true" />
            </span>
            <span className="truncate">{ta(locale, 'leo_name')}</span>
          </p>
          <div className="flex shrink-0 items-center gap-2">
            <button
              type="button"
              data-testid="assistant-history-toggle"
              aria-label={ta(locale, 'history_open')}
              aria-expanded={historyOpen}
              onClick={() => setHistoryOpen((open) => !open)}
              className="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-bold text-slate-600 transition hover:border-ia/40 hover:text-ia md:hidden"
            >
              <History className="h-4 w-4" aria-hidden="true" />
              {ta(locale, 'history_title')}
            </button>
            <button
              type="button"
              data-testid="assistant-new-conversation"
              onClick={startNewConversation}
              className="inline-flex items-center gap-1.5 rounded-lg border border-ia/30 bg-ia-light/60 px-2.5 py-1.5 text-xs font-bold text-ia-dark transition hover:bg-ia-light"
            >
              <Plus className="h-4 w-4" aria-hidden="true" />
              {ta(locale, 'new_conversation')}
            </button>
          </div>
        </div>

        {panelError === 'disabled' ? (
          <div
            data-testid="assistant-disabled"
            className="flex flex-1 flex-col items-center justify-center gap-3 p-8 text-center"
          >
            <span className="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100">
              <AlertTriangle className="h-7 w-7 text-slate-400" aria-hidden="true" />
            </span>
            <p className="text-lg font-bold text-slate-950">{ta(locale, 'disabled_title')}</p>
            <p className="max-w-md text-sm leading-relaxed text-slate-500">{ta(locale, 'disabled_body')}</p>
          </div>
        ) : (
          <>
            {panelError !== null ? (
              <p
                data-testid={`assistant-error-${panelError}`}
                role="alert"
                className="mx-4 mt-3 flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-2.5 text-sm text-amber-800"
              >
                <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
                {panelError === 'credits'
                  ? ta(locale, 'error_credits')
                  : panelError === 'budget'
                    ? ta(locale, 'error_budget')
                    : panelError === 'rate_limited'
                      ? ta(locale, 'error_rate_limited')
                      : ta(locale, 'error_generic')}
              </p>
            ) : null}

            <ChatThread
              messages={messages}
              locale={locale}
              sending={sending}
              onConfirm={handleConfirm}
              onReject={handleReject}
            />

            <ChatComposer
              value={input}
              locale={locale}
              sending={sending}
              onChange={setInput}
              onSubmit={() => void handleSend()}
            />
          </>
        )}
      </section>
    </div>
  );
}
