'use client';

/**
 * #8760 [BOS-CHAT] — Boîte de réception des demandes publiques (Business OS).
 *
 * Étape 9 du parcours cible : « le professionnel traite la demande depuis son
 * Business OS ». La surface consommée est `/api/v1/conversations/public`
 * (`auth:sanctum` + `tenant`), déjà couverte par `PublicConversationTest`.
 *
 * Décisions de conception :
 *   - **Mobile-first** : une seule colonne en dessous de `md`, bascule
 *     liste ↔ fil. Un gérant consulte ses demandes depuis son téléphone.
 *   - **Aucun état de succès déduit du client** : un message n'est affiché
 *     comme envoyé qu'après un `201` du serveur.
 *   - **Polling, pas de faux temps réel** : `after_seq` uniquement, en pause
 *     quand l'onglet est masqué, reprise immédiate au retour de visibilité.
 *     L'hébergement actuel ne garantit aucun WebSocket (#8746/#8749).
 *   - **États explicites** : chargement, vide, erreur réseau, session expirée,
 *     fil indisponible. Un `404` n'est jamais présenté comme une panne : le
 *     serveur l'utilise pour un fil d'un autre tenant, un fil supprimé, et un
 *     employé non autorisé — indistinguables par conception.
 */

import { ArrowLeft, CheckCheck, Inbox, MessageSquare, RefreshCw } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState, useSyncExternalStore } from 'react';

import { ModulePageShell } from '@/components/module-page-shell';
import { getPreferredLocale, type AppLocale } from '@/lib/i18n';
import { publicInboxT } from '@/lib/i18n/public-inbox';
import {
  CONVERSATION_MESSAGE_MAX,
  fetchInboxMessages,
  fetchInboxThread,
  fetchInboxThreads,
  sendInboxMessage,
  type InboxMessage,
  type InboxThreadSummary,
} from '@/lib/public-conversation-inbox';

/** Intervalle de rafraîchissement du fil ouvert (curseur `after_seq`). */
const POLL_INTERVAL_MS = 15_000;

function formatTimestamp(iso: string | null, locale: AppLocale): string {
  if (iso === null || iso === '') {
    return '';
  }

  const date = new Date(iso);

  if (Number.isNaN(date.getTime())) {
    return '';
  }

  return new Intl.DateTimeFormat(locale, {
    day: '2-digit',
    month: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  }).format(date);
}

