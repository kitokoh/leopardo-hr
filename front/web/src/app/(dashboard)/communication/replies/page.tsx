'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import { AlertTriangle, Inbox, MailCheck, PencilLine, Send, X } from 'lucide-react';

import { apiFetch } from '@/lib/api-client';
import { ModulePageShell } from '@/components/module-page-shell';
import { getPreferredLocale, toIntlLocale } from '@/lib/i18n';
import {
  tc,
  type CommunicationIntegration,
  type CommunicationPendingReply,
} from '@/lib/communication';
import { CommunicationTabs } from '../communication-tabs';

type PageError = 'forbidden' | 'network' | null;

const STATUS_FILTERS = ['pending', 'drafted', 'sent', 'rejected', 'skipped', 'failed'] as const;

const STATUS_BADGES: Record<CommunicationPendingReply['status'], string> = {
  pending: 'bg-amber-100 text-amber-700',
  drafted: 'bg-cyan-100 text-cyan-700',
  sent: 'bg-emerald-100 text-emerald-700',
  rejected: 'bg-slate-200 text-slate-600',
  skipped: 'bg-slate-100 text-slate-500',
  failed: 'bg-red-100 text-red-700',
};

/**
 * BC-29 COMMUNICATION — R6 (#7691) : file de confirmations des réponses
 * assistées (R5, #7690). Le propriétaire relit chaque proposition, peut
 * l'éditer, puis l'approuve (SEUL chemin d'envoi du mode confirm) ou la
 * rejette. Gère les gardes serveur : 422 REPLY_BLOCKED (+ reason), 429
 * rate-limit, 502 REPLY_SEND_FAILED.
 */
