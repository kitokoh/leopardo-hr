'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import Link from 'next/link';
import { ChevronLeft, ChevronRight, ListChecks, Plus, X } from 'lucide-react';
import { ApiError, apiFetch } from '@/lib/api-client';
import { ModulePageShell } from '@/components/module-page-shell';
import { PostEditor, type PostEditorSubmitPayload } from '@/modules/marketing/components/PostEditor';
import {
  STATUS_STYLES,
  calendarDateFor,
  dayKey,
  type SocialPost,
  type SocialPostsPayload,
} from '@/modules/marketing/types';

const WEEKDAY_LABELS = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];

function startOfMonth(date: Date): Date {
  return new Date(date.getFullYear(), date.getMonth(), 1);
}

function addMonths(date: Date, delta: number): Date {
  return new Date(date.getFullYear(), date.getMonth() + delta, 1);
}

/** Monday-first 6-week grid covering the full month, like most calendar UIs. */
function buildMonthGrid(monthStart: Date): Date[] {
  const jsWeekday = monthStart.getDay(); // 0 = Sunday
  const mondayOffset = (jsWeekday + 6) % 7;
  const gridStart = new Date(monthStart.getFullYear(), monthStart.getMonth(), 1 - mondayOffset);

  return Array.from({ length: 42 }, (_, i) => {
    const day = new Date(gridStart);
    day.setDate(gridStart.getDate() + i);
    return day;
  });
}

