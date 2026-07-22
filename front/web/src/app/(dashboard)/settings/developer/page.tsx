'use client';

import { useCallback, useEffect, useState, useSyncExternalStore } from 'react';
import { Key, Webhook, FileText, Plus, Trash2, Copy, Check, X, Loader2 } from 'lucide-react';
import Link from 'next/link';
import { motion, AnimatePresence } from 'framer-motion';
import { ApiError, apiFetch } from '@/lib/api-client';
import { ModulePageShell } from '@/components/module-page-shell';
import { getCopy, getPreferredLocale, toIntlLocale, type AppLocale } from '@/lib/i18n';

const emptySubscribe = () => () => {};

type ApiToken = {
  id: number | string;
  name: string;
  abilities?: string[];
  last_used_at?: string | null;
  created_at?: string | null;
};

type WebhookEndpoint = {
  id: number | string;
  url: string;
  events: string[];
  active: boolean;
  failure_count?: number | string | null;
  last_triggered_at?: string | null;
  created_at?: string | null;
};

export default function DeveloperSettingsPage() {
  const locale = useSyncExternalStore<AppLocale>(emptySubscribe, getPreferredLocale, () => 'fr');
  const labels = getCopy(locale).developerSettingsPage;
  const intlLocale = toIntlLocale(locale);
  const [copiedKey, setCopiedKey] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  const [tokens, setTokens] = useState<ApiToken[]>([]);
  const [tokensLoading, setTokensLoading] = useState(true);
  const [newTokenName, setNewTokenName] = useState('');
  const [creatingToken, setCreatingToken] = useState(false);
  const [revealedToken, setRevealedToken] = useState<{ name: string; token: string } | null>(null);

  const [webhooks, setWebhooks] = useState<WebhookEndpoint[]>([]);
  const [webhooksLoading, setWebhooksLoading] = useState(true);
  const [availableEvents, setAvailableEvents] = useState<string[]>([]);
  const [showWebhookModal, setShowWebhookModal] = useState(false);
  const [newWebhook, setNewWebhook] = useState({ url: '', events: [] as string[] });
  const [creatingWebhook, setCreatingWebhook] = useState(false);

  const handleCopy = (text: string, key: string) => {
    navigator.clipboard.writeText(text);
    setCopiedKey(key);
    setTimeout(() => setCopiedKey(null), 2000);
  };

  const loadTokens = useCallback(async () => {
    setTokensLoading(true);
    try {
      const res = await apiFetch('/api-tokens');
      const data = await res.json() as { data?: ApiToken[] };
      setTokens(Array.isArray(data.data) ? data.data : []);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : labels.loadTokensError);
    } finally {
      setTokensLoading(false);
    }
  }, [labels.loadTokensError]);

  const loadWebhooks = useCallback(async () => {
    setWebhooksLoading(true);
    try {
      const [webhooksRes, eventsRes] = await Promise.all([
        apiFetch('/webhooks'),
        apiFetch('/webhooks/events'),
      ]);
      const webhooksData = await webhooksRes.json() as { data?: WebhookEndpoint[] };
      const eventsData = await eventsRes.json() as { data?: string[] };
      setWebhooks(Array.isArray(webhooksData.data) ? webhooksData.data : []);
      setAvailableEvents(Array.isArray(eventsData.data) ? eventsData.data : []);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : labels.loadWebhooksError);
    } finally {
      setWebhooksLoading(false);
    }
  }, [labels.loadWebhooksError]);

  useEffect(() => {
    void loadTokens();
    void loadWebhooks();
  }, [loadTokens, loadWebhooks]);

  const handleCreateToken = async () => {
    if (!newTokenName.trim()) return;
    setCreatingToken(true);
    setError(null);
    try {
      const res = await apiFetch('/api-tokens', {
        method: 'POST',
        body: JSON.stringify({ name: newTokenName.trim() }),
      });
      const data = await res.json() as { data?: { name: string; token: string } };
      if (data.data?.token) {
        setRevealedToken({ name: data.data.name, token: data.data.token });
      }
      setNewTokenName('');
      await loadTokens();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : labels.createTokenError);
    } finally {
      setCreatingToken(false);
    }
  };

  const handleDeleteToken = async (tokenId: ApiToken['id']) => {
    if (!confirm(labels.revokeTokenConfirm)) return;
    try {
      await apiFetch(`/api-tokens/${tokenId}`, { method: 'DELETE' });
      setTokens((prev) => prev.filter((t) => t.id !== tokenId));
    } catch (err) {
      setError(err instanceof ApiError ? err.message : labels.deleteTokenError);
    }
  };

  const toggleEvent = (event: string) => {
    setNewWebhook((prev) => ({
      ...prev,
      events: prev.events.includes(event) ? prev.events.filter((e) => e !== event) : [...prev.events, event],
    }));
  };

  const handleCreateWebhook = async () => {
    if (!newWebhook.url.trim() || newWebhook.events.length === 0) return;
    setCreatingWebhook(true);
    setError(null);
    try {
      await apiFetch('/webhooks', {
        method: 'POST',
        body: JSON.stringify(newWebhook),
      });
      setShowWebhookModal(false);
      setNewWebhook({ url: '', events: [] });
      await loadWebhooks();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : labels.createWebhookError);
    } finally {
      setCreatingWebhook(false);
    }
  };

  const handleDeleteWebhook = async (id: WebhookEndpoint['id']) => {
    if (!confirm(labels.deleteWebhookConfirm)) return;
    try {
      await apiFetch(`/webhooks/${id}`, { method: 'DELETE' });
      setWebhooks((prev) => prev.filter((w) => w.id !== id));
    } catch (err) {
      setError(err instanceof ApiError ? err.message : labels.deleteWebhookError);
    }
  };

  const handleToggleWebhookActive = async (webhook: WebhookEndpoint) => {
    try {
      const res = await apiFetch(`/webhooks/${webhook.id}`, {
        method: 'PUT',
        body: JSON.stringify({ active: !webhook.active }),
      });
      const data = await res.json() as { data?: WebhookEndpoint };
      if (data.data) {
        setWebhooks((prev) => prev.map((w) => (w.id === webhook.id ? data.data as WebhookEndpoint : w)));
      }
    } catch (err) {
      setError(err instanceof ApiError ? err.message : labels.updateWebhookError);
    }
  };

  return (
    <>
      <ModulePageShell
        title={labels.title}
        subtitle={labels.subtitle}
        accentClassName="bg-gradient-to-br from-security/10 via-white to-white"
      >
        {error ? (
          <div className="flex items-center justify-between rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <span>{error}</span>
            <button onClick={() => setError(null)} className="text-red-400 hover:text-red-600"><X className="h-4 w-4" /></button>
          </div>
        ) : null}

        {revealedToken ? (
          <div className="rounded-2xl border border-amber-200 bg-amber-50 p-4">
            <p className="text-sm font-bold text-amber-900">
              {labels.revealedTokenNotice.replace('{name}', revealedToken.name)}
            </p>
            <div className="mt-2 flex items-center gap-2">
              <code className="flex-1 break-all rounded-xl border border-amber-200 bg-white px-3 py-2 text-xs font-mono">
                {revealedToken.token}
              </code>
              <button
                onClick={() => handleCopy(revealedToken.token, 'revealed_token')}
                className="rounded-xl border border-amber-300 bg-white p-2 text-amber-700 hover:bg-amber-100"
              >
                {copiedKey === 'revealed_token' ? <Check className="h-4 w-4 text-emerald-500" /> : <Copy className="h-4 w-4" />}
              </button>
            </div>
            <button onClick={() => setRevealedToken(null)} className="mt-2 text-xs font-bold uppercase tracking-wider text-amber-700 underline">
              {labels.revealedTokenDismiss}
            </button>
          </div>
        ) : null}

        <div className="grid gap-6 md:grid-cols-2">
          <section className="rounded-2xl border border-app-border bg-white p-6 shadow-sm">
            <div className="flex items-center gap-3 border-b border-app-border pb-4">
              <div className="rounded-xl bg-brand-50 p-2 text-brand-600">
                <Key className="h-5 w-5" />
              </div>
              <h2 className="text-sm font-bold uppercase tracking-wider text-slate-800">{labels.apiKeysTitle}</h2>
            </div>

            <div className="mt-4 space-y-3">
              {tokensLoading ? (
                <p className="text-sm text-slate-400">{labels.loading}</p>
              ) : tokens.length === 0 ? (
                <p className="text-sm text-slate-400">{labels.noTokens}</p>
              ) : (
                tokens.map((token) => (
                  <div key={token.id} className="flex items-center justify-between rounded-xl border border-app-border bg-slate-50 p-4">
                    <div>
                      <p className="font-bold text-slate-950">{token.name}</p>
                      <p className="text-xs text-slate-500">
                        {token.created_at
                          ? labels.createdOn.replace('{date}', new Date(token.created_at).toLocaleDateString(intlLocale))
                          : labels.unknownDate}
                        {token.last_used_at
                          ? labels.lastUsedOn.replace('{date}', new Date(token.last_used_at).toLocaleDateString(intlLocale))
                          : labels.neverUsed}
                      </p>
                    </div>
                    <button
                      onClick={() => handleDeleteToken(token.id)}
                      className="rounded-lg p-2 text-red-400 hover:bg-red-50 hover:text-red-600"
                      title={labels.revoke}
                    >
                      <Trash2 className="h-4 w-4" />
                    </button>
                  </div>
                ))
              )}
            </div>

            <div className="mt-4 flex gap-2">
              <input
                type="text"
                placeholder={labels.tokenNamePlaceholder}
                value={newTokenName}
                onChange={(e) => setNewTokenName(e.target.value)}
                onKeyDown={(e) => { if (e.key === 'Enter') void handleCreateToken(); }}
                className="flex-1 rounded-xl border border-app-border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500"
              />
              <button
                onClick={handleCreateToken}
                disabled={!newTokenName.trim() || creatingToken}
                className="flex items-center gap-2 rounded-xl bg-slate-950 px-4 py-2 text-sm font-bold text-white hover:bg-slate-800 disabled:opacity-50"
              >
                {creatingToken ? <Loader2 className="h-4 w-4 animate-spin" /> : <Plus className="h-4 w-4" />}
              </button>
            </div>
          </section>

          <section className="rounded-2xl border border-app-border bg-white p-6 shadow-sm">
            <div className="flex items-center gap-3 border-b border-app-border pb-4">
              <div className="rounded-xl bg-ia-light p-2 text-ia">
                <Webhook className="h-5 w-5" />
              </div>
              <h2 className="text-sm font-bold uppercase tracking-wider text-slate-800">{labels.webhooksTitle}</h2>
            </div>

            <div className="mt-4 space-y-3">
              {webhooksLoading ? (
                <p className="text-sm text-slate-400">{labels.loading}</p>
              ) : webhooks.length === 0 ? (
                <p className="text-sm text-slate-400">{labels.noWebhooks}</p>
              ) : (
                webhooks.map((webhook) => (
                  <div key={webhook.id} className="rounded-xl border border-app-border bg-slate-50 p-4">
                    <div className="flex items-center justify-between gap-2">
                      <div className="min-w-0 flex-1">
                        <p className="truncate font-bold text-slate-950">{webhook.url}</p>
                        <p className="mt-0.5 text-xs text-slate-500">
                          {labels.eventsCount.replace('{count}', String(webhook.events.length))} · {webhook.failure_count ? labels.failuresCount.replace('{count}', String(webhook.failure_count)) : labels.noFailures}
                        </p>
                      </div>
                      <button
                        onClick={() => handleToggleWebhookActive(webhook)}
                        className={`shrink-0 rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-wider ${
                          webhook.active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-200 text-slate-600'
                        }`}
                      >
                        {webhook.active ? labels.active : labels.inactive}
                      </button>
                    </div>
                    <div className="mt-2 flex flex-wrap gap-1">
                      {webhook.events.map((event) => (
                        <span key={event} className="rounded-full border border-app-border bg-white px-2 py-0.5 text-[10px] font-medium text-slate-600">
                          {event}
                        </span>
                      ))}
                    </div>
                    <div className="mt-3 flex items-center justify-between border-t border-app-border pt-3">
                      <span className="text-xs text-slate-500">
                        {webhook.last_triggered_at
                          ? labels.triggeredOn.replace('{date}', new Date(webhook.last_triggered_at).toLocaleString(intlLocale))
                          : labels.neverTriggered}
                      </span>
                      <button
                        onClick={() => handleDeleteWebhook(webhook.id)}
                        className="flex items-center gap-1 text-xs font-bold text-red-500 hover:underline"
                      >
                        <Trash2 className="h-3 w-3" /> {labels.delete}
                      </button>
                    </div>
                  </div>
                ))
              )}
            </div>

            <button
              onClick={() => setShowWebhookModal(true)}
              className="mt-4 flex w-full items-center justify-center gap-2 rounded-xl border border-dashed border-app-border py-3 text-sm font-bold text-slate-600 hover:bg-slate-50 hover:text-slate-900"
            >
              <Plus className="h-4 w-4" /> {labels.addEndpoint}
            </button>
          </section>
        </div>

        <section className="rounded-2xl border border-app-border bg-gradient-to-br from-slate-900 to-slate-800 p-6 text-white shadow-sm">
          <div className="flex flex-col justify-between gap-4 md:flex-row md:items-center">
            <div>
              <h2 className="text-lg font-black">{labels.apiDocsTitle}</h2>
              <p className="mt-1 text-sm text-slate-400">
                {labels.apiDocsBody}
              </p>
            </div>
            <Link
              href={process.env.NEXT_PUBLIC_API_URL?.replace('/api/v1', '/api-explorer') ?? '/api-explorer'}
              target="_blank"
              className="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2 text-sm font-bold text-slate-900 transition hover:bg-slate-100"
            >
              <FileText className="h-4 w-4" />
              {labels.openExplorer}
            </Link>
          </div>
        </section>
      </ModulePageShell>

      <AnimatePresence>
        {showWebhookModal ? (
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
          >
            <motion.div
              initial={{ opacity: 0, scale: 0.95 }}
              animate={{ opacity: 1, scale: 1 }}
              exit={{ opacity: 0, scale: 0.95 }}
              className="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl"
            >
              <div className="mb-4 flex items-center justify-between">
                <h2 className="text-lg font-black text-slate-950">{labels.newWebhookModalTitle}</h2>
                <button onClick={() => setShowWebhookModal(false)} className="text-slate-400 hover:text-slate-600"><X className="h-5 w-5" /></button>
              </div>
              <div className="space-y-3">
                <div>
                  <label className="mb-1 block text-xs font-bold uppercase tracking-wider text-slate-400">{labels.destinationUrlLabel}</label>
                  <input
                    type="url"
                    placeholder="https://erp.client.com/webhook"
                    value={newWebhook.url}
                    onChange={(e) => setNewWebhook({ ...newWebhook, url: e.target.value })}
                    className="w-full rounded-xl border border-app-border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500"
                  />
                </div>
                <div>
                  <label className="mb-1 block text-xs font-bold uppercase tracking-wider text-slate-400">{labels.eventsToListenLabel}</label>
                  <div className="max-h-48 space-y-1 overflow-y-auto rounded-xl border border-app-border p-2">
                    {availableEvents.map((event) => (
                      <label key={event} className="flex items-center gap-2 rounded-lg px-2 py-1 text-sm hover:bg-slate-50">
                        <input
                          type="checkbox"
                          checked={newWebhook.events.includes(event)}
                          onChange={() => toggleEvent(event)}
                        />
                        {event}
                      </label>
                    ))}
                  </div>
                </div>
              </div>
              <div className="mt-5 flex gap-3">
                <button onClick={() => setShowWebhookModal(false)} className="flex-1 rounded-xl border border-app-border px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50">{labels.cancel}</button>
                <button
                  onClick={handleCreateWebhook}
                  disabled={!newWebhook.url.trim() || newWebhook.events.length === 0 || creatingWebhook}
                  className="flex-1 rounded-xl bg-brand-600 px-4 py-2 text-sm font-bold text-white hover:bg-brand-700 disabled:opacity-50"
                >
                  {creatingWebhook ? labels.creating : labels.create}
                </button>
              </div>
            </motion.div>
          </motion.div>
        ) : null}
      </AnimatePresence>
    </>
  );
}
