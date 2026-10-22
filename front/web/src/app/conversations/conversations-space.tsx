'use client';

import { useCallback, useEffect, useRef, useState } from 'react';
import { t } from '@/lib/i18n/locale-catalog';
import type { AppLocale } from '@/lib/i18n';
import {
  CONVERSATION_MESSAGE_MAX,
  fetchConversation,
  fetchConversationMessages,
  fetchMyConversations,
  sendConversationMessage,
  type PublicConversationDetail,
  type PublicConversationMessage,
  type PublicConversationSummary,
} from '@/lib/public-conversation-api';

/**
 * #8760 [BOS-CHAT] — Espace conversations du client public.
 *
 * Mobile-first : une seule colonne, la liste OU le fil, jamais les deux côte à
 * côte sur téléphone. Sur tablette/ordinateur la liste reste visible à gauche.
 *
 * ⚠️ Pas de faux temps réel : le fil se rafraîchit par polling du curseur `seq`
 * (toutes les 15 s, uniquement quand l'onglet est visible). Aucun WebSocket —
 * l'hébergement ne le garantit pas, et un indicateur « en direct » mensonger
 * serait pire qu'un rafraîchissement honnête.
 *
 * ⚠️ Aucun état de succès optimiste : un message n'apparaît comme envoyé
 * qu'après un `201` du serveur. En cas d'échec, le texte reste dans le champ
 * et un message d'erreur explicite s'affiche — jamais de perte silencieuse.
 *
 * ⚠️ États couverts : chargement, erreur réseau, non authentifié, fil
 * introuvable (404 = fil d'un autre client ou supprimé), liste vide, fil vide.
 */

const POLL_INTERVAL_MS = 15_000;

interface ConversationsSpaceProps {
  locale: AppLocale;
}