function toDatetimeLocalValue(date: Date): string {
  const pad = (n: number) => String(n).padStart(2, '0');
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(Math.max(date.getHours(), 9))}:00`;
}

/**
 * Module Marketing â€” Phase 4 (PA2-MKT-011).
 *
 * `/social` calendar page: month grid of scheduled/published/draft posts,
 * click a day to compose a new post pre-filled for that date via
 * `PostEditor`, click an existing post to see/publish/delete it.
 * Complements (does not replace) `/social-marketing`, which keeps the
 * account-connect flow and the flat chronological list + pagination.
 */
export default function SocialCalendarPage() {
  const [monthStart, setMonthStart] = useState(() => startOfMonth(new Date()));
  const [posts, setPosts] = useState<SocialPost[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [accountConnected, setAccountConnected] = useState<boolean | null>(null);

  const [composerDay, setComposerDay] = useState<Date | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState<string | null>(null);

  const [selectedPost, setSelectedPost] = useState<SocialPost | null>(null);
  const [postActionPending, setPostActionPending] = useState(false);

  const loadAccount = useCallback(async () => {
    try {
      await apiFetch('/marketing/social-account');
      setAccountConnected(true);
    } catch (err) {
      if (err instanceof ApiError && err.code === 'SOCIAL_ACCOUNT_NOT_FOUND') {
        setAccountConnected(false);
      } else {
        setAccountConnected(true);
      }
    }
  }, []);

  const loadPosts = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      // Marketing posts are not paginated by month server-side yet (no
      // `from`/`to` filter on the API), so we page through the API's own
      // pagination client-side and keep everything for the calendar grid.
      // Volumes for a single tenant's social calendar are small enough
      // (drafts + scheduled + recently published) that this stays cheap.
      let page = 1;
      let lastPage = 1;
      const all: SocialPost[] = [];
      do {
        const res = await apiFetch(`/marketing/social-posts?per_page=100&page=${page}`);
        const payload = (await res.json()) as SocialPostsPayload;
        all.push(...(Array.isArray(payload.data) ? payload.data : []));
        lastPage = payload.meta?.last_page ?? page;
        page += 1;
      } while (page <= lastPage && page <= 20); // hard safety cap
      setPosts(all);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Impossible de charger les publications.');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void loadAccount();
    void loadPosts();
  }, [loadAccount, loadPosts]);

  const postsByDay = useMemo(() => {
    const map = new Map<string, SocialPost[]>();
    for (const post of posts) {
      const date = calendarDateFor(post);
      if (!date) {
        continue;
      }
      const key = dayKey(date);
      const bucket = map.get(key) ?? [];
      bucket.push(post);
      map.set(key, bucket);
    }
    return map;
  }, [posts]);

  const grid = useMemo(() => buildMonthGrid(monthStart), [monthStart]);
  const today = dayKey(new Date());

  const monthLabel = monthStart.toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' });

  const handleCreatePost = async (payload: PostEditorSubmitPayload) => {
    setSubmitting(true);
    setSubmitError(null);
    try {
      await apiFetch('/marketing/social-posts', {
        method: 'POST',
        body: JSON.stringify({
          content: payload.content,
          target_platforms: payload.targetPlatforms,
          scheduled_at: payload.scheduledAt,
        }),
      });
      setComposerDay(null);
      await loadPosts();
    } catch (err) {
      setSubmitError(err instanceof ApiError ? err.message : 'Impossible de creer la publication.');
    } finally {
      setSubmitting(false);
    }
  };

  const handlePublishNow = async (post: SocialPost) => {
    setPostActionPending(true);
    try {
      await apiFetch(`/marketing/social-posts/${post.id}/publish`, {
        method: 'POST',
        body: JSON.stringify({ scheduled_at: null }),
      });
      setSelectedPost(null);
      await loadPosts();
    } finally {
      setPostActionPending(false);
    }
  };

  const handleDeletePost = async (post: SocialPost) => {
    setPostActionPending(true);
    try {
      await apiFetch(`/marketing/social-posts/${post.id}`, { method: 'DELETE' });
      setSelectedPost(null);
      setPosts((prev) => prev.filter((p) => p.id !== post.id));
    } finally {
      setPostActionPending(false);
    }
  };

  return (
    <ModulePageShell
      title="Calendrier Marketing"
      subtitle="Visualisez vos publications planifiees, publiees et en brouillon sur un calendrier mensuel, et creez une nouvelle publication directement depuis un jour."
      accentClassName="bg-gradient-to-br from-brand-500/10 via-white to-white"
    >
      {accountConnected === false ? (
        <div className="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
          Aucun compte social connecte. Rendez-vous sur{' '}
          <Link href="/social-marketing" className="font-bold underline">
            la page Liste &amp; compte
          </Link>{' '}
          pour connecter votre compte avant de planifier des publications.
        </div>
      ) : null}

      {error ? (
        <div className="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{error}</div>
      ) : null}

      <section className="rounded-3xl border border-app-border bg-white shadow-sm">
        <div className="flex items-center justify-between border-b border-app-border px-6 py-4">
          <div className="flex items-center gap-3">
            <button
              type="button"
              aria-label="Mois precedent"
              onClick={() => setMonthStart((prev) => addMonths(prev, -1))}
              className="rounded-lg p-1.5 text-slate-500 transition hover:bg-slate-100"
            >
              <ChevronLeft className="h-4 w-4" />
            </button>
            <h2 className="min-w-40 text-center text-sm font-bold uppercase tracking-wider text-slate-800">
              {monthLabel}
            </h2>
            <button
              type="button"
              aria-label="Mois suivant"
              onClick={() => setMonthStart((prev) => addMonths(prev, 1))}
              className="rounded-lg p-1.5 text-slate-500 transition hover:bg-slate-100"
            >
              <ChevronRight className="h-4 w-4" />
            </button>
          </div>
          <div className="flex items-center gap-2">
            <button
              type="button"
              onClick={() => setMonthStart(startOfMonth(new Date()))}
              className="rounded-xl border border-app-border px-3 py-1.5 text-xs font-bold text-slate-600 transition hover:bg-transparent"
            >
              Aujourd&apos;hui
            </button>
            <Link
              href="/social-marketing"
              className="inline-flex items-center gap-2 rounded-xl border border-app-border px-3 py-1.5 text-xs font-bold text-slate-600 transition hover:bg-transparent"
            >
              <ListChecks className="h-3.5 w-3.5" /> Vue liste
            </Link>
            <button
              type="button"
              onClick={() => setComposerDay(new Date())}
              disabled={accountConnected === false}
              className="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-3 py-1.5 text-xs font-bold text-white transition hover:bg-brand-700 disabled:opacity-50"
            >
              <Plus className="h-3.5 w-3.5" /> Nouvelle publication
            </button>
          </div>
        </div>

        <div className="grid grid-cols-7 border-b border-app-border text-center">
          {WEEKDAY_LABELS.map((label) => (
            <div key={label} className="px-2 py-2 text-[11px] font-bold uppercase tracking-wider text-slate-400">
              {label}
            </div>
          ))}
        </div>

        {loading ? (
          <div className="px-6 py-10 text-center text-sm text-slate-500">Chargement du calendrier...</div>
        ) : (
          <div className="grid grid-cols-7">
            {grid.map((day) => {
              const key = dayKey(day);
              const inMonth = day.getMonth() === monthStart.getMonth();
              const dayPosts = postsByDay.get(key) ?? [];
              const isToday = key === today;

              return (
                <button
                  key={key}
                  type="button"
                  data-testid={`calendar-day-${key}`}
                  onClick={() => setComposerDay(day)}
                  disabled={accountConnected === false}
                  className={`min-h-24 border-b border-r border-app-border p-2 text-left transition hover:bg-transparent disabled:cursor-not-allowed disabled:hover:bg-transparent ${
                    inMonth ? 'bg-white' : 'bg-transparent/60'
                  }`}
                >
                  <span
                    className={`inline-flex h-6 w-6 items-center justify-center rounded-full text-xs font-bold ${
                      isToday ? 'bg-brand-600 text-white' : inMonth ? 'text-slate-700' : 'text-slate-400'
                    }`}
                  >
                    {day.getDate()}
                  </span>
                  <div className="mt-1 space-y-1">
                    {dayPosts.slice(0, 3).map((post) => {
                      const style = STATUS_STYLES[post.status] ?? STATUS_STYLES.draft;
                      return (
                        <span
                          key={post.id}
                          onClick={(e) => {
                            e.stopPropagation();
                            setSelectedPost(post);
                          }}
                          className={`block truncate rounded px-1.5 py-0.5 text-[10px] font-bold ${style.class}`}
                          title={post.content}
                        >
                          {post.content}
                        </span>
                      );
                    })}
                    {dayPosts.length > 3 ? (
                      <span className="block text-[10px] font-bold text-slate-400">+{dayPosts.length - 3} autres</span>
                    ) : null}
                  </div>
                </button>
              );
            })}
          </div>
        )}
      </section>

      {composerDay ? (
        <section className="rounded-3xl border border-app-border bg-white shadow-sm">
          <div className="flex items-center justify-between border-b border-app-border px-6 py-4">
            <h2 className="text-sm font-bold uppercase tracking-wider text-slate-800">
              Nouvelle publication â€” {composerDay.toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' })}
            </h2>
            <button
              type="button"
              onClick={() => setComposerDay(null)}
              className="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
              aria-label="Fermer"
            >
              <X className="h-4 w-4" />
            </button>
          </div>
          <div className="p-6">
            {submitError ? (
              <div className="mb-4 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{submitError}</div>
            ) : null}
            <PostEditor
              initialScheduledAt={toDatetimeLocalValue(composerDay)}
              submitting={submitting}
              onSubmit={handleCreatePost}
              onCancel={() => setComposerDay(null)}
            />
          </div>
        </section>
      ) : null}

      {selectedPost ? (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 p-4"
          role="dialog"
          aria-modal="true"
          onClick={() => setSelectedPost(null)}
        >
          <div
            className="w-full max-w-lg rounded-3xl border border-app-border bg-white p-6 shadow-2xl"
            onClick={(e) => e.stopPropagation()}
          >
            <div className="mb-4 flex items-start justify-between gap-4">
              <span className={`inline-flex rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-wider ${(STATUS_STYLES[selectedPost.status] ?? STATUS_STYLES.draft).class}`}>
                {(STATUS_STYLES[selectedPost.status] ?? STATUS_STYLES.draft).label}
              </span>
              <button
                type="button"
                onClick={() => setSelectedPost(null)}
                className="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
                aria-label="Fermer"
              >
                <X className="h-4 w-4" />
              </button>
            </div>
            <p className="whitespace-pre-wrap text-sm text-slate-900">{selectedPost.content}</p>
            <div className="mt-3 flex flex-wrap gap-1.5">
              {selectedPost.target_platforms.map((platform) => (
                <span key={platform} className="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-slate-600">
                  {platform}
                </span>
              ))}
            </div>
            {selectedPost.error_message ? (
              <p className="mt-3 text-xs text-red-600">{selectedPost.error_message}</p>
            ) : null}
            {selectedPost.status === 'draft' || selectedPost.status === 'scheduled' ? (
              <div className="mt-5 flex items-center gap-2">
                <button
                  type="button"
                  onClick={() => void handlePublishNow(selectedPost)}
                  disabled={postActionPending}
                  className="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-brand-700 disabled:opacity-50"
                >
                  Publier maintenant
                </button>
                <button
                  type="button"
                  onClick={() => void handleDeletePost(selectedPost)}
                  disabled={postActionPending}
                  className="inline-flex items-center gap-2 rounded-xl border border-app-border px-4 py-2 text-sm font-bold text-slate-700 transition hover:bg-transparent disabled:opacity-50"
                >
                  Supprimer
                </button>
              </div>
            ) : null}
          </div>
        </div>
      ) : null}
    </ModulePageShell>
  );
}

