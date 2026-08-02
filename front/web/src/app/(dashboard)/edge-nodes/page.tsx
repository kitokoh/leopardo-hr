// ============================================================
// Edge Nodes Dashboard Page â€” Priority 3.2
// Manages Leopardo Edge nodes for the company
// ============================================================

'use client';

import { useState, useEffect, useCallback, useSyncExternalStore } from 'react';
import { motion } from 'framer-motion';
import { Cpu, Plus, RefreshCw, ShieldAlert, Wifi, WifiOff, X } from 'lucide-react';
import { ApiError, apiFetch } from '@/lib/api-client';
import { ModulePageShell } from '@/components/module-page-shell';
import { getCopy, getPreferredLocale, toIntlLocale, type AppLocale } from '@/lib/i18n';

const emptySubscribe = () => () => {};

interface EdgeNode {
  id: string;
  name: string;
  slug: string;
  site_address: string | null;
  status: 'active' | 'inactive' | 'suspended';
  mode: 'cloud' | 'offline' | 'hybrid';
  is_online: boolean;
  license_valid: boolean;
  license_expires_at: string | null;
  last_sync_at: string | null;
  last_seen_at: string | null;
  edge_version: string;
}

const MODE_STYLES: Record<string, string> = {
  cloud: 'bg-info/15 text-info',
  hybrid: 'bg-finance-light text-finance-dark',
  offline: 'bg-slate-100 text-slate-600',
};

