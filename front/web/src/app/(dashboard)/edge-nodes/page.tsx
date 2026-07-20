// ============================================================
// Edge Nodes Dashboard Page — Priority 3.2
// Manages Leopardo Edge nodes for the company
// ============================================================

'use client';

import { useState, useEffect } from 'react';
import { motion } from 'framer-motion';
import { Cpu, Plus, RefreshCw, ShieldAlert, Wifi, WifiOff, X } from 'lucide-react';
import { ApiError, apiFetch } from '@/lib/api-client';
import { ModulePageShell } from '@/components/module-page-shell';

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

const MODE_LABELS: Record<string, string> = {
  cloud: 'Cloud',
  hybrid: 'Hybride',
  offline: 'Offline',
};

const MODE_STYLES: Record<string, string> = {
  cloud: 'bg-info/15 text-info',
  hybrid: 'bg-finance-light text-finance-dark',
  offline: 'bg-slate-100 text-slate-600',
};

export default function EdgeNodesPage() {
  const [nodes, setNodes] = useState<EdgeNode[]>([]);
  const [loading, setLoading] = useState(true);
  const [syncing, setSyncing] = useState<string | null>(null);
  const [showAddModal, setShowAddModal] = useState(false);
  const [newNode, setNewNode] = useState({ name: '', site_address: '', mode: 'hybrid' });
  const [installCommand, setInstallCommand] = useState<string | null>(null);
  const [loadError, setLoadError] = useState<string | null>(null);

  useEffect(() => {
    fetchNodes();
    const interval = setInterval(fetchNodes, 30000); // refresh every 30s
    return () => clearInterval(interval);
  }, []);

  async function fetchNodes() {
    try {
      const res = await apiFetch('/edge');
      const data = await res.json();
      setNodes(data.data ?? []);
      setLoadError(null);
    } catch (err) {
      setLoadError(err instanceof ApiError ? err.message : 'Impossible de charger les Edge nodes.');
    } finally {
      setLoading(false);
    }
  }

  async function triggerSync(nodeId: string) {
    setSyncing(nodeId);
    try {
      const res = await apiFetch(`/edge/${nodeId}/sync`, { method: 'POST' });
      const data = await res.json();
      alert(`Sync termine — envoyes: ${data.data?.records_sent ?? 0}, conflits: ${data.data?.conflicts_detected ?? 0}`);
      fetchNodes();
    } catch (err) {
      alert(err instanceof ApiError ? err.message : 'Erreur lors de la synchronisation');
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
      alert(err instanceof ApiError ? err.message : 'Erreur lors de la creation du node');
    }
  }

  const stats = [
    { label: 'Total nodes', value: nodes.length },
    { label: 'En ligne', value: nodes.filter((n) => n.is_online).length },
    { label: 'Hors ligne', value: nodes.filter((n) => !n.is_online).length },
    { label: 'Licences valides', value: nodes.filter((n) => n.license_valid).length },
  ];

  return (
    <>
      <ModulePageShell
        title="Edge Nodes"
        subtitle="Gerez les noeuds locaux pour le mode offline-first : etat de connexion, licences et synchronisation."
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
            className="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-brand-700"
          >
            <Plus className="h-4 w-4" /> Nouveau Node
          </button>
        </div>

        {installCommand ? (
          <div className="rounded-2xl border border-emerald-200 bg-emerald-50 p-5">
            <p className="text-sm font-bold text-emerald-900">
              Node cree ! Copiez et executez cette commande sur votre serveur local :
            </p>
            <code className="mt-3 block break-all rounded-xl border border-emerald-200 bg-white p-3 text-xs font-mono text-slate-700">
              {installCommand}
            </code>
            <button
              onClick={() => { navigator.clipboard.writeText(installCommand); }}
              className="mt-3 text-xs font-bold uppercase tracking-wider text-emerald-700 underline"
            >
              Copier
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
            <h2 className="text-sm font-bold uppercase tracking-wider text-slate-800">Noeuds configures</h2>
          </div>
          {loading ? (
            <div className="px-6 py-8 text-sm text-slate-500">Chargement des Edge nodes...</div>
          ) : nodes.length === 0 ? (
            <div className="px-6 py-10 text-center text-sm text-slate-500">
              Aucun Edge node configure. Creez un nouveau node pour activer le mode offline-first.
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
                          {MODE_LABELS[node.mode] ?? node.mode}
                        </span>
                        {!node.license_valid ? (
                          <span className="inline-flex items-center gap-1 rounded-full bg-red-50 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-red-600">
                            <ShieldAlert className="h-3 w-3" /> Licence expiree
                          </span>
                        ) : null}
                      </div>
                      <p className="mt-1 text-xs text-slate-500">
                        {node.site_address ?? 'Adresse non renseignee'} · v{node.edge_version}
                      </p>
                      <p className="mt-1 text-xs text-slate-400">
                        Derniere sync : {node.last_sync_at ? new Date(node.last_sync_at).toLocaleString('fr-FR') : 'jamais'}
                      </p>
                    </div>
                  </div>
                  <div className="flex items-center gap-2">
                    <span className={`rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-wider ${node.is_online ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-600'}`}>
                      {node.is_online ? 'En ligne' : 'Hors ligne'}
                    </span>
                    <button
                      onClick={() => triggerSync(node.id)}
                      disabled={syncing === node.id || !node.is_online}
                      className="inline-flex items-center gap-1.5 rounded-lg border border-app-border bg-white px-3 py-1.5 text-xs font-bold text-slate-700 transition hover:bg-slate-50 disabled:opacity-50"
                    >
                      <RefreshCw className={`h-3.5 w-3.5 ${syncing === node.id ? 'animate-spin' : ''}`} />
                      {syncing === node.id ? 'Sync...' : 'Sync'}
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
                <Cpu className="h-5 w-5 text-brand-600" /> Nouveau Edge Node
              </h2>
              <button onClick={() => setShowAddModal(false)} className="text-slate-400 hover:text-slate-600">
                <X className="h-5 w-5" />
              </button>
            </div>
            <div className="space-y-3">
              <div>
                <label className="mb-1 block text-xs font-bold uppercase tracking-wider text-slate-400">Nom du site</label>
                <input
                  type="text"
                  placeholder="ex: Entrepot Nord"
                  value={newNode.name}
                  onChange={(e) => setNewNode({ ...newNode, name: e.target.value })}
                  className="w-full rounded-xl border border-app-border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500"
                />
              </div>
              <div>
                <label className="mb-1 block text-xs font-bold uppercase tracking-wider text-slate-400">Adresse du site</label>
                <input
                  type="text"
                  placeholder="ex: Zone Industrielle, Batiment A"
                  value={newNode.site_address}
                  onChange={(e) => setNewNode({ ...newNode, site_address: e.target.value })}
                  className="w-full rounded-xl border border-app-border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500"
                />
              </div>
              <div>
                <label className="mb-1 block text-xs font-bold uppercase tracking-wider text-slate-400">Mode</label>
                <select
                  value={newNode.mode}
                  onChange={(e) => setNewNode({ ...newNode, mode: e.target.value })}
                  className="w-full rounded-xl border border-app-border px-3 py-2 text-sm"
                >
                  <option value="hybrid">Hybride (recommande)</option>
                  <option value="offline">Offline total</option>
                  <option value="cloud">Cloud uniquement</option>
                </select>
              </div>
            </div>
            <div className="mt-5 flex gap-3">
              <button
                onClick={() => setShowAddModal(false)}
                className="flex-1 rounded-xl border border-app-border px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50"
              >
                Annuler
              </button>
              <button
                onClick={addNode}
                disabled={!newNode.name}
                className="flex-1 rounded-xl bg-brand-600 px-4 py-2 text-sm font-bold text-white hover:bg-brand-700 disabled:opacity-50"
              >
                Creer le node
              </button>
            </div>
          </div>
        </div>
      ) : null}
    </>
  );
}
