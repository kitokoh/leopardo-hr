'use client';

/**
 * #8751 [BOS-CHAT] — liste des conversations.
 *
 * Réutilise le design system existant (`ModulePageShell`, tokens Tailwind du
 * projet) : aucun composant de charte parallèle. États couverts : chargement,
 * erreur, vide, hors-ligne, non-lus.
 */

import { AlertCircle, Loader2, MessageSquare, Search, Users, WifiOff } from 'lucide-react';
import { useMemo, useState } from 'react';

import type { ChatThread } from '@/lib/chat';

interface ConversationListProps {
  threads: ChatThread[];
  selectedId: number | null;
  loading: boolean;
  error: string | null;
  offline: boolean;
  onSelect: (threadId: number) => void;
}

function formatRelative(iso: string | null): string {
  if (!iso) return '';

  const date = new Date(iso);

  if (Number.isNaN(date.getTime())) return '';

  const diffMinutes = Math.floor((Date.now() - date.getTime()) / 60_000);

  if (diffMinutes < 1) return "à l'instant";
  if (diffMinutes < 60) return `il y a ${diffMinutes} min`;

  const diffHours = Math.floor(diffMinutes / 60);
  if (diffHours < 24) return `il y a ${diffHours} h`;

  const diffDays = Math.floor(diffHours / 24);
  if (diffDays === 1) return 'hier';

  return date.toLocaleDateString(undefined, { day: '2-digit', month: 'short' });
}

export function ConversationList({
  threads,
  selectedId,
  loading,
  error,
  offline,
  onSelect,
}: ConversationListProps) {
  const [query, setQuery] = useState('');

  const filtered = useMemo(() => {
    const needle = query.trim().toLowerCase();

    if (needle === '') return threads;

    return threads.filter((thread) => thread.title.toLowerCase().includes(needle));
  }, [threads, query]);

  const unreadCount = threads.filter((thread) => thread.unread).length;

  return (
    <aside className="flex h-full w-full flex-col rounded-2xl border border-gray-200/70 bg-white/80 shadow-premium backdrop-blur-xl md:w-80 md:shrink-0">
      <div className="border-b border-gray-100 p-3">
        <div className="mb-2 flex items-center justify-between">
          <h2 className="flex items-center gap-2 text-sm font-semibold text-gray-900">
            <MessageSquare className="h-4 w-4 text-emerald-600" aria-hidden />
            Conversations
          </h2>
          {unreadCount > 0 && (
            <span
              className="rounded-full bg-emerald-600 px-2 py-0.5 text-[11px] font-semibold text-white"
              aria-label={`${unreadCount} conversation${unreadCount > 1 ? 's' : ''} non lue${unreadCount > 1 ? 's' : ''}`}
            >
              {unreadCount > 99 ? '99+' : unreadCount}
            </span>
          )}
        </div>

        <label className="relative block">
          <span className="sr-only">Rechercher une conversation</span>
          <Search className="pointer-events-none absolute left-2.5 top-2 h-3.5 w-3.5 text-gray-400" aria-hidden />
          <input
            type="search"
            value={query}
            onChange={(event) => setQuery(event.target.value)}
            placeholder="Rechercher…"
            className="w-full rounded-lg border border-gray-200 bg-white/70 py-1.5 pl-8 pr-2 text-xs text-gray-900 placeholder:text-gray-400 focus:border-emerald-400 focus:outline-none focus:ring-1 focus:ring-emerald-400"
          />
        </label>
      </div>

      {offline && (
        <div
          className="flex items-center gap-2 border-b border-amber-200 bg-amber-50 px-3 py-2 text-[11px] text-amber-900"
          role="status"
        >
          <WifiOff className="h-3.5 w-3.5 shrink-0" aria-hidden />
          Hors ligne — reprise automatique dès le retour du réseau.
        </div>
      )}

      <div className="min-h-0 flex-1 overflow-y-auto">
        {loading && (
          <div className="flex items-center justify-center gap-2 p-6 text-xs text-gray-500">
            <Loader2 className="h-4 w-4 animate-spin" aria-hidden />
            Chargement des conversations…
          </div>
        )}

        {!loading && error !== null && (
          <div className="flex flex-col items-center gap-1 p-6 text-center text-xs text-red-700" role="alert">
            <AlertCircle className="h-5 w-5" aria-hidden />
            <span>{error}</span>
          </div>
        )}

        {!loading && error === null && filtered.length === 0 && (
          <div className="flex flex-col items-center gap-2 p-8 text-center">
            <MessageSquare className="h-8 w-8 text-gray-300" aria-hidden />
            <p className="text-xs font-medium text-gray-600">
              {threads.length === 0 ? 'Aucune conversation pour le moment.' : 'Aucun résultat.'}
            </p>
            <p className="text-[11px] text-gray-400">
              {threads.length === 0
                ? 'Ouvrez une conversation depuis un dossier ou un profil.'
                : 'Essayez un autre mot-clé.'}
            </p>
          </div>
        )}

        <ul className="divide-y divide-gray-100">
          {filtered.map((thread) => {
            const active = thread.id === selectedId;

            return (
              <li key={thread.id}>
                <button
                  type="button"
                  onClick={() => onSelect(thread.id)}
                  aria-current={active ? 'true' : undefined}
                  className={`flex w-full items-start gap-2.5 px-3 py-2.5 text-left transition-colors ${
                    active ? 'bg-emerald-50' : 'hover:bg-gray-50'
                  }`}
                >
                  <span
                    className={`mt-1 grid h-8 w-8 shrink-0 place-items-center rounded-full text-[11px] font-semibold ${
                      thread.kind === 'group'
                        ? 'bg-indigo-100 text-indigo-700'
                        : 'bg-emerald-100 text-emerald-700'
                    }`}
                    aria-hidden
                  >
                    {thread.kind === 'group' ? <Users className="h-4 w-4" /> : thread.title.slice(0, 2).toUpperCase()}
                  </span>

                  <span className="min-w-0 flex-1">
                    <span className="flex items-baseline justify-between gap-2">
                      <span
                        className={`truncate text-xs ${
                          thread.unread ? 'font-semibold text-gray-900' : 'font-medium text-gray-700'
                        }`}
                      >
                        {thread.title}
                      </span>
                      <span className="shrink-0 text-[10px] text-gray-400">
                        {formatRelative(thread.last_message_at)}
                      </span>
                    </span>

                    <span className="mt-0.5 flex items-center gap-1.5">
                      {thread.kind === 'group' && (
                        <span className="rounded bg-gray-100 px-1 py-px text-[10px] text-gray-600">Groupe</span>
                      )}
                      {thread.unread && (
                        <>
                          <span className="h-1.5 w-1.5 rounded-full bg-emerald-600" aria-hidden />
                          <span className="text-[10px] font-medium text-emerald-700">Non lu</span>
                        </>
                      )}
                    </span>
                  </span>
                </button>
              </li>
            );
          })}
        </ul>
      </div>
    </aside>
  );
}
