'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  AlertTriangle,
  ChevronDown,
  ChevronUp,
  Inbox,
  Mail,
  MailPlus,
  Paperclip,
  ShieldCheck,
  Sparkles,
  Trash2,
  UserPlus,
} from 'lucide-react';

import { apiFetch } from '@/lib/api-client';
import { ModulePageShell } from '@/components/module-page-shell';
import { getPreferredLocale, toIntlLocale } from '@/lib/i18n';
import {
  categoryLabel,
  integrationHasSendScope,
  tc,
  type CommunicationCategory,
  type CommunicationContactProposal,
  type CommunicationIntegration,
  type CommunicationMessage,
  type CommunicationModuleStatus,
  type CommunicationThread,
} from '@/lib/communication';
import { CommunicationTabs } from './communication-tabs';

type PageError = 'forbidden' | 'network' | null;

/**
 * BC-29 COMMUNICATION — R6 (#7691) : écran « Boîte connectée » de l'espace
 * client. Connexion Google (OAuth serveur R1), fils/messages synchronisés
 * (R2) classés par catégorie (R3) et propositions de contacts CRM à
 * confirmer (R3). Consomme exclusivement l'API tenant `/communication/*`.
 */
export default function CommunicationInboxPage() {
  const locale = getPreferredLocale();
  const intlLocale = toIntlLocale(locale);

  const [status, setStatus] = useState<CommunicationModuleStatus | null>(null);
  const [integrations, setIntegrations] = useState<CommunicationIntegration[]>([]);
  const [categories, setCategories] = useState<CommunicationCategory[]>([]);
  const [proposals, setProposals] = useState<CommunicationContactProposal[]>([]);
  const [threads, setThreads] = useState<CommunicationThread[]>([]);
  const [selectedIntegration, setSelectedIntegration] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<PageError>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);

  const [withSend, setWithSend] = useState(false);
  const [connecting, setConnecting] = useState(false);
  const [revoking, setRevoking] = useState<string | null>(null);

  const [openThread, setOpenThread] = useState<string | null>(null);
  const [threadMessages, setThreadMessages] = useState<Record<string, CommunicationMessage[]>>({});
  const [threadLoading, setThreadLoading] = useState<string | null>(null);
  const [threadError, setThreadError] = useState<string | null>(null);
  const [proposalBusy, setProposalBusy] = useState<string | null>(null);

  const activeIntegrations = useMemo(
    () => integrations.filter((integration) => integration.status === 'active'),
    [integrations],
  );

  const loadAll = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const [statusRes, integrationsRes, categoriesRes, proposalsRes] = await Promise.all([
        apiFetch('/communication/status'),
        apiFetch('/communication/integrations'),
        apiFetch('/communication/categories'),
        apiFetch('/communication/contact-proposals?status=proposed&per_page=50'),
      ]);
      if ([statusRes, integrationsRes].some((res) => res.status === 403 || res.status === 404)) {
        setError('forbidden');

        return;
      }
      const statusBody = (await statusRes.json()) as { data: CommunicationModuleStatus };
      const integrationsBody = (await integrationsRes.json()) as { data: CommunicationIntegration[] };
      const categoriesBody = categoriesRes.ok
        ? ((await categoriesRes.json()) as { data: CommunicationCategory[] })
        : { data: [] };
      const proposalsBody = proposalsRes.ok
        ? ((await proposalsRes.json()) as { data: CommunicationContactProposal[] })
        : { data: [] };

      setStatus(statusBody.data);
      setIntegrations(integrationsBody.data ?? []);
      setCategories(categoriesBody.data ?? []);
      setProposals(proposalsBody.data ?? []);

      const firstActive = (integrationsBody.data ?? []).find(
        (integration) => integration.status === 'active',
      );
      setSelectedIntegration((current) => current ?? firstActive?.id ?? null);
    } catch {
      setError('network');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void loadAll();
  }, [loadAll]);

  const loadThreads = useCallback(async (integrationId: string) => {
    try {
      const res = await apiFetch(
        `/communication/threads?integration=${encodeURIComponent(integrationId)}&per_page=50`,
      );
      if (!res.ok) {
        setThreads([]);

        return;
      }
      const body = (await res.json()) as { data: CommunicationThread[] };
      setThreads(body.data ?? []);
    } catch {
      setThreads([]);
    }
  }, []);

  useEffect(() => {
    if (selectedIntegration) {
      void loadThreads(selectedIntegration);
    } else {
      setThreads([]);
    }
  }, [selectedIntegration, loadThreads]);

  const connectGoogle = useCallback(async () => {
    setConnecting(true);
    setActionError(null);
    try {
      const res = await apiFetch('/communication/integrations/google', {
        method: 'POST',
        body: JSON.stringify({ with_send: withSend }),
      });
      if (!res.ok) {
        setActionError(tc(locale, 'mailbox.connectError'));

        return;
      }
      const body = (await res.json()) as { data: { authorization_url: string } };
      window.location.assign(body.data.authorization_url);
    } catch {
      setActionError(tc(locale, 'mailbox.connectError'));
      setConnecting(false);
    }
  }, [locale, withSend]);

  const revokeIntegration = useCallback(
    async (integrationId: string) => {
      if (!window.confirm(tc(locale, 'mailbox.revokeConfirm'))) {
        return;
      }
      setRevoking(integrationId);
      setActionError(null);
      try {
        const res = await apiFetch(`/communication/integrations/${integrationId}`, {
          method: 'DELETE',
        });
        if (!res.ok) {
          setActionError(tc(locale, 'mailbox.revokeError'));

          return;
        }
        setNotice(tc(locale, 'mailbox.revokeSuccess'));
        setSelectedIntegration(null);
        await loadAll();
      } catch {
        setActionError(tc(locale, 'mailbox.revokeError'));
      } finally {
        setRevoking(null);
      }
    },
    [locale, loadAll],
  );

  const toggleThread = useCallback(
    async (threadId: string) => {
      setThreadError(null);
      if (openThread === threadId) {
        setOpenThread(null);

        return;
      }
      setOpenThread(threadId);
      if (threadMessages[threadId]) {
        return;
      }
      setThreadLoading(threadId);
      try {
        const res = await apiFetch(`/communication/threads/${threadId}/messages`);
        if (!res.ok) {
          setThreadError(tc(locale, 'threads.messagesError'));

          return;
        }
        const body = (await res.json()) as {
          data: { messages: CommunicationMessage[] };
        };
        setThreadMessages((current) => ({ ...current, [threadId]: body.data.messages ?? [] }));
      } catch {
        setThreadError(tc(locale, 'threads.messagesError'));
      } finally {
        setThreadLoading(null);
      }
    },
    [locale, openThread, threadMessages],
  );

  const reclassify = useCallback(
    async (messageId: string) => {
      setActionError(null);
      try {
        const res = await apiFetch(`/communication/messages/${messageId}/classify`, {
          method: 'POST',
        });
        if (!res.ok) {
          setActionError(tc(locale, 'messages.reclassifyError'));

          return;
        }
        setNotice(tc(locale, 'messages.reclassifyQueued'));
      } catch {
        setActionError(tc(locale, 'messages.reclassifyError'));
      }
    },
    [locale],
  );

  const decideProposal = useCallback(
    async (proposalId: string, decision: 'accept' | 'dismiss') => {
      setProposalBusy(proposalId);
      setActionError(null);
      try {
        const res = await apiFetch(`/communication/contact-proposals/${proposalId}/${decision}`, {
          method: 'POST',
        });
        if (!res.ok) {
          setActionError(tc(locale, 'proposals.actionError'));

          return;
        }
        setNotice(tc(locale, decision === 'accept' ? 'proposals.accepted' : 'proposals.dismissed'));
        setProposals((current) => current.filter((proposal) => proposal.id !== proposalId));
      } catch {
        setActionError(tc(locale, 'proposals.actionError'));
      } finally {
        setProposalBusy(null);
      }
    },
    [locale],
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
      title={tc(locale, 'moduleTitle')}
      subtitle={tc(locale, 'moduleSubtitle')}
      icon={Mail}
      accentClassName="from-cyan-500/10 via-white/40 to-emerald-500/10"
    >
      <CommunicationTabs />

      {loading && (
        <div className="flex items-center justify-center gap-3 py-16 text-slate-400">
          <div className="h-5 w-5 animate-spin rounded-full border-2 border-cyan-500 border-t-transparent" />
          <span className="text-sm">{tc(locale, 'common.loading')}</span>
        </div>
      )}

      {!loading && error === 'forbidden' && (
        <div className="flex items-center justify-center gap-3 rounded-3xl border border-amber-200 bg-amber-50 py-16 text-amber-700">
          <AlertTriangle className="h-6 w-6" />
          <p className="text-sm font-medium">{tc(locale, 'common.featureLocked')}</p>
        </div>
      )}

      {!loading && error === 'network' && (
        <div className="flex flex-col items-center gap-3 rounded-3xl border border-red-100 bg-red-50 py-16 text-red-600">
          <AlertTriangle className="h-6 w-6" />
          <p className="text-sm font-medium">{tc(locale, 'common.error')}</p>
          <button
            type="button"
            onClick={() => void loadAll()}
            className="rounded-full bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700"
          >
            {tc(locale, 'common.retry')}
          </button>
        </div>
      )}

      {!loading && !error && (
        <div className="space-y-6">
          {notice && (
            <div className="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
              {notice}
            </div>
          )}
          {actionError && (
            <div className="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-600">
              {actionError}
            </div>
          )}

          {/* Boîte connectée : état + connexion + révocation */}
          <section className="rounded-3xl border border-white/20 bg-white/70 p-6 shadow-premium backdrop-blur-xl">
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
              <h2 className="flex items-center gap-2 text-lg font-bold text-slate-900">
                <ShieldCheck className="h-5 w-5 text-cyan-600" />
                {tc(locale, 'mailbox.title')}
              </h2>
              {status && (
                <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-500">
                  {tc(locale, 'common.stage', { stage: status.stage })}
                </span>
              )}
            </div>
            <p className="mb-5 text-sm text-slate-500">{tc(locale, 'mailbox.subtitle')}</p>

            {integrations.length === 0 ? (
              <div className="flex flex-col items-center gap-2 rounded-2xl border border-dashed border-slate-200 py-10 text-slate-400">
                <Inbox className="h-8 w-8" />
                <p className="text-sm font-medium">{tc(locale, 'mailbox.empty')}</p>
                <p className="text-xs">{tc(locale, 'mailbox.emptyHint')}</p>
              </div>
            ) : (
              <ul className="space-y-3">
                {integrations.map((integration) => (
                  <li
                    key={integration.id}
                    className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-100 bg-white p-4 shadow-sm"
                  >
                    <div>
                      <p className="font-semibold text-slate-900">{integration.email ?? integration.provider}</p>
                      <p className="text-xs text-slate-500">
                        {tc(locale, 'mailbox.connectedAt')} {formatDate(integration.connected_at)}
                      </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                      <span
                        className={`rounded-full px-3 py-1 text-xs font-semibold ${
                          integration.status === 'active'
                            ? 'bg-emerald-100 text-emerald-700'
                            : integration.status === 'revoked'
                              ? 'bg-slate-100 text-slate-500'
                              : 'bg-red-100 text-red-700'
                        }`}
                      >
                        {tc(locale, `mailbox.status.${integration.status}`)}
                      </span>
                      <span className="rounded-full bg-cyan-50 px-3 py-1 text-xs font-semibold text-cyan-700">
                        {integrationHasSendScope(integration)
                          ? tc(locale, 'mailbox.scopeSend')
                          : tc(locale, 'mailbox.readOnly')}
                      </span>
                      {integration.status === 'active' && (
                        <button
                          type="button"
                          onClick={() => void revokeIntegration(integration.id)}
                          disabled={revoking === integration.id}
                          className="inline-flex items-center gap-1 rounded-full border border-red-200 px-3 py-1 text-xs font-semibold text-red-600 hover:bg-red-50 disabled:opacity-50"
                        >
                          <Trash2 className="h-3.5 w-3.5" />
                          {tc(locale, 'mailbox.revoke')}
                        </button>
                      )}
                    </div>
                  </li>
                ))}
              </ul>
            )}

            <div className="mt-5 flex flex-wrap items-center gap-4 rounded-2xl border border-slate-100 bg-slate-50 p-4">
              <label className="flex items-center gap-2 text-sm text-slate-600">
                <input
                  type="checkbox"
                  checked={withSend}
                  onChange={(event) => setWithSend(event.target.checked)}
                  className="h-4 w-4 rounded border-slate-300 text-cyan-600 focus:ring-cyan-500"
                />
                {tc(locale, 'mailbox.connectWithSend')}
              </label>
              <button
                type="button"
                onClick={() => void connectGoogle()}
                disabled={connecting}
                className="inline-flex items-center gap-2 rounded-full bg-gradient-to-r from-cyan-600 to-emerald-600 px-5 py-2.5 text-sm font-bold text-white shadow-md transition hover:opacity-90 disabled:opacity-50"
              >
                <MailPlus className="h-4 w-4" />
                {connecting ? tc(locale, 'mailbox.connecting') : tc(locale, 'mailbox.connect')}
              </button>
              <p className="w-full text-xs text-slate-400">
                {tc(locale, 'mailbox.connectHint')} {tc(locale, 'mailbox.withSendHint')}
              </p>
            </div>
          </section>

          {/* Propositions de contacts CRM (R3) */}
          <section className="rounded-3xl border border-white/20 bg-white/70 p-6 shadow-premium backdrop-blur-xl">
            <h2 className="mb-1 flex items-center gap-2 text-lg font-bold text-slate-900">
              <UserPlus className="h-5 w-5 text-emerald-600" />
              {tc(locale, 'proposals.title')}
            </h2>
            <p className="mb-4 text-sm text-slate-500">{tc(locale, 'proposals.subtitle')}</p>
            {proposals.length === 0 ? (
              <p className="rounded-2xl border border-dashed border-slate-200 py-6 text-center text-sm text-slate-400">
                {tc(locale, 'proposals.empty')}
              </p>
            ) : (
              <ul className="grid gap-3 sm:grid-cols-2">
                {proposals.map((proposal) => (
                  <li
                    key={proposal.id}
                    className="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm"
                  >
                    <p className="font-semibold text-slate-900">{proposal.suggested_name ?? proposal.email}</p>
                    <p className="text-xs text-slate-500">{proposal.email}</p>
                    <p className="mt-1 text-xs text-slate-400">
                      {tc(locale, 'proposals.seenIn', { count: proposal.message_count })}
                    </p>
                    <div className="mt-3 flex gap-2">
                      <button
                        type="button"
                        onClick={() => void decideProposal(proposal.id, 'accept')}
                        disabled={proposalBusy === proposal.id}
                        className="rounded-full bg-emerald-600 px-4 py-1.5 text-xs font-bold text-white hover:bg-emerald-700 disabled:opacity-50"
                      >
                        {tc(locale, 'proposals.accept')}
                      </button>
                      <button
                        type="button"
                        onClick={() => void decideProposal(proposal.id, 'dismiss')}
                        disabled={proposalBusy === proposal.id}
                        className="rounded-full border border-slate-200 px-4 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50 disabled:opacity-50"
                      >
                        {tc(locale, 'proposals.dismiss')}
                      </button>
                    </div>
                  </li>
                ))}
              </ul>
            )}
          </section>

          {/* Fils synchronisés + messages classés (R2/R3) */}
          <section className="rounded-3xl border border-white/20 bg-white/70 p-6 shadow-premium backdrop-blur-xl">
            <div className="mb-1 flex flex-wrap items-center justify-between gap-3">
              <h2 className="flex items-center gap-2 text-lg font-bold text-slate-900">
                <Sparkles className="h-5 w-5 text-cyan-600" />
                {tc(locale, 'threads.title')}
              </h2>
              {activeIntegrations.length > 1 && (
                <select
                  value={selectedIntegration ?? ''}
                  onChange={(event) => setSelectedIntegration(event.target.value || null)}
                  aria-label={tc(locale, 'policies.mailbox')}
                  className="rounded-xl border-slate-200 text-sm focus:border-cyan-500 focus:ring-cyan-500"
                >
                  {activeIntegrations.map((integration) => (
                    <option key={integration.id} value={integration.id}>
                      {integration.email ?? integration.provider}
                    </option>
                  ))}
                </select>
              )}
            </div>
            <p className="mb-4 text-sm text-slate-500">{tc(locale, 'threads.subtitle')}</p>

            {threads.length === 0 ? (
              <div className="flex flex-col items-center gap-2 rounded-2xl border border-dashed border-slate-200 py-10 text-slate-400">
                <Inbox className="h-8 w-8" />
                <p className="text-sm font-medium">{tc(locale, 'threads.empty')}</p>
                <p className="text-xs">{tc(locale, 'threads.emptyHint')}</p>
              </div>
            ) : (
              <ul className="space-y-3">
                {threads.map((thread) => {
                  const isOpen = openThread === thread.id;
                  const messages = threadMessages[thread.id] ?? [];

                  return (
                    <li key={thread.id} className="rounded-2xl border border-slate-100 bg-white shadow-sm">
                      <button
                        type="button"
                        onClick={() => void toggleThread(thread.id)}
                        className="flex w-full flex-wrap items-center justify-between gap-2 p-4 text-start"
                      >
                        <span>
                          <span className="block font-semibold text-slate-900">
                            {thread.subject ?? tc(locale, 'threads.noSubject')}
                          </span>
                          <span className="block text-xs text-slate-500">{thread.snippet}</span>
                        </span>
                        <span className="flex items-center gap-3 text-xs text-slate-400">
                          <span>{tc(locale, 'threads.messagesCount', { count: thread.message_count })}</span>
                          <span>{formatDate(thread.last_message_at)}</span>
                          {isOpen ? <ChevronUp className="h-4 w-4" /> : <ChevronDown className="h-4 w-4" />}
                        </span>
                      </button>

                      {isOpen && (
                        <div className="space-y-3 border-t border-slate-100 p-4">
                          {threadLoading === thread.id && (
                            <p className="text-sm text-slate-400">{tc(locale, 'threads.loadingMessages')}</p>
                          )}
                          {threadError && threadLoading !== thread.id && messages.length === 0 && (
                            <p className="text-sm text-red-500">{threadError}</p>
                          )}
                          {messages.map((message) => (
                            <article
                              key={message.id}
                              className="rounded-xl border border-slate-100 bg-slate-50 p-4"
                            >
                              <div className="flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500">
                                <span>
                                  <strong>{tc(locale, 'messages.from')}</strong> {message.from_email ?? '—'}
                                </span>
                                <span>{formatDate(message.sent_at)}</span>
                              </div>
                              <div className="mt-2 flex flex-wrap items-center gap-2">
                                <span className="rounded-full bg-cyan-100 px-2.5 py-0.5 text-xs font-semibold text-cyan-700">
                                  {categoryLabel(
                                    categories,
                                    message.ai_category,
                                    tc(locale, 'messages.uncategorized'),
                                  )}
                                </span>
                                {message.ai_sentiment && (
                                  <span className="rounded-full bg-slate-200 px-2.5 py-0.5 text-xs font-medium text-slate-600">
                                    {tc(locale, `messages.sentiment.${message.ai_sentiment}`)}
                                  </span>
                                )}
                                {message.ai_action && (
                                  <span className="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-medium text-emerald-700">
                                    {tc(locale, `messages.action.${message.ai_action}`)}
                                  </span>
                                )}
                                {typeof message.ai_confidence === 'number' && (
                                  <span className="text-xs text-slate-400">
                                    {tc(locale, 'messages.confidence', { value: message.ai_confidence })}
                                  </span>
                                )}
                                <button
                                  type="button"
                                  onClick={() => void reclassify(message.id)}
                                  className="ms-auto rounded-full border border-slate-200 px-3 py-0.5 text-xs font-semibold text-slate-500 hover:bg-white"
                                >
                                  {tc(locale, 'messages.reclassify')}
                                </button>
                              </div>
                              {message.body ? (
                                <p className="mt-3 whitespace-pre-wrap text-sm leading-relaxed text-slate-700">
                                  {message.body}
                                </p>
                              ) : (
                                <p className="mt-3 text-sm italic text-slate-400">{message.snippet}</p>
                              )}
                              {message.labels.length > 0 && (
                                <p className="mt-2 flex items-center gap-1 text-xs text-slate-400">
                                  <Paperclip className="h-3 w-3" />
                                  {message.labels.join(' · ')}
                                </p>
                              )}
                            </article>
                          ))}
                        </div>
                      )}
                    </li>
                  );
                })}
              </ul>
            )}
          </section>
        </div>
      )}
    </ModulePageShell>
  );
}
