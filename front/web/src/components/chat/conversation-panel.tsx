'use client';

/**
 * #8751 [BOS-CHAT] — écran de conversation.
 *
 * Couvre les états exigés : chargement, erreur, hors-ligne, conversation vide,
 * pièces jointes, édition/suppression, réponse citée, mobile.
 *
 * Le rendu ne décide d'AUCUNE autorisation : les boutons d'édition ne sont
 * affichés que pour l'auteur, mais c'est le serveur qui refuse (404) si un
 * autre compte tente l'action. Masquer un bouton n'est jamais une sécurité.
 */

import {
  AlertCircle,
  ArrowLeft,
  CornerUpLeft,
  Download,
  Loader2,
  Paperclip,
  Pencil,
  Send,
  Trash2,
  WifiOff,
  X,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

import {
  attachmentUrl,
  deleteMessage,
  editMessage,
  fetchMessages,
  mergeMessages,
  newClientMessageId,
  sendMessage,
  type ChatMessage,
  type ChatThread,
} from '@/lib/chat';
import { useConversationPolling, type PollingState } from '@/components/chat/use-conversation-polling';

interface ConversationPanelProps {
  thread: ChatThread | null;
  currentUserId: number | null;
  onBack?: () => void;
  onThreadActivity?: (threadId: number) => void;
}

export function ConversationPanel({ thread, currentUserId, onBack, onThreadActivity }: ConversationPanelProps) {
  const [messages, setMessages] = useState<ChatMessage[]>([]);
  const [lastSeq, setLastSeq] = useState<number>(0);
  const [loading, setLoading] = useState<boolean>(false);
  const [loadingOlder, setLoadingOlder] = useState<boolean>(false);
  const [hasMore, setHasMore] = useState<boolean>(false);
  const [error, setError] = useState<string | null>(null);
  const [sendError, setSendError] = useState<string | null>(null);
  const [sending, setSending] = useState<boolean>(false);
  const [draft, setDraft] = useState<string>('');
  const [attachment, setAttachment] = useState<File | null>(null);
  const [replyTo, setReplyTo] = useState<ChatMessage | null>(null);
  const [editing, setEditing] = useState<ChatMessage | null>(null);
  const [pollingState, setPollingState] = useState<PollingState>('live');

  const bottomRef = useRef<HTMLDivElement | null>(null);
  const scrollRef = useRef<HTMLDivElement | null>(null);
  const fileInputRef = useRef<HTMLInputElement | null>(null);
  // Clé d'idempotence du brouillon en cours : elle survit à un échec d'envoi,
  // donc un « réessayer » ne crée pas un doublon.
  const pendingKeyRef = useRef<string | null>(null);

  const threadId = thread?.id ?? null;

  const scrollToBottom = useCallback((behavior: ScrollBehavior = 'smooth') => {
    bottomRef.current?.scrollIntoView({ behavior, block: 'end' });
  }, []);

  // ── Chargement initial / changement de fil ─────────────────────────────────
  useEffect(() => {
    if (threadId === null) {
      setMessages([]);
      setLastSeq(0);
      setHasMore(false);
      return;
    }

    let cancelled = false;

    setLoading(true);
    setError(null);
    setMessages([]);
    setReplyTo(null);
    setEditing(null);
    setAttachment(null);
    setDraft('');

    fetchMessages(threadId, { per_page: 30 })
      .then((page) => {
        if (cancelled) return;

        setMessages(page.data);
        setHasMore(page.meta.has_more);
        setLastSeq(page.meta.last_seq);
        // Après le rendu, on se place en bas — un fil se lit du plus récent.
        requestAnimationFrame(() => scrollToBottom('auto'));
      })
      .catch((cause: unknown) => {
        if (cancelled) return;

        setError(
          cause instanceof Error && cause.message !== ''
            ? cause.message
            : 'Impossible de charger cette conversation.',
        );
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });

    return () => {
      cancelled = true;
    };
  }, [threadId, scrollToBottom]);

  // ── Polling / reprise (#8749) ─────────────────────────────────────────────
  const handleIncoming = useCallback(
    (incoming: ChatMessage[], meta: { lastSeq: number }) => {
      setMessages((current) => mergeMessages(current, incoming));
      setLastSeq(meta.lastSeq);
      onThreadActivity?.(threadId ?? 0);
      requestAnimationFrame(() => scrollToBottom());
    },
    [onThreadActivity, scrollToBottom, threadId],
  );

  const refreshNow = useConversationPolling({
    threadId: threadId ?? 0,
    lastSeq,
    enabled: threadId !== null && !loading,
    onMessages: handleIncoming,
    onStateChange: setPollingState,
  });

  // ── Historique plus ancien ────────────────────────────────────────────────
  const loadOlder = useCallback(async () => {
    if (threadId === null || loadingOlder) return;

    const oldest = messages.find((message) => message.seq !== null);

    if (oldest?.seq == null) return;

    setLoadingOlder(true);

    try {
      const page = await fetchMessages(threadId, { before_seq: oldest.seq, per_page: 30 });
      setMessages((current) => mergeMessages(current, page.data));
      setHasMore(page.meta.has_more);
    } catch {
      setSendError('Impossible de charger les messages plus anciens.');
    } finally {
      setLoadingOlder(false);
    }
  }, [loadingOlder, messages, threadId]);

  // ── Envoi ─────────────────────────────────────────────────────────────────
  const submit = useCallback(async () => {
    if (threadId === null || sending) return;

    const body = draft.trim();

    if (body === '' && attachment === null) return;

    if (editing !== null) {
      setSending(true);
      setSendError(null);

      try {
        const updated = await editMessage(threadId, editing.id, body);
        setMessages((current) => current.map((message) => (message.id === updated.id ? updated : message)));
        setEditing(null);
        setDraft('');
      } catch (cause) {
        setSendError(cause instanceof Error ? cause.message : "L'édition a échoué.");
      } finally {
        setSending(false);
      }

      return;
    }

    // La clé n'est générée QU'UNE fois par brouillon : un retry réutilise la
    // même valeur et le serveur renvoie le message déjà écrit (200).
    pendingKeyRef.current ??= newClientMessageId();
    const key = pendingKeyRef.current;

    setSending(true);
    setSendError(null);

    try {
      const { message } = await sendMessage(threadId, {
        body,
        attachment,
        client_message_id: key,
        reply_to_message_id: replyTo?.id ?? null,
      });

      setMessages((current) => mergeMessages(current, [message]));
      setDraft('');
      setAttachment(null);
      setReplyTo(null);
      pendingKeyRef.current = null;
      requestAnimationFrame(() => scrollToBottom());
      // Un envoi peut avoir avancé le curseur serveur : on resynchronise tout
      // de suite plutôt que d'attendre le prochain tick.
      refreshNow();
    } catch (cause) {
      // La clé est CONSERVÉE : le prochain essai ne dupliquera pas.
      setSendError(
        cause instanceof Error && cause.message !== ''
          ? cause.message
          : "L'envoi a échoué. Votre message est conservé.",
      );
    } finally {
      setSending(false);
    }
  }, [attachment, draft, editing, refreshNow, replyTo, scrollToBottom, sending, threadId]);

  const remove = useCallback(
    async (message: ChatMessage) => {
      if (threadId === null) return;

      try {
        await deleteMessage(threadId, message.id);
        // Mise à jour optimiste : le serveur a déjà validé, on masque sans
        // recharger tout l'historique.
        setMessages((current) =>
          current.map((item) =>
            item.id === message.id
              ? { ...item, body: '', deleted: true, attachment: null }
              : item,
          ),
        );

        if (editing?.id === message.id) {
          setEditing(null);
          setDraft('');
        }
      } catch (cause) {
        setSendError(cause instanceof Error ? cause.message : 'La suppression a échoué.');
      }
    },
    [editing, threadId],
  );

  const grouped = useMemo(() => messages, [messages]);

  // ── États vides / pas de sélection ────────────────────────────────────────
  if (thread === null) {
    return (
      <div className="grid h-full place-items-center rounded-2xl border border-dashed border-gray-200 bg-white/60 p-8 text-center">
        <div>
          <AlertCircle className="mx-auto mb-2 h-8 w-8 text-gray-300" aria-hidden />
          <p className="text-sm font-medium text-gray-700">Sélectionnez une conversation</p>
          <p className="mt-1 text-xs text-gray-400">
            Choisissez un fil à gauche, ou ouvrez-en un depuis un dossier.
          </p>
        </div>
      </div>
    );
  }

  return (
    <section className="flex h-full min-h-0 flex-col rounded-2xl border border-gray-200/70 bg-white/85 shadow-premium backdrop-blur-xl">
      {/* En-tête */}
      <header className="flex items-center gap-2 border-b border-gray-100 px-3 py-2.5">
        {onBack && (
          <button
            type="button"
            onClick={onBack}
            className="rounded-lg p-1.5 text-gray-600 hover:bg-gray-100 md:hidden"
            aria-label="Revenir aux conversations"
          >
            <ArrowLeft className="h-4 w-4" aria-hidden />
          </button>
        )}

        <div className="min-w-0 flex-1">
          <h2 className="truncate text-sm font-semibold text-gray-900">{thread.title}</h2>
          <p className="text-[11px] text-gray-400">
            {thread.kind === 'group' ? 'Conversation de groupe' : 'Conversation privée'}
          </p>
        </div>

        {pollingState === 'offline' && (
          <span className="flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-medium text-amber-900">
            <WifiOff className="h-3 w-3" aria-hidden />
            Hors ligne
          </span>
        )}
      </header>

      {/* Messages */}
      <div
        ref={scrollRef}
        className="min-h-0 flex-1 space-y-1 overflow-y-auto px-3 py-3"
        aria-live="polite"
        aria-busy={loading}
      >
        {loading && (
          <div className="flex items-center justify-center gap-2 py-10 text-xs text-gray-500">
            <Loader2 className="h-4 w-4 animate-spin" aria-hidden />
            Chargement des messages…
          </div>
        )}

        {!loading && error !== null && (
          <div className="mx-auto flex max-w-sm flex-col items-center gap-1 rounded-xl bg-red-50 p-4 text-center" role="alert">
            <AlertCircle className="h-5 w-5 text-red-600" aria-hidden />
            <p className="text-xs font-medium text-red-800">{error}</p>
          </div>
        )}

        {!loading && error === null && hasMore && (
          <div className="pb-2 text-center">
            <button
              type="button"
              onClick={() => void loadOlder()}
              disabled={loadingOlder}
              className="rounded-full border border-gray-200 px-3 py-1 text-[11px] font-medium text-gray-600 hover:bg-gray-50 disabled:opacity-50"
            >
              {loadingOlder ? 'Chargement…' : 'Messages plus anciens'}
            </button>
          </div>
        )}

        {!loading && error === null && grouped.length === 0 && (
          <div className="grid place-items-center py-12 text-center">
            <div>
              <MessageSquarePlaceholder />
              <p className="mt-2 text-xs font-medium text-gray-600">Aucun message</p>
              <p className="text-[11px] text-gray-400">Écrivez le premier message de cette conversation.</p>
            </div>
          </div>
        )}

        {grouped.map((message) => {
          const mine = currentUserId !== null && message.author_id === currentUserId;

          return (
            <article
              key={message.id}
              className={`group flex flex-col ${mine ? 'items-end' : 'items-start'}`}
            >
              {message.reply_to !== null && (
                <div
                  className={`mb-0.5 max-w-[75%] truncate rounded-lg border-l-2 border-gray-300 bg-gray-50 px-2 py-1 text-[10px] text-gray-500 ${
                    mine ? 'mr-1' : 'ml-1'
                  }`}
                >
                  <CornerUpLeft className="mr-1 inline h-2.5 w-2.5" aria-hidden />
                  {message.reply_to.deleted ? 'Message supprimé' : message.reply_to.body}
                </div>
              )}

              <div
                className={`max-w-[85%] rounded-2xl px-3 py-2 text-xs shadow-sm ${
                  mine
                    ? 'rounded-br-md bg-emerald-600 text-white'
                    : 'rounded-bl-md bg-gray-100 text-gray-900'
                }`}
              >
                {!mine && message.author !== null && (
                  <p className="mb-0.5 text-[10px] font-semibold opacity-70">{message.author.name}</p>
                )}

                {message.deleted ? (
                  <p className={`italic ${mine ? 'text-white/70' : 'text-gray-400'}`}>Message supprimé</p>
                ) : (
                  <>
                    {message.body !== '' && <p className="whitespace-pre-wrap break-words">{message.body}</p>}

                    {message.attachment !== null && (
                      <a
                        href={attachmentUrl(message.conversation_thread_id, message.id)}
                        target="_blank"
                        rel="noreferrer"
                        className={`mt-1.5 flex items-center gap-1.5 rounded-lg px-2 py-1 text-[11px] underline-offset-2 hover:underline ${
                          mine ? 'bg-emerald-700/40 text-white' : 'bg-white/70 text-gray-700'
                        }`}
                      >
                        <Paperclip className="h-3 w-3 shrink-0" aria-hidden />
                        <span className="truncate">{message.attachment.original_name ?? 'Pièce jointe'}</span>
                        <Download className="ml-auto h-3 w-3 shrink-0" aria-hidden />
                      </a>
                    )}
                  </>
                )}

                <p className={`mt-1 text-[10px] ${mine ? 'text-white/60' : 'text-gray-400'}`}>
                  {new Date(message.created_at).toLocaleTimeString(undefined, {
                    hour: '2-digit',
                    minute: '2-digit',
                  })}
                  {message.edited_at !== null && ' · modifié'}
                </p>
              </div>

              {/* Actions : auteur uniquement pour l'édition ; le serveur tranche. */}
              {!message.deleted && (
                <div className="mt-0.5 flex items-center gap-1 opacity-0 transition-opacity group-hover:opacity-100 focus-within:opacity-100">
                  <button
                    type="button"
                    onClick={() => setReplyTo(message)}
                    className="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700"
                    aria-label="Répondre à ce message"
                  >
                    <CornerUpLeft className="h-3 w-3" aria-hidden />
                  </button>
                  {mine && message.body !== '' && (
                    <button
                      type="button"
                      onClick={() => {
                        setEditing(message);
                        setDraft(message.body);
                      }}
                      className="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700"
                      aria-label="Modifier ce message"
                    >
                      <Pencil className="h-3 w-3" aria-hidden />
                    </button>
                  )}
                  {mine && (
                    <button
                      type="button"
                      onClick={() => void remove(message)}
                      className="rounded p-1 text-gray-400 hover:bg-red-50 hover:text-red-600"
                      aria-label="Supprimer ce message"
                    >
                      <Trash2 className="h-3 w-3" aria-hidden />
                    </button>
                  )}
                </div>
              )}
            </article>
          );
        })}

        <div ref={bottomRef} />
      </div>

      {/* Composition */}
      <div className="border-t border-gray-100 p-2.5">
        {replyTo !== null && (
          <div className="mb-1.5 flex items-center gap-2 rounded-lg bg-gray-50 px-2 py-1 text-[11px] text-gray-600">
            <CornerUpLeft className="h-3 w-3 shrink-0 text-gray-400" aria-hidden />
            <span className="min-w-0 flex-1 truncate">
              Réponse à : {replyTo.body !== '' ? replyTo.body : 'pièce jointe'}
            </span>
            <button
              type="button"
              onClick={() => setReplyTo(null)}
              className="rounded p-0.5 hover:bg-gray-200"
              aria-label="Annuler la réponse"
            >
              <X className="h-3 w-3" aria-hidden />
            </button>
          </div>
        )}

        {editing !== null && (
          <div className="mb-1.5 flex items-center gap-2 rounded-lg bg-amber-50 px-2 py-1 text-[11px] text-amber-900">
            <Pencil className="h-3 w-3 shrink-0" aria-hidden />
            <span className="flex-1">Modification d&apos;un message</span>
            <button
              type="button"
              onClick={() => {
                setEditing(null);
                setDraft('');
              }}
              className="rounded p-0.5 hover:bg-amber-100"
              aria-label="Annuler la modification"
            >
              <X className="h-3 w-3" aria-hidden />
            </button>
          </div>
        )}

        {attachment !== null && (
          <div className="mb-1.5 flex items-center gap-2 rounded-lg bg-emerald-50 px-2 py-1 text-[11px] text-emerald-900">
            <Paperclip className="h-3 w-3 shrink-0" aria-hidden />
            <span className="min-w-0 flex-1 truncate">{attachment.name}</span>
            <span className="shrink-0 text-[10px] text-emerald-700">
              {(attachment.size / 1024).toFixed(0)} Ko
            </span>
            <button
              type="button"
              onClick={() => setAttachment(null)}
              className="rounded p-0.5 hover:bg-emerald-100"
              aria-label="Retirer la pièce jointe"
            >
              <X className="h-3 w-3" aria-hidden />
            </button>
          </div>
        )}

        {sendError !== null && (
          <p className="mb-1.5 text-[11px] text-red-700" role="alert">
            {sendError}
          </p>
        )}

        <form
          className="flex items-end gap-1.5"
          onSubmit={(event) => {
            event.preventDefault();
            void submit();
          }}
        >
          <input
            ref={fileInputRef}
            type="file"
            className="hidden"
            onChange={(event) => {
              const file = event.target.files?.[0] ?? null;
              setAttachment(file);
            }}
          />

          <button
            type="button"
            onClick={() => fileInputRef.current?.click()}
            disabled={editing !== null}
            className="rounded-lg p-2 text-gray-500 hover:bg-gray-100 disabled:opacity-40"
            aria-label="Joindre un fichier"
          >
            <Paperclip className="h-4 w-4" aria-hidden />
          </button>

          <label className="min-w-0 flex-1">
            <span className="sr-only">Votre message</span>
            <textarea
              value={draft}
              onChange={(event) => setDraft(event.target.value)}
              onKeyDown={(event) => {
                // Entrée envoie, Maj+Entrée passe à la ligne — comportement
                // attendu d'une messagerie, y compris sur mobile.
                if (event.key === 'Enter' && !event.shiftKey && !event.nativeEvent.isComposing) {
                  event.preventDefault();
                  void submit();
                }
              }}
              rows={1}
              placeholder={editing !== null ? 'Modifier le message…' : 'Écrire un message…'}
              className="max-h-32 w-full resize-none rounded-xl border border-gray-200 bg-white/80 px-3 py-2 text-xs text-gray-900 placeholder:text-gray-400 focus:border-emerald-400 focus:outline-none focus:ring-1 focus:ring-emerald-400"
            />
          </label>

          <button
            type="submit"
            disabled={sending || (draft.trim() === '' && attachment === null)}
            className="rounded-xl bg-emerald-600 p-2 text-white transition-colors hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-40"
            aria-label={editing !== null ? 'Enregistrer la modification' : 'Envoyer le message'}
          >
            {sending ? <Loader2 className="h-4 w-4 animate-spin" aria-hidden /> : <Send className="h-4 w-4" aria-hidden />}
          </button>
        </form>
      </div>
    </section>
  );
}

function MessageSquarePlaceholder() {
  return (
    <div className="mx-auto grid h-10 w-10 place-items-center rounded-full bg-gray-100">
      <Paperclip className="h-4 w-4 text-gray-400" aria-hidden />
    </div>
  );
}