export default function CommunicationRepliesPage() {
  const locale = getPreferredLocale();
  const intlLocale = toIntlLocale(locale);

  const [replies, setReplies] = useState<CommunicationPendingReply[]>([]);
  const [integrations, setIntegrations] = useState<CommunicationIntegration[]>([]);
  const [statusFilter, setStatusFilter] = useState<(typeof STATUS_FILTERS)[number]>('pending');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<PageError>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);

  const [editingId, setEditingId] = useState<string | null>(null);
  const [editSubject, setEditSubject] = useState('');
  const [editBody, setEditBody] = useState('');
  const [busyId, setBusyId] = useState<string | null>(null);

  const mailboxByIntegration = useMemo(() => {
    const map = new Map<string, string>();
    for (const integration of integrations) {
      map.set(integration.id, integration.email ?? integration.provider);
    }

    return map;
  }, [integrations]);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const [repliesRes, integrationsRes] = await Promise.all([
        apiFetch(`/communication/pending-replies?status=${statusFilter}&per_page=50`),
        apiFetch('/communication/integrations'),
      ]);
      if (repliesRes.status === 403 || repliesRes.status === 404) {
        setError('forbidden');

        return;
      }
      const repliesBody = (await repliesRes.json()) as { data: CommunicationPendingReply[] };
      const integrationsBody = integrationsRes.ok
        ? ((await integrationsRes.json()) as { data: CommunicationIntegration[] })
        : { data: [] };
      setReplies(repliesBody.data ?? []);
      setIntegrations(integrationsBody.data ?? []);
    } catch {
      setError('network');
    } finally {
      setLoading(false);
    }
  }, [statusFilter]);

  useEffect(() => {
    void load();
  }, [load]);

  const startEdit = useCallback((reply: CommunicationPendingReply) => {
    setEditingId(reply.id);
    setEditSubject(reply.subject ?? '');
    setEditBody(reply.body ?? '');
  }, []);

  const saveEdit = useCallback(
    async (replyId: string) => {
      setBusyId(replyId);
      setActionError(null);
      try {
        const res = await apiFetch(`/communication/pending-replies/${replyId}`, {
          method: 'PATCH',
          body: JSON.stringify({ subject: editSubject, body: editBody }),
        });
        if (!res.ok) {
          setActionError(tc(locale, 'replies.saveError'));

          return;
        }
        const body = (await res.json()) as { data: CommunicationPendingReply };
        setReplies((current) =>
          current.map((reply) => (reply.id === replyId ? body.data : reply)),
        );
        setEditingId(null);
        setNotice(tc(locale, 'replies.saved'));
      } catch {
        setActionError(tc(locale, 'replies.saveError'));
      } finally {
        setBusyId(null);
      }
    },
    [editBody, editSubject, locale],
  );

  const decide = useCallback(
    async (replyId: string, decision: 'approve' | 'reject') => {
      setBusyId(replyId);
      setActionError(null);
      setNotice(null);
      try {
        const res = await apiFetch(`/communication/pending-replies/${replyId}/${decision}`, {
          method: 'POST',
        });
        if (res.status === 429) {
          setActionError(tc(locale, 'replies.rateLimited'));

          return;
        }
        if (res.status === 502) {
          setActionError(tc(locale, 'replies.sendFailed'));

          return;
        }
        if (res.status === 422) {
          const body = (await res.json()) as { reason?: string; message?: string };
          setActionError(tc(locale, 'replies.blocked', { reason: body.reason ?? body.message ?? '' }));
          await load();

          return;
        }
        if (!res.ok) {
          setActionError(tc(locale, 'replies.actionError'));

          return;
        }
        setNotice(
          tc(locale, decision === 'approve' ? 'replies.approveSuccess' : 'replies.rejectSuccess'),
        );
        await load();
      } catch {
        setActionError(tc(locale, 'replies.actionError'));
      } finally {
        setBusyId(null);
      }
    },
    [locale, load],
  );

  const formatDate = useCallback(
    (value: string | null) =>
      value
        ? new Intl.DateTimeFormat(intlLocale, { dateStyle: 'medium', timeStyle: 'short' }).format(
            new Date(value),
          )
        : '—',
    [intlLocale],
  );

  return (
    <ModulePageShell
      title={tc(locale, 'replies.title')}
      subtitle={tc(locale, 'replies.subtitle')}
      icon={MailCheck}
      accentClassName="from-amber-500/10 via-white/40 to-cyan-500/10"
    >
      <CommunicationTabs />

      <div className="rounded-3xl border border-white/20 bg-white/70 p-6 shadow-premium backdrop-blur-xl">
        <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
          <label className="flex items-center gap-2 text-sm text-slate-600">
            {tc(locale, 'replies.filterLabel')}
            <select
              value={statusFilter}
              onChange={(event) =>
                setStatusFilter(event.target.value as (typeof STATUS_FILTERS)[number])
              }
              className="rounded-xl border-slate-200 text-sm focus:border-cyan-500 focus:ring-cyan-500"
            >
              {STATUS_FILTERS.map((value) => (
                <option key={value} value={value}>
                  {tc(locale, `replies.status.${value}`)}
                </option>
              ))}
            </select>
          </label>
          <button
            type="button"
            onClick={() => void load()}
            className="rounded-full border border-slate-200 px-4 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50"
          >
            {tc(locale, 'common.refresh')}
          </button>
        </div>

        {notice && (
          <div className="mb-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
            {notice}
          </div>
        )}
        {actionError && (
          <div className="mb-4 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-600">
            {actionError}
          </div>
        )}

        {loading && (
          <div className="flex items-center justify-center gap-3 py-16 text-slate-400">
            <div className="h-5 w-5 animate-spin rounded-full border-2 border-cyan-500 border-t-transparent" />
            <span className="text-sm">{tc(locale, 'common.loading')}</span>
          </div>
        )}

        {!loading && error === 'forbidden' && (
          <div className="flex items-center justify-center gap-3 py-16 text-amber-600">
            <AlertTriangle className="h-6 w-6" />
            <p className="text-sm font-medium">{tc(locale, 'common.featureLocked')}</p>
          </div>
        )}

        {!loading && error === 'network' && (
          <div className="flex flex-col items-center gap-3 py-16 text-red-500">
            <AlertTriangle className="h-6 w-6" />
            <p className="text-sm font-medium">{tc(locale, 'common.error')}</p>
            <button
              type="button"
              onClick={() => void load()}
              className="rounded-full bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700"
            >
              {tc(locale, 'common.retry')}
            </button>
          </div>
        )}

        {!loading && !error && replies.length === 0 && (
          <div className="flex flex-col items-center gap-3 py-16 text-slate-400">
            <Inbox className="h-10 w-10" />
            <p className="text-sm font-medium">{tc(locale, 'replies.empty')}</p>
            <p className="text-xs">{tc(locale, 'replies.emptyHint')}</p>
          </div>
        )}

        {!loading && !error && replies.length > 0 && (
          <ul className="space-y-4">
            {replies.map((reply) => {
              const isEditing = editingId === reply.id;
              const busy = busyId === reply.id;

              return (
                <li key={reply.id} className="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
                  <div className="flex flex-wrap items-center justify-between gap-2">
                    <div className="flex flex-wrap items-center gap-2">
                      <span className={`rounded-full px-3 py-1 text-xs font-semibold ${STATUS_BADGES[reply.status]}`}>
                        {tc(locale, `replies.status.${reply.status}`)}
                      </span>
                      <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600">
                        {tc(locale, 'replies.modeLabel')} : {tc(locale, `replies.mode.${reply.mode}`)}
                      </span>
                      {typeof reply.ai_confidence === 'number' && (
                        <span className="text-xs text-slate-400">
                          {tc(locale, 'replies.confidence', { value: reply.ai_confidence })}
                        </span>
                      )}
                    </div>
                    <span className="text-xs text-slate-400">{formatDate(reply.created_at)}</span>
                  </div>

                  <dl className="mt-3 space-y-1 text-sm">
                    <div className="flex gap-2">
                      <dt className="font-semibold text-slate-500">{tc(locale, 'replies.to')} :</dt>
                      <dd className="text-slate-800">{reply.to_email}</dd>
                    </div>
                    <div className="flex gap-2">
                      <dt className="font-semibold text-slate-500">
                        {tc(locale, 'policies.mailbox')} :
                      </dt>
                      <dd className="text-slate-800">
                        {mailboxByIntegration.get(reply.integration_id) ?? '—'}
                      </dd>
                    </div>
                    {reply.skip_reason && (
                      <div className="flex gap-2">
                        <dt className="font-semibold text-slate-500">
                          {tc(locale, 'replies.skipReason')} :
                        </dt>
                        <dd className="text-slate-800">{reply.skip_reason}</dd>
                      </div>
                    )}
                  </dl>

                  {isEditing ? (
                    <div className="mt-4 space-y-3">
                      <label className="block text-sm">
                        <span className="mb-1 block font-semibold text-slate-600">
                          {tc(locale, 'replies.subject')}
                        </span>
                        <input
                          type="text"
                          value={editSubject}
                          maxLength={255}
                          onChange={(event) => setEditSubject(event.target.value)}
                          className="w-full rounded-xl border-slate-200 text-sm focus:border-cyan-500 focus:ring-cyan-500"
                        />
                      </label>
                      <label className="block text-sm">
                        <span className="mb-1 block font-semibold text-slate-600">
                          {tc(locale, 'replies.body')}
                        </span>
                        <textarea
                          value={editBody}
                          rows={6}
                          maxLength={10000}
                          onChange={(event) => setEditBody(event.target.value)}
                          className="w-full rounded-xl border-slate-200 text-sm focus:border-cyan-500 focus:ring-cyan-500"
                        />
                      </label>
                      <div className="flex gap-2">
                        <button
                          type="button"
                          onClick={() => void saveEdit(reply.id)}
                          disabled={busy}
                          className="rounded-full bg-cyan-600 px-4 py-1.5 text-xs font-bold text-white hover:bg-cyan-700 disabled:opacity-50"
                        >
                          {tc(locale, 'common.save')}
                        </button>
                        <button
                          type="button"
                          onClick={() => setEditingId(null)}
                          className="rounded-full border border-slate-200 px-4 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50"
                        >
                          {tc(locale, 'common.cancel')}
                        </button>
                      </div>
                    </div>
                  ) : (
                    <div className="mt-4 rounded-xl border border-slate-100 bg-slate-50 p-4">
                      <p className="text-sm font-semibold text-slate-700">
                        {reply.subject ?? tc(locale, 'threads.noSubject')}
                      </p>
                      <p className="mt-2 whitespace-pre-wrap text-sm leading-relaxed text-slate-600">
                        {reply.body}
                      </p>
                    </div>
                  )}

                  {reply.status === 'pending' && !isEditing && (
                    <div className="mt-4 flex flex-wrap gap-2">
                      <button
                        type="button"
                        onClick={() => void decide(reply.id, 'approve')}
                        disabled={busy}
                        className="inline-flex items-center gap-1.5 rounded-full bg-emerald-600 px-5 py-2 text-xs font-bold text-white hover:bg-emerald-700 disabled:opacity-50"
                      >
                        <Send className="h-3.5 w-3.5" />
                        {tc(locale, 'replies.approve')}
                      </button>
                      <button
                        type="button"
                        onClick={() => startEdit(reply)}
                        disabled={busy}
                        className="inline-flex items-center gap-1.5 rounded-full border border-cyan-200 px-5 py-2 text-xs font-semibold text-cyan-700 hover:bg-cyan-50 disabled:opacity-50"
                      >
                        <PencilLine className="h-3.5 w-3.5" />
                        {tc(locale, 'replies.edit')}
                      </button>
                      <button
                        type="button"
                        onClick={() => void decide(reply.id, 'reject')}
                        disabled={busy}
                        className="inline-flex items-center gap-1.5 rounded-full border border-red-200 px-5 py-2 text-xs font-semibold text-red-600 hover:bg-red-50 disabled:opacity-50"
                      >
                        <X className="h-3.5 w-3.5" />
                        {tc(locale, 'replies.reject')}
                      </button>
                    </div>
                  )}
                </li>
              );
            })}
          </ul>
        )}
      </div>
    </ModulePageShell>
  );
}