export function PublicInboxSpace() {
  const [threads, setThreads] = useState<InboxThreadSummary[]>([]);
  const [selectedId, setSelectedId] = useState<number | null>(null);
  const [messages, setMessages] = useState<InboxMessage[]>([]);
  const [lastSeq, setLastSeq] = useState<number>(0);
  const [hasMore, setHasMore] = useState<boolean>(false);
  const [nextBeforeSeq, setNextBeforeSeq] = useState<number | null>(null);

  const [listState, setListState] = useState<'loading' | 'ready' | 'error' | 'unauthenticated'>('loading');
  const [threadState, setThreadState] = useState<'idle' | 'loading' | 'ready' | 'error' | 'unavailable'>('idle');

  const [draft, setDraft] = useState<string>('');
  const [sending, setSending] = useState<boolean>(false);
  const [sendError, setSendError] = useState<string | null>(null);

  const selectedRef = useRef<number | null>(null);
  selectedRef.current = selectedId;

  const locale = useSyncExternalStore<AppLocale>(() => () => {}, getPreferredLocale, () => 'fr');

  // ── Liste ────────────────────────────────────────────────────────────────
  const loadList = useCallback(async (): Promise<void> => {
    setListState('loading');

    const result = await fetchInboxThreads();

    if (result.status === 'ok') {
      setThreads(result.data);
      setListState('ready');
      return;
    }

    setListState(result.status === 'unauthenticated' ? 'unauthenticated' : 'error');
  }, []);

  useEffect(() => {
    void loadList();
  }, [loadList]);

  // ── Fil ouvert ───────────────────────────────────────────────────────────
  const loadThread = useCallback(async (threadId: number): Promise<void> => {
    setThreadState('loading');
    setSendError(null);

    const result = await fetchInboxThread(threadId);

    // Réponse arrivée après un changement de sélection : on l'ignore pour ne
    // pas écraser le fil courant par un résultat périmé.
    if (selectedRef.current !== threadId) {
      return;
    }

    if (result.status === 'ok') {
      setMessages(result.data.messages);
      setLastSeq(result.data.last_message_seq);
      setHasMore(false);
      setNextBeforeSeq(null);
      setThreadState('ready');
      return;
    }

    setThreadState(result.status === 'not_found' ? 'unavailable' : 'error');
  }, []);

  const selectThread = useCallback(
    (threadId: number): void => {
      setSelectedId(threadId);
      setMessages([]);
      setLastSeq(0);
      void loadThread(threadId);
    },
    [loadThread],
  );

  // ── Polling `after_seq` (aucun WebSocket) ────────────────────────────────
  useEffect(() => {
    if (selectedId === null || threadState !== 'ready') {
      return;
    }

    let cancelled = false;
    let timer: ReturnType<typeof setTimeout> | null = null;

    const tick = async (): Promise<void> => {
      if (cancelled || document.visibilityState === 'hidden') {
        timer = setTimeout(() => void tick(), POLL_INTERVAL_MS);
        return;
      }

      const result = await fetchInboxMessages(selectedId, { afterSeq: lastSeq });

      if (!cancelled && result.status === 'ok' && result.data.messages.length > 0) {
        setMessages((current) => {
          const known = new Set(current.map((message) => message.id));
          return [...current, ...result.data.messages.filter((message) => !known.has(message.id))];
        });
        setLastSeq(result.data.last_seq);
      }

      if (!cancelled) {
        timer = setTimeout(() => void tick(), POLL_INTERVAL_MS);
      }
    };

    timer = setTimeout(() => void tick(), POLL_INTERVAL_MS);

    // Retour d'onglet : on rattrape tout de suite au lieu d'attendre le tick.
    const onVisible = (): void => {
      if (document.visibilityState === 'visible') {
        void tick();
      }
    };

    const onOnline = (): void => void tick();

    document.addEventListener('visibilitychange', onVisible);
    window.addEventListener('online', onOnline);

    return () => {
      cancelled = true;

      if (timer !== null) {
        clearTimeout(timer);
      }

      document.removeEventListener('visibilitychange', onVisible);
      window.removeEventListener('online', onOnline);
    };
  }, [selectedId, threadState, lastSeq]);

  // ── Historique plus ancien ───────────────────────────────────────────────
  const loadOlder = useCallback(async (): Promise<void> => {
    if (selectedId === null || nextBeforeSeq === null) {
      return;
    }

    const result = await fetchInboxMessages(selectedId, { beforeSeq: nextBeforeSeq });

    if (result.status !== 'ok') {
      return;
    }

    setMessages((current) => {
      const known = new Set(current.map((message) => message.id));
      return [...result.data.messages.filter((message) => !known.has(message.id)), ...current];
    });
    setHasMore(result.data.has_more);
    setNextBeforeSeq(result.data.next_before_seq);
  }, [selectedId, nextBeforeSeq]);

  // ── Envoi ────────────────────────────────────────────────────────────────
  const send = useCallback(async (): Promise<void> => {
    if (selectedId === null || sending) {
      return;
    }

    const body = draft.trim();

    if (body === '') {
      setSendError(publicInboxT(locale, 'emptyBody'));
      return;
    }

    if (body.length > CONVERSATION_MESSAGE_MAX) {
      setSendError(publicInboxT(locale, 'tooLong', { max: CONVERSATION_MESSAGE_MAX }));
      return;
    }

    setSending(true);
    setSendError(null);

    const result = await sendInboxMessage(selectedId, body);

    if (result.status === 'ok') {
      // `201` reçu : le message existe côté serveur, on l'affiche.
      setMessages((current) => [...current, result.data]);
      setLastSeq((current) => Math.max(current, result.data.seq));
      setDraft('');
      setSending(false);
      return;
    }

    setSendError(
      result.status === 'unauthenticated'
        ? publicInboxT(locale, 'unauthenticated')
        : publicInboxT(locale, 'sendError'),
    );
    setSending(false);
  }, [selectedId, sending, draft, locale]);

  const selectedThread = useMemo(
    () => threads.find((thread) => thread.id === selectedId) ?? null,
    [threads, selectedId],
  );

  const showThread = selectedId !== null;

  return (
    <ModulePageShell
      title={publicInboxT(locale, 'pageTitle')}
      subtitle={publicInboxT(locale, 'pageSubtitle')}
      description={publicInboxT(locale, 'pageDescription')}
      icon={Inbox}
      actions={
        <button
          type="button"
          onClick={() => void loadList()}
          className="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
        >
          <RefreshCw className="h-3.5 w-3.5" aria-hidden="true" />
          {publicInboxT(locale, 'listRetry')}
        </button>
      }
    >
      {/* `min-h-0` est indispensable : sans lui le scroll interne perd contre la
          hauteur du parent flexbox (piège classique). */}
      <div className="flex h-[calc(100dvh-13rem)] min-h-[26rem] gap-3">
        {/* ── Liste ────────────────────────────────────────────────────── */}
        <div
          className={`${showThread ? 'hidden md:block' : 'block'} h-full md:w-80 md:shrink-0`}
        >
          <div className="flex h-full flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white">
            <div className="border-b border-slate-100 px-4 py-3">
              <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                {publicInboxT(locale, 'listTitle')}
              </p>
            </div>

            <div className="min-h-0 flex-1 overflow-y-auto">
              {listState === 'loading' ? (
                <p role="status" className="px-4 py-6 text-sm text-slate-500">
                  {publicInboxT(locale, 'listLoading')}
                </p>
              ) : null}

              {listState === 'unauthenticated' ? (
                <p role="alert" className="px-4 py-6 text-sm text-amber-700">
                  {publicInboxT(locale, 'unauthenticated')}
                </p>
              ) : null}

              {listState === 'error' ? (
                <div role="alert" className="px-4 py-6">
                  <p className="text-sm text-red-700">{publicInboxT(locale, 'listError')}</p>
                  <button
                    type="button"
                    onClick={() => void loadList()}
                    className="mt-2 text-sm font-medium text-emerald-700 underline"
                  >
                    {publicInboxT(locale, 'listRetry')}
                  </button>
                </div>
              ) : null}

              {listState === 'ready' && threads.length === 0 ? (
                <div className="px-4 py-8">
                  <p className="text-sm font-medium text-slate-800">
                    {publicInboxT(locale, 'listEmpty')}
                  </p>
                  <p className="mt-1 text-sm text-slate-500">
                    {publicInboxT(locale, 'listEmptyBody')}
                  </p>
                </div>
              ) : null}

              {listState === 'ready' ? (
                <ul className="divide-y divide-slate-100">
                  {threads.map((thread) => (
                    <li key={thread.id}>
                      <button
                        type="button"
                        onClick={() => selectThread(thread.id)}
                        aria-current={thread.id === selectedId ? 'true' : undefined}
                        className={`w-full px-4 py-3 text-left transition hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-emerald-600 ${
                          thread.id === selectedId ? 'bg-emerald-50/60' : ''
                        }`}
                      >
                        <span className="flex items-baseline justify-between gap-2">
                          <span className="truncate text-sm font-semibold text-slate-900">
                            {thread.title !== '' ? thread.title : publicInboxT(locale, 'noSlug')}
                          </span>
                          {thread.unread ? (
                            <span className="shrink-0 rounded-full bg-emerald-600 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-white">
                              {publicInboxT(locale, 'unread')}
                            </span>
                          ) : null}
                        </span>
                        <span className="mt-1 block text-xs text-slate-500">
                          {formatTimestamp(thread.last_message_at, locale)}
                        </span>
                      </button>
                    </li>
                  ))}
                </ul>
              ) : null}
            </div>
          </div>
        </div>

        {/* ── Fil ──────────────────────────────────────────────────────── */}
        <div className={`${showThread ? 'block' : 'hidden md:block'} min-h-0 min-w-0 flex-1`}>
          <div className="flex h-full flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white">
            {selectedThread === null ? (
              <div className="flex h-full flex-col items-center justify-center px-6 text-center">
                <MessageSquare className="h-8 w-8 text-slate-300" aria-hidden="true" />
                <p className="mt-3 text-sm font-medium text-slate-800">
                  {publicInboxT(locale, 'threadNotSelected')}
                </p>
                <p className="mt-1 max-w-xs text-sm text-slate-500">
                  {publicInboxT(locale, 'threadNotSelectedBody')}
                </p>
              </div>
            ) : (
              <>
                <div className="flex items-center gap-2 border-b border-slate-100 px-3 py-2 md:px-4">
                  <button
                    type="button"
                    onClick={() => setSelectedId(null)}
                    aria-label={publicInboxT(locale, 'backToList')}
                    className="inline-flex items-center rounded-lg p-1.5 text-slate-600 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-emerald-600 md:hidden"
                  >
                    <ArrowLeft className="h-4 w-4" aria-hidden="true" />
                  </button>
                  <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-semibold text-slate-900">
                      {selectedThread.title !== ''
                        ? selectedThread.title
                        : publicInboxT(locale, 'noSlug')}
                    </p>
                    {selectedThread.target_slug !== null ? (
                      <p className="truncate text-xs text-slate-500">{selectedThread.target_slug}</p>
                    ) : null}
                  </div>
                </div>

                <div className="min-h-0 flex-1 overflow-y-auto px-3 py-3 md:px-4">
                  {threadState === 'loading' ? (
                    <p role="status" className="py-6 text-center text-sm text-slate-500">
                      {publicInboxT(locale, 'threadLoading')}
                    </p>
                  ) : null}

                  {threadState === 'error' ? (
                    <div role="alert" className="py-6 text-center">
                      <p className="text-sm text-red-700">{publicInboxT(locale, 'threadError')}</p>
                      <button
                        type="button"
                        onClick={() => void loadThread(selectedThread.id)}
                        className="mt-2 text-sm font-medium text-emerald-700 underline"
                      >
                        {publicInboxT(locale, 'listRetry')}
                      </button>
                    </div>
                  ) : null}

                  {threadState === 'unavailable' ? (
                    <div role="alert" className="py-6 text-center">
                      <p className="text-sm font-medium text-slate-800">
                        {publicInboxT(locale, 'threadUnavailable')}
                      </p>
                      <p className="mt-1 text-sm text-slate-500">
                        {publicInboxT(locale, 'threadUnavailableBody')}
                      </p>
                    </div>
                  ) : null}

                  {threadState === 'ready' ? (
                    <>
                      {hasMore ? (
                        <div className="pb-3 text-center">
                          <button
                            type="button"
                            onClick={() => void loadOlder()}
                            className="text-xs font-medium text-emerald-700 underline"
                          >
                            {publicInboxT(locale, 'loadOlder')}
                          </button>
                        </div>
                      ) : messages.length > 0 ? (
                        <p className="pb-3 text-center text-xs text-slate-400">
                          {publicInboxT(locale, 'noOlder')}
                        </p>
                      ) : null}

                      {messages.length === 0 ? (
                        <div className="py-8 text-center">
                          <p className="text-sm font-medium text-slate-800">
                            {publicInboxT(locale, 'threadEmpty')}
                          </p>
                          <p className="mt-1 text-sm text-slate-500">
                            {publicInboxT(locale, 'threadEmptyBody')}
                          </p>
                        </div>
                      ) : (
                        <ul className="space-y-2">
                          {messages.map((message) => {
                            const mine = message.from === 'professional';

                            return (
                              <li
                                key={message.id}
                                className={`flex ${mine ? 'justify-end' : 'justify-start'}`}
                              >
                                <div
                                  className={`max-w-[85%] rounded-2xl px-3 py-2 text-sm ${
                                    mine
                                      ? 'bg-emerald-600 text-white'
                                      : 'bg-slate-100 text-slate-900'
                                  }`}
                                >
                                  {message.deleted === true ? (
                                    <p className="italic opacity-70">
                                      {publicInboxT(locale, 'threadUnavailable')}
                                    </p>
                                  ) : (
                                    <p className="whitespace-pre-wrap break-words">{message.body}</p>
                                  )}
                                  <span
                                    className={`mt-1 flex items-center gap-1 text-[10px] ${
                                      mine ? 'text-emerald-50/80' : 'text-slate-500'
                                    }`}
                                  >
                                    {formatTimestamp(message.created_at, locale)}
                                    {mine ? <CheckCheck className="h-3 w-3" aria-hidden="true" /> : null}
                                  </span>
                                </div>
                              </li>
                            );
                          })}
                        </ul>
                      )}
                    </>
                  ) : null}
                </div>

                {threadState === 'ready' ? (
                  <div className="border-t border-slate-100 px-3 py-2 md:px-4">
                    {sendError !== null ? (
                      <p role="alert" className="mb-2 text-xs text-red-700">
                        {sendError}
                      </p>
                    ) : null}

                    <form
                      onSubmit={(event) => {
                        event.preventDefault();
                        void send();
                      }}
                      className="flex items-end gap-2"
                    >
                      <label htmlFor="public-inbox-composer" className="sr-only">
                        {publicInboxT(locale, 'composerLabel')}
                      </label>
                      <textarea
                        id="public-inbox-composer"
                        value={draft}
                        onChange={(event) => setDraft(event.target.value)}
                        rows={2}
                        maxLength={CONVERSATION_MESSAGE_MAX}
                        placeholder={publicInboxT(locale, 'composerPlaceholder')}
                        className="min-h-[2.75rem] flex-1 resize-y rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600/20"
                      />
                      <button
                        type="submit"
                        disabled={sending || draft.trim() === ''}
                        className="inline-flex h-11 shrink-0 items-center rounded-xl bg-emerald-600 px-4 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
                      >
                        {sending
                          ? publicInboxT(locale, 'sending')
                          : publicInboxT(locale, 'send')}
                      </button>
                    </form>
                  </div>
                ) : null}
              </>
            )}
          </div>
        </div>
      </div>
    </ModulePageShell>
  );
}

export default PublicInboxSpace;
