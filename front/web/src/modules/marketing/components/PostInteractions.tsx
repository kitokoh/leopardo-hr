'use client';

import { useCallback, useEffect, useState } from 'react';
import { MessageCircle, Send, Sparkles, RefreshCw } from 'lucide-react';
import { ApiError, apiFetch } from '@/lib/api-client';
import { t } from '@/lib/i18n/locale-catalog';
import { getPreferredLocale } from '@/lib/i18n';

export type SocialComment = {
  platform: string;
  comment: string;
  userName?: string;
  created?: string;
};

type CommentsPayload = {
  data?: {
    social_post_id: number;
    provider_post_ref: string;
    comments?: unknown;
  };
};

type SuggestReplyPayload = {
  data?: {
    suggestion?: string;
  };
};

type PostInteractionsProps = {
  postId: number;
};

/**
 * Aplati le payload agrégateur (commentaires groupés par plateforme) en une
 * liste plate — exporté pour les tests. Données tierces : texte brut, jamais
 * interprété.
 */
export function normalizeComments(raw: unknown): SocialComment[] {
  const result: SocialComment[] = [];
  if (!raw || typeof raw !== 'object' || Array.isArray(raw)) {
    return result;
  }
  for (const [platform, entries] of Object.entries(raw as Record<string, unknown>)) {
    if (!Array.isArray(entries)) {
      continue;
    }
    for (const entry of entries) {
      if (!entry || typeof entry !== 'object') {
        continue;
      }
      const record = entry as Record<string, unknown>;
      const text = typeof record.comment === 'string'
        ? record.comment
        : typeof record.text === 'string'
          ? record.text
          : null;
      if (!text) {
        continue;
      }
      result.push({
        platform,
        comment: text,
        userName: typeof record.userName === 'string' ? record.userName : undefined,
        created: typeof record.created === 'string' ? record.created : undefined,
      });
    }
  }
  return result;
}

/**
 * Module Marketing — Issue #7755 (interactions sociales, backend #7754).
 *
 * Panneau des commentaires reçus sur un post publié : lecture (via
 * l'agrégateur), réponse manuelle, et « Suggérer une réponse (IA) » qui
 * pré-remplit le champ — l'humain valide TOUJOURS avant l'envoi.
 * Les commentaires viennent de tiers : affichés comme texte brut, jamais
 * interprétés.
 */
export function PostInteractions({ postId }: PostInteractionsProps) {
  const [comments, setComments] = useState<SocialComment[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [reply, setReply] = useState('');
  const [sending, setSending] = useState(false);
  const [suggesting, setSuggesting] = useState(false);
  const [sent, setSent] = useState(false);
  const locale = getPreferredLocale();

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await apiFetch(`/marketing/social-posts/${postId}/comments`);
      const payload = (await res.json()) as CommentsPayload;
      setComments(normalizeComments(payload.data?.comments));
    } catch (err) {
      setError(err instanceof ApiError ? err.message : t(locale, 'marketing.commentsLoadError'));
    } finally {
      setLoading(false);
    }
  }, [postId, locale]);

  useEffect(() => {
    void load();
  }, [load]);

  const handleSuggest = async (comment: SocialComment) => {
    setSuggesting(true);
    setError(null);
    try {
      const res = await apiFetch(`/marketing/social-posts/${postId}/comments/suggest-reply`, {
        method: 'POST',
        body: JSON.stringify({ comment: comment.comment, platform: comment.platform }),
      });
      const payload = (await res.json()) as SuggestReplyPayload;
      if (payload.data?.suggestion) {
        setReply(payload.data.suggestion);
      }
    } catch (err) {
      setError(err instanceof ApiError ? err.message : t(locale, 'marketing.aiSuggestUnavailable'));
    } finally {
      setSuggesting(false);
    }
  };

  const handleReply = async () => {
    if (!reply.trim()) {
      return;
    }
    setSending(true);
    setError(null);
    setSent(false);
    try {
      await apiFetch(`/marketing/social-posts/${postId}/comments/reply`, {
        method: 'POST',
        body: JSON.stringify({ comment: reply.trim() }),
      });
      setReply('');
      setSent(true);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : t(locale, 'marketing.replySendError'));
    } finally {
      setSending(false);
    }
  };

  return (
    <div data-testid="post-interactions" className="mt-3 rounded-2xl border border-app-border bg-slate-50/60 p-4">
      <div className="mb-3 flex items-center justify-between">
        <p className="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-slate-600">
          <MessageCircle className="h-4 w-4" /> {t(locale, 'marketing.web.interactions.title')}
        </p>
        <button
          type="button"
          onClick={() => void load()}
          disabled={loading}
          className="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-bold text-slate-500 transition hover:text-slate-800 disabled:opacity-50"
        >
          <RefreshCw className="h-3.5 w-3.5" /> {t(locale, 'marketing.web.interactions.refresh')}
        </button>
      </div>

      {error ? (
        <div className="mb-3 rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700">{error}</div>
      ) : null}
      {sent ? (
        <div data-testid="post-interactions-sent" className="mb-3 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-700">
          {t(locale, 'marketing.web.interactions.sent')}
        </div>
      ) : null}

      {loading ? (
        <p className="text-xs text-slate-500">{t(locale, 'marketing.web.interactions.loading')}</p>
      ) : comments.length === 0 ? (
        <p data-testid="post-interactions-empty" className="text-xs text-slate-500">{t(locale, 'marketing.web.interactions.empty')}</p>
      ) : (
        <ul data-testid="post-interactions-list" className="space-y-2">
          {comments.map((comment, index) => (
            <li key={`${comment.platform}-${index}`} className="rounded-xl bg-white px-3 py-2">
              <div className="flex items-center justify-between gap-2">
                <p className="text-[10px] font-bold uppercase tracking-wider text-slate-500">
                  {comment.platform}
                  {comment.userName ? ` · ${comment.userName}` : ''}
                </p>
                <button
                  type="button"
                  data-testid={`post-interactions-suggest-${index}`}
                  onClick={() => void handleSuggest(comment)}
                  disabled={suggesting}
                  className="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-[11px] font-bold text-violet-700 transition hover:bg-violet-50 disabled:opacity-50"
                >
                  <Sparkles className="h-3.5 w-3.5" />
                  {suggesting ? t(locale, 'marketing.web.interactions.suggesting') : t(locale, 'marketing.suggestReply')}
                </button>
              </div>
              <p className="mt-1 text-sm text-slate-800">{comment.comment}</p>
            </li>
          ))}
        </ul>
      )}

      <div className="mt-3 flex flex-col gap-2 md:flex-row md:items-end">
        <textarea
          data-testid="post-interactions-reply"
          placeholder={t(locale, 'marketing.replyPlaceholder')}
          value={reply}
          onChange={(e) => setReply(e.target.value)}
          rows={2}
          maxLength={2000}
          className="w-full rounded-xl border border-app-border bg-white px-3 py-2 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-emerald-500"
        />
        <button
          type="button"
          data-testid="post-interactions-send"
          onClick={() => void handleReply()}
          disabled={!reply.trim() || sending}
          className="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-emerald-700 disabled:opacity-50"
        >
          <Send className="h-4 w-4" /> {sending ? t(locale, 'marketing.web.interactions.sending') : t(locale, 'marketing.web.interactions.send')}
        </button>
      </div>
    </div>
  );
}