export default function ConversationsSpace({ locale }: ConversationsSpaceProps) {
  const [threads, setThreads] = useState<PublicConversationSummary[] | null>(null);
  const [listState, setListState] = useState<'loading' | 'ready' | 'error' | 'unauthenticated'>('loading');

  const [active, setActive] = useState<PublicConversationDetail | null>(null);
  const [activeState, setActiveState] = useState<'idle' | 'loading' | 'error' | 'not_found'>('idle');

  const [draft, setDraft] = useState('');
  const [sending, setSending] = useState(false);
  const [sendError, setSendError] = useState(false);

  const lastSeqRef = useRef(0);
  const scrollRef = useRef<HTMLDivElement | null>(null);

  const loadList = useCallback(async () => {
    setListState('loading');
    const result = await fetchMyConversations();

    if (result.status === 'ok') {
      setThreads(result.data);
      setListState('ready');
      return;
    }

    if (result.status === 'unauthenticated') {
      setListState('unauthenticated');
      return;
    }

    setListState('error');
  }, []);

  useEffect(() => {
    void loadList();
  }, [loadList]);

  const openThread = useCallback(async (summary: PublicConversationSummary) => {
    setActiveState('loading');
    setActive(null);
    setSendError(false);

    const result = await fetchConversation(summary.company_id, summary.thread_id);

    if (result.status === 'ok') {
      setActive(result.data);
      lastSeqRef.current = result.data.last_message_seq;
      setActiveState('idle');
      return;
    }

    if (result.status === 'not_found') {
      setActiveState('not_found');
      return;
    }

    setActiveState('error');
  }, []);

  /**
   * Rattrapage par curseur : ne récupère que ce qui est arrivé APRÈS le dernier
   * `seq` connu. C'est ce qui rend le polling supportable sur connexion limitée.
   */
  const catchUp = useCallback(async () => {
    if (!active) {
      return;
    }

    const result = await fetchConversationMessages(active.company_id, active.thread_id, {
      afterSeq: lastSeqRef.current,
    });

    if (result.status !== 'ok' || result.data.messages.length === 0) {
      return;
    }

    lastSeqRef.current = result.data.last_seq;

    setActive((current) => {
      if (!current) {
        return current;
      }

      const known = new Set(current.messages.map((message) => message.id));
      const fresh = result.data.messages.filter((message) => !known.has(message.id));

      if (fresh.length === 0) {
        return current;
      }

      return { ...current, messages: [...current.messages, ...fresh] };
    });
  }, [active]);

  useEffect(() => {
    if (!active) {
      return;
    }

    const timer = window.setInterval(() => {
      // Ne polle pas un onglet caché : économise batterie et données.
      if (document.visibilityState === 'visible') {
        void catchUp();
      }
    }, POLL_INTERVAL_MS);

    return () => window.clearInterval(timer);
  }, [active, catchUp]);

  useEffect(() => {
    const node = scrollRef.current;

    if (node) {
      node.scrollTop = node.scrollHeight;
    }
  }, [active?.messages.length]);

  const submit = useCallback(async () => {
    if (!active || sending) {
      return;
    }

    const body = draft.trim();

    if (body.length === 0 || body.length > CONVERSATION_MESSAGE_MAX) {
      return;
    }

    setSending(true);
    setSendError(false);

    const result = await sendConversationMessage(active.company_id, active.thread_id, body);

    setSending(false);

    if (result.status !== 'ok') {
      // Le texte reste dans le champ : l'utilisateur ne perd pas sa saisie.
      setSendError(true);
      return;
    }

    setDraft('');
    lastSeqRef.current = Math.max(lastSeqRef.current, result.data.seq);
    setActive((current) =>
      current ? { ...current, messages: [...current.messages, result.data] } : current,
    );
  }, [active, draft, sending]);

  const loadEarlier = useCallback(async () => {
    if (!active) {
      return;
    }

    const oldest = active.messages[0];

    if (!oldest) {
      return;
    }

    const result = await fetchConversationMessages(active.company_id, active.thread_id, {
      beforeSeq: oldest.seq,
    });

    if (result.status !== 'ok' || result.data.messages.length === 0) {
      return;
    }

    setActive((current) => {
      if (!current) {
        return current;
      }

      const known = new Set(current.messages.map((message) => message.id));
      const older = result.data.messages.filter((message) => !known.has(message.id));

      return { ...current, messages: [...older, ...current.messages] };
    });
  }, [active]);

  // ── Liste ────────────────────────────────────────────────────────────────

  if (listState === 'unauthenticated') {
    return (
      <p className="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900" role="status">
        {t(locale, 'discovery.conversationLoginRequired')}
      </p>
    );
  }

  if (listState === 'error') {
    return (
      <div className="rounded-lg border border-red-200 bg-red-50 p-4" role="alert">
        <p className="text-sm text-red-900">{t(locale, 'discovery.conversationsLoadError')}</p>
        <button
          type="button"
          onClick={() => void loadList()}
          className="mt-3 rounded-md bg-red-700 px-3 py-2 text-sm font-medium text-white"
        >
          {t(locale, 'discovery.retry')}
        </button>
      </div>
    );
  }

  if (listState === 'loading' || threads === null) {
    return <p className="p-4 text-sm text-gray-600">{t(locale, 'discovery.loading')}</p>;
  }

  if (threads.length === 0) {
    return (
      <p className="rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700" role="status">
        {t(locale, 'discovery.conversationsEmpty')}
      </p>
    );
  }

  // ── Fil actif ────────────────────────────────────────────────────────────

  if (activeState === 'not_found') {
    return (
      <div className="rounded-lg border border-gray-200 bg-gray-50 p-4" role="alert">
        <p className="text-sm text-gray-800">{t(locale, 'discovery.conversationUnavailable')}</p>
        <button
          type="button"
          onClick={() => {
            setActiveState('idle');
            setActive(null);
            void loadList();
          }}
          className="mt-3 rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-800"
        >
          {t(locale, 'discovery.conversationBack')}
        </button>
      </div>
    );
  }

  if (activeState === 'error') {
    return (
      <div className="rounded-lg border border-red-200 bg-red-50 p-4" role="alert">
        <p className="text-sm text-red-900">{t(locale, 'discovery.conversationsLoadError')}</p>
        <button
          type="button"
          onClick={() => setActiveState('idle')}
          className="mt-3 rounded-md bg-red-700 px-3 py-2 text-sm font-medium text-white"
        >
          {t(locale, 'discovery.retry')}
        </button>
      </div>
    );
  }

  if (active) {
    return (
      <section className="flex h-[70vh] flex-col rounded-lg border border-gray-200 bg-white">
        <header className="flex items-center gap-3 border-b border-gray-200 p-3">
          <button
            type="button"
            onClick={() => {
              setActive(null);
              setActiveState('idle');
              void loadList();
            }}
            className="rounded-md border border-gray-300 px-2 py-1 text-sm text-gray-800 md:hidden"
          >
            {t(locale, 'discovery.conversationBack')}
          </button>
          <h2 className="truncate text-sm font-semibold text-gray-900">{active.title}</h2>
        </header>

        <div ref={scrollRef} className="flex-1 space-y-3 overflow-y-auto p-3">
          {active.messages.length === 0 ? (
            <p className="text-sm text-gray-600">{t(locale, 'discovery.conversationEmpty')}</p>
          ) : (
            <>
              <button
                type="button"
                onClick={() => void loadEarlier()}
                className="w-full rounded-md border border-gray-200 py-2 text-xs text-gray-700"
              >
                {t(locale, 'discovery.conversationLoadMore')}
              </button>

              {active.messages.map((message: PublicConversationMessage) => (
                <article
                  key={message.id}
                  className={
                    message.from === 'client'
                      ? 'ml-auto max-w-[85%] rounded-lg bg-gray-900 p-3 text-sm text-white'
                      : 'mr-auto max-w-[85%] rounded-lg bg-gray-100 p-3 text-sm text-gray-900'
                  }
                >
                  <p className="mb-1 text-xs opacity-70">
                    {message.from === 'client'
                      ? t(locale, 'discovery.conversationFromClient')
                      : t(locale, 'discovery.conversationFromProfessional')}
                  </p>
                  <p className="whitespace-pre-wrap break-words">
                    {message.deleted ? '—' : message.body}
                  </p>
                </article>
              ))}
            </>
          )}
        </div>

        <footer className="border-t border-gray-200 p-3">
          {sendError ? (
            <p className="mb-2 text-xs text-red-700" role="alert">
              {t(locale, 'discovery.conversationSendError')}
            </p>
          ) : null}

          <div className="flex items-end gap-2">
            <label className="sr-only" htmlFor="public-conversation-draft">
              {t(locale, 'discovery.conversationPlaceholder')}
            </label>
            <textarea
              id="public-conversation-draft"
              value={draft}
              onChange={(event) => setDraft(event.target.value.slice(0, CONVERSATION_MESSAGE_MAX))}
              placeholder={t(locale, 'discovery.conversationPlaceholder')}
              rows={2}
              maxLength={CONVERSATION_MESSAGE_MAX}
              className="min-h-[44px] flex-1 resize-none rounded-md border border-gray-300 p-2 text-sm"
            />
            <button
              type="button"
              onClick={() => void submit()}
              disabled={sending || draft.trim().length === 0}
              className="min-h-[44px] rounded-md bg-gray-900 px-4 text-sm font-medium text-white disabled:opacity-50"
            >
              {sending
                ? t(locale, 'discovery.conversationSending')
                : t(locale, 'discovery.conversationSend')}
            </button>
          </div>
        </footer>
      </section>
    );
  }

  // ── Liste des conversations ──────────────────────────────────────────────

  return (
    <section aria-label={t(locale, 'discovery.conversationsTitle')}>
      <h2 className="mb-3 text-base font-semibold text-gray-900">
        {t(locale, 'discovery.conversationsTitle')}
      </h2>

      <ul className="space-y-2">
        {threads.map((thread) => (
          <li key={`${thread.company_id}-${thread.thread_id}`}>
            <button
              type="button"
              onClick={() => void openThread(thread)}
              className="flex w-full items-center justify-between gap-3 rounded-lg border border-gray-200 bg-white p-3 text-left"
            >
              <span className="min-w-0">
                <span className="block truncate text-sm font-medium text-gray-900">{thread.title}</span>
                {thread.last_message_at ? (
                  <span className="block text-xs text-gray-500">
                    {new Date(thread.last_message_at).toLocaleString(locale)}
                  </span>
                ) : null}
              </span>

              {thread.unread ? (
                <span className="shrink-0 rounded-full bg-gray-900 px-2 py-0.5 text-xs text-white">
                  {t(locale, 'discovery.conversationUnread')}
                </span>
              ) : null}
            </button>
          </li>
        ))}
      </ul>
    </section>
  );
}