export default function EdgeNodesPage() {
  const locale = useSyncExternalStore<AppLocale>(emptySubscribe, getPreferredLocale, () => 'fr');
  const labels = getCopy(locale).edgeNodesPage;
  const intlLocale = toIntlLocale(locale);
  const modeLabels: Record<string, string> = {
    cloud: labels.modeCloud,
    hybrid: labels.modeHybrid,
    offline: labels.modeOffline,
  };
  const [nodes, setNodes] = useState<EdgeNode[]>([]);
  const [loading, setLoading] = useState(true);
  const [syncing, setSyncing] = useState<string | null>(null);
  const [showAddModal, setShowAddModal] = useState(false);
  const [newNode, setNewNode] = useState({ name: '', site_address: '', mode: 'hybrid' });
  const [installCommand, setInstallCommand] = useState<string | null>(null);
  const [loadError, setLoadError] = useState<string | null>(null);

  const fetchNodes = useCallback(async () => {
    try {
      const res = await apiFetch('/edge');
      const data = await res.json();
      setNodes(data.data ?? []);
      setLoadError(null);
    } catch (err) {
      setLoadError(err instanceof ApiError ? err.message : labels.loadError);
    } finally {
      setLoading(false);
    }
  }, [labels.loadError]);

  useEffect(() => {
    fetchNodes();
    const interval = setInterval(fetchNodes, 30000); // refresh every 30s
    return () => clearInterval(interval);
  }, [fetchNodes]);

  async function triggerSync(nodeId: string) {
    setSyncing(nodeId);
    try {
      const res = await apiFetch(`/edge/${nodeId}/sync`, { method: 'POST' });
      const data = await res.json();
      alert(
        labels.syncCompleteMessage
          .replace('{sent}', String(data.data?.records_sent ?? 0))
          .replace('{conflicts}', String(data.data?.conflicts_detected ?? 0)),
      );
      fetchNodes();
    } catch (err) {
      alert(err instanceof ApiError ? err.message : labels.syncError);
    } finally {
      setSyncing(null);
    }
  }

  async function addNode() {
    try {
      const res = await apiFetch('/edge', {
        method: 'POST',
        body: JSON.stringify(newNode),
      });
      const data = await res.json();
      if (data.install_command) setInstallCommand(data.install_command);
      setShowAddModal(false);
      fetchNodes();
    } catch (err) {
      alert(err instanceof ApiError ? err.message : labels.createError);
    }
  }

  const stats = [
    { label: labels.statTotalNodes, value: nodes.length },
    { label: labels.statOnline, value: nodes.filter((n) => n.is_online).length },
    { label: labels.statOffline, value: nodes.filter((n) => !n.is_online).length },
    { label: labels.statValidLicenses, value: nodes.filter((n) => n.license_valid).length },
  ];

  return (
    <>
      <ModulePageShell
        title={labels.title}
        subtitle={labels.subtitle}
        accentClassName="bg-gradient-to-br from-security/10 via-white to-white"
      >
        {loadError ? (
          <div className="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {loadError}
          </div>
        ) : null}

        <div className="flex justify-end">
          <button
            onClick={() => setShowAddModal(true)}
            className="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-emerald-700"
          >
            <Plus className="h-4 w-4" /> {labels.newNode}
          </button>
        </div>

        {installCommand ? (
          <div className="rounded-2xl border border-emerald-200 bg-emerald-50 p-5">
            <p className="text-sm font-bold text-emerald-900">
              {labels.nodeCreatedTitle}
            </p>
            <code className="mt-3 block break-all rounded-xl border border-emerald-200 bg-white p-3 text-xs font-mono text-slate-700">
              {installCommand}
            </code>
            <button
              onClick={() => { navigator.clipboard.writeText(installCommand); }}
              className="mt-3 text-xs font-bold uppercase tracking-wider text-emerald-700 underline"
            >
              {labels.copy}
            </button>
          </div>
        ) : null}

        <section className="grid grid-cols-2 gap-4 sm:grid-cols-4">
          {stats.map((stat, i) => (
            <motion.div
              key={stat.label}
              initial={{ opacity: 0, y: 10 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: i * 0.05 }}
              className="rounded-2xl border border-app-border bg-white p-5 shadow-sm"
            >
              <p className="text-xs font-bold uppercase tracking-widest text-slate-400">{stat.label}</p>
              <p className="mt-2 text-2xl font-black text-slate-950">{loading ? '...' : stat.value}</p>
            </motion.div>
          ))}
        </section>

        <section className="overflow-hidden rounded-3xl border border-app-border bg-white shadow-sm">
          <div className="border-b border-app-border px-6 py-4">
            <h2 className="text-sm font-bold uppercase tracking-wider text-slate-800">{labels.configuredNodesTitle}</h2>
          </div>
          {loading ? (
            <div className="px-6 py-8 text-sm text-slate-500">{labels.loadingNodes}</div>
          ) : nodes.length === 0 ? (
            <div className="px-6 py-10 text-center text-sm text-slate-500">
              {labels.noNodes}
            </div>
          ) : (
            <div className="divide-y divide-app-border">
              {nodes.map((node) => (
                <div key={node.id} className="flex flex-col gap-3 px-6 py-5 md:flex-row md:items-center md:justify-between">
                  <div className="flex items-start gap-4">
                    <div className={`mt-1 flex h-9 w-9 items-center justify-center rounded-xl ${node.is_online ? 'bg-emerald-50 text-emerald-600' : 'bg-red-50 text-red-500'}`}>
                      {node.is_online ? <Wifi className="h-4 w-4" /> : <WifiOff className="h-4 w-4" />}
                    </div>
                    <div>
                      <div className="flex items-center gap-2">
                        <p className="text-sm font-bold text-slate-950">{node.name}</p>
                        <span className={`rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider ${MODE_STYLES[node.mode] ?? 'bg-slate-100 text-slate-600'}`}>
                          {modeLabels[node.mode] ?? node.mode}
                        </span>
                        {!node.license_valid ? (
                          <span className="inline-flex items-center gap-1 rounded-full bg-red-50 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-red-600">
                            <ShieldAlert className="h-3 w-3" /> {labels.licenseExpired}
                          </span>
                        ) : null}
                      </div>
                      <p className="mt-1 text-xs text-slate-500">
                        {node.site_address ?? labels.addressMissing} Â· v{node.edge_version}
                      </p>
                      <p className="mt-1 text-xs text-slate-400">
                        {labels.lastSyncLabel}{node.last_sync_at ? new Date(node.last_sync_at).toLocaleString(intlLocale) : labels.lastSyncNever}
                      </p>
                    </div>
                  </div>
                  <div className="flex items-center gap-2">
                    <span className={`rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-wider ${node.is_online ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-600'}`}>
                      {node.is_online ? labels.statusOnline : labels.statusOffline}
                    </span>
                    <button
                      onClick={() => triggerSync(node.id)}
                      disabled={syncing === node.id || !node.is_online}
                      className="inline-flex items-center gap-1.5 rounded-lg border border-app-border bg-white px-3 py-1.5 text-xs font-bold text-slate-700 transition hover:bg-transparent disabled:opacity-50"
                    >
                      <RefreshCw className={`h-3.5 w-3.5 ${syncing === node.id ? 'animate-spin' : ''}`} />
                      {syncing === node.id ? labels.syncing : labels.sync}
                    </button>
                  </div>
                </div>
              ))}
            </div>
          )}
        </section>
      </ModulePageShell>

      {showAddModal ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
          <div className="w-full max-w-md rounded-3xl bg-white p-6 shadow-xl">
            <div className="mb-4 flex items-center justify-between">
              <h2 className="flex items-center gap-2 text-lg font-black text-slate-950">
                <Cpu className="h-5 w-5 text-emerald-600" /> {labels.modalTitle}
              </h2>
              <button onClick={() => setShowAddModal(false)} className="text-slate-400 hover:text-slate-600">
                <X className="h-5 w-5" />
              </button>
            </div>
            <div className="space-y-3">
              <div>
                <label className="mb-1 block text-xs font-bold uppercase tracking-wider text-slate-400">{labels.siteNameLabel}</label>
                <input
                  type="text"
                  placeholder={labels.siteNamePlaceholder}
                  value={newNode.name}
                  onChange={(e) => setNewNode({ ...newNode, name: e.target.value })}
                  className="w-full rounded-xl border border-app-border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                />
              </div>
              <div>
                <label className="mb-1 block text-xs font-bold uppercase tracking-wider text-slate-400">{labels.siteAddressLabel}</label>
                <input
                  type="text"
                  placeholder={labels.siteAddressPlaceholder}
                  value={newNode.site_address}
                  onChange={(e) => setNewNode({ ...newNode, site_address: e.target.value })}
                  className="w-full rounded-xl border border-app-border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                />
              </div>
              <div>
                <label className="mb-1 block text-xs font-bold uppercase tracking-wider text-slate-400">{labels.modeLabel}</label>
                <select
                  value={newNode.mode}
                  onChange={(e) => setNewNode({ ...newNode, mode: e.target.value })}
                  className="w-full rounded-xl border border-app-border px-3 py-2 text-sm"
                >
                  <option value="hybrid">{labels.modeHybridOption}</option>
                  <option value="offline">{labels.modeOfflineOption}</option>
                  <option value="cloud">{labels.modeCloudOption}</option>
                </select>
              </div>
            </div>
            <div className="mt-5 flex gap-3">
              <button
                onClick={() => setShowAddModal(false)}
                className="flex-1 rounded-xl border border-app-border px-4 py-2 text-sm font-bold text-slate-700 hover:bg-transparent"
              >
                {labels.cancel}
              </button>
              <button
                onClick={addNode}
                disabled={!newNode.name}
                className="flex-1 rounded-xl bg-emerald-600 px-4 py-2 text-sm font-bold text-white hover:bg-emerald-700 disabled:opacity-50"
              >
                {labels.createNode}
              </button>
            </div>
          </div>
        </div>
      ) : null}
    </>
  );
}

