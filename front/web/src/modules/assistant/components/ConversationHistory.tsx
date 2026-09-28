'use client';

import { useState } from 'react';
import { History, Trash2 } from 'lucide-react';

import type { AppLocale } from '@/lib/i18n';
import { toIntlLocale } from '@/lib/i18n';
import type { AssistantConversationSummary } from '../lib/api';
import { ta } from '../lib/i18n';

type ConversationHistoryProps = {
  conversations: AssistantConversationSummary[];
  loading: boolean;
  loadError: boolean;
  currentId: number | null;
  locale: AppLocale;
  onSelect: (conversationId: number) => void;
  onDelete: (conversationId: number) => Promise<void>;
};

function conversationTitle(conversation: AssistantConversationSummary, locale: AppLocale): string {
  if (conversation.title && conversation.title.trim() !== '') {
    return conversation.title;
  }
  const rawDate = conversation.created_at ?? conversation.updated_at;
  const date = rawDate ? new Date(rawDate) : null;
  const formatted = date && !Number.isNaN(date.getTime())
    ? date.toLocaleDateString(toIntlLocale(locale))
    : '—';
  return ta(locale, 'untitled', { date: formatted });
}

function conversationDate(conversation: AssistantConversationSummary, locale: AppLocale): string {
  const rawDate = conversation.updated_at ?? conversation.created_at;
  if (!rawDate) {
    return '';
  }
  const date = new Date(rawDate);
  return Number.isNaN(date.getTime()) ? '' : date.toLocaleDateString(toIntlLocale(locale));
}

/**
 * BOS-035 (#8224) — historique des conversations (titres, dates, tokens) via
 * `GET /ai/chat/history`. Sélectionner une conversation reprend son
 * `conversation_id` pour les messages suivants ; la suppression passe par
 * une confirmation inline en deux clics (jamais de `window.confirm`, #3494).
 */
export function ConversationHistory({
  conversations,
  loading,
  loadError,
  currentId,
  locale,
  onSelect,
  onDelete,
}: ConversationHistoryProps) {
  const [confirmDeleteId, setConfirmDeleteId] = useState<number | null>(null);
  const [deletingId, setDeletingId] = useState<number | null>(null);
  const [deleteError, setDeleteError] = useState(false);

  const handleDelete = async (conversationId: number) => {
    setDeletingId(conversationId);
    setDeleteError(false);
    try {
      await onDelete(conversationId);
      setConfirmDeleteId(null);
    } catch {
      setDeleteError(true);
    } finally {
      setDeletingId(null);
    }
  };

  return (
    <div data-testid="assistant-history" className="flex h-full flex-col">
      <p className="flex items-center gap-2 border-b border-slate-100 px-4 py-3 text-xs font-bold uppercase tracking-wider text-slate-500">
        <History className="h-4 w-4" aria-hidden="true" />
        {ta(locale, 'history_title')}
      </p>

      {loading ? (
        <div className="space-y-2 p-4" data-testid="assistant-history-loading">
          {[0, 1, 2].map((line) => (
            <div key={line} className="h-10 animate-pulse rounded-xl bg-slate-100" />
          ))}
        </div>
      ) : loadError ? (
        <p className="p-4 text-sm text-red-700" data-testid="assistant-history-error">
          {ta(locale, 'history_load_error')}
        </p>
      ) : conversations.length === 0 ? (
        <p className="p-4 text-sm text-slate-500" data-testid="assistant-history-empty">
          {ta(locale, 'history_empty')}
        </p>
      ) : (
        <ul className="flex-1 space-y-1 overflow-y-auto p-2">
          {conversations.map((conversation) => {
            const selected = conversation.id === currentId;
            const confirming = confirmDeleteId === conversation.id;
            return (
              <li key={conversation.id}>
                <div
                  className={`group flex items-start gap-1 rounded-xl border px-2 py-2 transition ${
                    selected
                      ? 'border-ia/40 bg-ia-light/60'
                      : 'border-transparent hover:border-slate-200 hover:bg-slate-50'
                  }`}
                >
                  <button
                    type="button"
                    data-testid={`assistant-history-item-${conversation.id}`}
                    aria-current={selected ? 'true' : undefined}
                    onClick={() => onSelect(conversation.id)}
                    className="min-w-0 flex-1 text-start"
                  >
                    <span className="block truncate text-sm font-bold text-slate-800">
                      {conversationTitle(conversation, locale)}
                    </span>
                    <span className="mt-0.5 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                      <span>{conversationDate(conversation, locale)}</span>
                      <span className="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-600">
                        {ta(locale, 'tokens_label', { count: conversation.token_count })}
                      </span>
                    </span>
                  </button>
                  <button
                    type="button"
                    data-testid={`assistant-history-delete-${conversation.id}`}
                    aria-label={ta(locale, 'delete')}
                    onClick={() => {
                      setDeleteError(false);
                      setConfirmDeleteId(confirming ? null : conversation.id);
                    }}
                    className="mt-0.5 rounded-lg p-1.5 text-slate-400 transition hover:bg-red-50 hover:text-red-600"
                  >
                    <Trash2 className="h-4 w-4" aria-hidden="true" />
                  </button>
                </div>
                {confirming ? (
                  <div className="mt-1 flex items-center gap-2 rounded-xl border border-red-100 bg-red-50 px-3 py-2">
                    <span className="flex-1 text-xs font-bold text-red-700">{ta(locale, 'delete_confirm')}</span>
                    <button
                      type="button"
                      data-testid={`assistant-history-delete-confirm-${conversation.id}`}
                      disabled={deletingId === conversation.id}
                      onClick={() => void handleDelete(conversation.id)}
                      className="rounded-lg bg-red-600 px-2.5 py-1 text-xs font-bold text-white transition hover:bg-red-700 disabled:opacity-60"
                    >
                      {ta(locale, 'delete')}
                    </button>
                    <button
                      type="button"
                      disabled={deletingId === conversation.id}
                      onClick={() => setConfirmDeleteId(null)}
                      className="rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-bold text-slate-600 transition hover:bg-slate-50 disabled:opacity-60"
                    >
                      {ta(locale, 'delete_cancel')}
                    </button>
                  </div>
                ) : null}
              </li>
            );
          })}
        </ul>
      )}

      {deleteError ? (
        <p className="border-t border-red-100 bg-red-50 px-4 py-2 text-xs text-red-700" data-testid="assistant-history-delete-error">
          {ta(locale, 'delete_error')}
        </p>
      ) : null}
    </div>
  );
}
