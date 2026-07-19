'use client';

import { useCallback, useEffect, useState } from 'react';
import { Key, Webhook, FileText, Plus, Trash2, Copy, Check, X, Loader2 } from 'lucide-react';
import Link from 'next/link';
import { motion, AnimatePresence } from 'framer-motion';
import { ApiError, apiFetch } from '@/lib/api-client';

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
      setError(err instanceof ApiError ? err.message : 'Impossible de charger les cles API.');
    } finally {
      setTokensLoading(false);
    }
  }, []);

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
      setError(err instanceof ApiError ? err.message : 'Impossible de charger les webhooks.');
    } finally {
      setWebhooksLoading(false);
    }
  }, []);

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
      setError(err instanceof ApiError ? err.message : 'Impossible de creer la cle API.');
    } finally {
      setCreatingToken(false);
    }
  };

  const handleDeleteToken = async (tokenId: ApiToken['id']) => {
    if (!confirm('Revoquer cette cle API ? Les integrations qui l\'utilisent cesseront de fonctionner.')) return;
    try {
      await apiFetch(`/api-tokens/${tokenId}`, { method: 'DELETE' });
      setTokens((prev) => prev.filter((t) => t.id !== tokenId));
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Impossible de revoquer la cle API.');
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
      setError(err instanceof ApiError ? err.message : 'Impossible de creer le webhook.');
    } finally {
      setCreatingWebhook(false);
    }
  };

  const handleDeleteWebhook = async (id: WebhookEndpoint['id']) => {
    if (!confirm('Supprimer cet endpoint webhook ?')) return;
    try {
      await apiFetch(`/webhooks/${id}`, { method: 'DELETE' });
      setWebhooks((prev) => prev.filter((w) => w.id !== id));
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Impossible de supprimer le webhook.');
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
      setError(err instanceof ApiError ? err.message : 'Impossible de mettre a jour le webhook.');
    }
  };

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-slate-900">Espace Développeur</h1>
        <p className="text-slate-500">Gérez vos clés API et vos webhooks pour intégrer Leopardo RH à vos outils.</p>
      </div>

      {error ? (
        <div className="flex items-center justify-between rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
          <span>{error}</span>
          <button onClick={() => setError(null)} className="text-red-400 hover:text-red-600"><X className="h-4 w-4" /></button>
        </div>
      ) : null}

      {revealedToken ? (
        <div className="rounded-xl border border-amber-200 bg-amber-50 p-4">
          <p className="text-sm font-bold text-amber-900">
            Cle "{revealedToken.name}" creee — copiez-la maintenant, elle ne sera plus jamais affichee :
          </p>
          <div className="mt-2 flex items-center gap-2">
            <code className="flex-1 rounded-lg border border-amber-200 bg-white px-3 py-2 text-xs font-mono break-all">
              {revealedToken.token}
            </code>
            <button
              onClick={() => handleCopy(revealedToken.token, 'revealed_token')}
              className="rounded-lg border border-amber-300 bg-white p-2 text-amber-700 hover:bg-amber-100"
            >
              {copiedKey === 'revealed_token' ? <Check className="h-4 w-4 text-emerald-500" /> : <Copy className="h-4 w-4" />}
            </button>
          </div>
          <button onClick={() => setRevealedToken(null)} className="mt-2 text-xs font-medium text-amber-700 underline">
            J&apos;ai copie la cle, masquer
          </button>
        </div>
      ) : null}

      <div className="grid gap-6 md:grid-cols-2">
        <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
          <div className="flex items-center gap-3 border-b border-slate-100 pb-4">
            <div className="rounded-xl bg-blue-50 p-2 text-blue-600">
              <Key className="h-5 w-5" />
            </div>
            <h2 className="text-lg font-semibold text-slate-900">Clés API</h2>
          </div>

          <div className="mt-4 space-y-3">
            {tokensLoading ? (
              <p className="text-sm text-slate-400">Chargement...</p>
            ) : tokens.length === 0 ? (
              <p className="text-sm text-slate-400">Aucune cle API creee pour le moment.</p>
            ) : (
              tokens.map((token) => (
                <div key={token.id} className="flex items-center justify-between rounded-xl border border-slate-100 bg-slate-50 p-4">
                  <div>
                    <p className="font-medium text-slate-900">{token.name}</p>
                    <p className="text-xs text-slate-500">
                      {token.created_at ? `Creee le ${new Date(token.created_at).toLocaleDateString('fr-FR')}` : 'Date inconnue'}
                      {token.last_used_at ? ` · derniere utilisation le ${new Date(token.last_used_at).toLocaleDateString('fr-FR')}` : ' · jamais utilisee'}
                    </p>
                  </div>
                  <button
                    onClick={() => handleDeleteToken(token.id)}
                    className="rounded-lg p-2 text-red-400 hover:bg-red-50 hover:text-red-600"
                    title="Revoquer"
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
              placeholder="Nom de la cle (ex: Production)"
              value={newTokenName}
              onChange={(e) => setNewTokenName(e.target.value)}
              onKeyDown={(e) => { if (e.key === 'Enter') void handleCreateToken(); }}
              className="flex-1 rounded-xl border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
            <button
              onClick={handleCreateToken}
              disabled={!newTokenName.trim() || creatingToken}
              className="flex items-center gap-2 rounded-xl bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-50"
            >
              {creatingToken ? <Loader2 className="h-4 w-4 animate-spin" /> : <Plus className="h-4 w-4" />}
            </button>
          </div>
        </div>

        <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
          <div className="flex items-center gap-3 border-b border-slate-100 pb-4">
            <div className="rounded-xl bg-purple-50 p-2 text-purple-600">
              <Webhook className="h-5 w-5" />
            </div>
            <h2 className="text-lg font-semibold text-slate-900">Webhooks</h2>
          </div>

          <div className="mt-4 space-y-3">
            {webhooksLoading ? (
              <p className="text-sm text-slate-400">Chargement...</p>
            ) : webhooks.length === 0 ? (
              <p className="text-sm text-slate-400">Aucun endpoint webhook configure.</p>
            ) : (
              webhooks.map((webhook) => (
                <div key={webhook.id} className="rounded-xl border border-slate-100 bg-slate-50 p-4">
                  <div className="flex items-center justify-between gap-2">
                    <div className="min-w-0 flex-1">
                      <p className="truncate font-medium text-slate-900">{webhook.url}</p>
                      <p className="mt-0.5 text-xs text-slate-500">
                        {webhook.events.length} evenement(s) · {webhook.failure_count ? `${webhook.failure_count} echec(s)` : 'aucun echec'}
                      </p>
                    </div>
                    <button
                      onClick={() => handleToggleWebhookActive(webhook)}
                      className={`shrink-0 rounded-full px-2 py-1 text-xs font-medium ${
                        webhook.active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600'
                      }`}
                    >
                      {webhook.active ? 'Actif' : 'Inactif'}
                    </button>
                  </div>
                  <div className="mt-2 flex flex-wrap gap-1">
                    {webhook.events.map((event) => (
                      <span key={event} className="rounded-full bg-white px-2 py-0.5 text-[10px] font-medium text-slate-600 border border-slate-200">
                        {event}
                      </span>
                    ))}
                  </div>
                  <div className="mt-3 flex items-center justify-between border-t border-slate-200 pt-3">
                    <span className="text-xs text-slate-500">
                      {webhook.last_triggered_at ? `Declenche le ${new Date(webhook.last_triggered_at).toLocaleString('fr-FR')}` : 'Jamais declenche'}
                    </span>
                    <button
                      onClick={() => handleDeleteWebhook(webhook.id)}
                      className="flex items-center gap-1 text-xs font-medium text-red-500 hover:underline"
                    >
                      <Trash2 className="h-3 w-3" /> Supprimer
                    </button>
                  </div>
                </div>
              ))
            )}
          </div>

          <button
            onClick={() => setShowWebhookModal(true)}
            className="mt-4 flex w-full items-center justify-center gap-2 rounded-xl border border-dashed border-slate-300 py-3 text-sm font-medium text-slate-600 hover:bg-slate-50 hover:text-slate-900"
          >
            <Plus className="h-4 w-4" /> Ajouter un endpoint
          </button>
        </div>
      </div>

      <div className="rounded-2xl border border-slate-200 bg-gradient-to-br from-slate-900 to-slate-800 p-6 text-white shadow-sm">
        <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
          <div>
            <h2 className="text-lg font-bold">Documentation API</h2>
            <p className="mt-1 text-sm text-slate-400">
              Découvrez comment intégrer nos webhooks signés (format Svix) et nos endpoints REST.
            </p>
          </div>
          <Link
            href={process.env.NEXT_PUBLIC_API_URL?.replace('/api/v1', '/api-explorer') ?? '/api-explorer'}
            target="_blank"
            className="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2 text-sm font-bold text-slate-900 transition hover:bg-slate-100"
          >
            <FileText className="h-4 w-4" />
            Ouvrir l'Explorer
          </Link>
        </div>
      </div>

      <AnimatePresence>
        {showWebhookModal && (
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
              <div className="flex items-center justify-between mb-4">
                <h2 className="text-lg font-bold text-slate-900">Nouvel endpoint webhook</h2>
                <button onClick={() => setShowWebhookModal(false)} className="text-slate-400 hover:text-slate-600"><X className="h-5 w-5" /></button>
              </div>
              <div className="space-y-3">
                <div>
                  <label className="mb-1 block text-sm font-medium text-slate-700">URL de destination</label>
                  <input
                    type="url"
                    placeholder="https://erp.client.com/webhook"
                    value={newWebhook.url}
                    onChange={(e) => setNewWebhook({ ...newWebhook, url: e.target.value })}
                    className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
                  />
                </div>
                <div>
                  <label className="mb-1 block text-sm font-medium text-slate-700">Evenements a ecouter</label>
                  <div className="max-h-48 space-y-1 overflow-y-auto rounded-lg border border-slate-200 p-2">
                    {availableEvents.map((event) => (
                      <label key={event} className="flex items-center gap-2 rounded px-2 py-1 text-sm hover:bg-slate-50">
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
                <button onClick={() => setShowWebhookModal(false)} className="flex-1 rounded-lg border border-slate-200 px-4 py-2 text-sm hover:bg-slate-50">Annuler</button>
                <button
                  onClick={handleCreateWebhook}
                  disabled={!newWebhook.url.trim() || newWebhook.events.length === 0 || creatingWebhook}
                  className="flex-1 rounded-lg bg-purple-600 px-4 py-2 text-sm font-semibold text-white hover:bg-purple-700 disabled:opacity-50"
                >
                  {creatingWebhook ? 'Creation...' : 'Creer'}
                </button>
              </div>
            </motion.div>
          </motion.div>
        )}
      </AnimatePresence>
    </div>
  );
}
