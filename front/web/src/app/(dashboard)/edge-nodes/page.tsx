// ============================================================
// Edge Nodes Dashboard Page — Priority 3.2
// Manages Leopardo Edge nodes for the company
// ============================================================

'use client';

import { useState, useEffect } from 'react';
import { ApiError, apiFetch } from '@/lib/api-client';

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

interface SyncLog {
  id: string;
  direction: string;
  status: string;
  records_sent: number;
  records_received: number;
  conflicts_detected: number;
  started_at: string;
  finished_at: string | null;
}

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
      console.error('Failed to fetch edge nodes', err);
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
      alert(`Sync terminé — envoyés: ${data.data?.records_sent ?? 0}, conflits: ${data.data?.conflicts_detected ?? 0}`);
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
      alert(err instanceof ApiError ? err.message : 'Erreur lors de la création du node');
    }
  }

  function statusBadge(node: EdgeNode) {
    if (!node.is_online) return <span className="badge badge-offline">Hors ligne</span>;
    if (node.mode === 'offline') return <span className="badge badge-local">Local</span>;
    return <span className="badge badge-online">En ligne</span>;
  }

  function modeBadge(mode: string) {
    const colors: Record<string, string> = {
      cloud: 'bg-blue-100 text-blue-800',
      hybrid: 'bg-orange-100 text-orange-800',
      offline: 'bg-gray-100 text-gray-800',
    };
    return (
      <span className={`px-2 py-0.5 rounded text-xs font-medium ${colors[mode] ?? ''}`}>
        {mode}
      </span>
    );
  }

  if (loading) return <div className="p-8 text-center">Chargement des Edge nodes...</div>;

  return (
    <div className="p-6 max-w-6xl mx-auto">
      {loadError && (
        <div className="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
          {loadError}
        </div>
      )}
      {/* Header */}
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">🐆 Edge Nodes</h1>
          <p className="text-gray-500 text-sm mt-1">
            Gérez les nœuds locaux pour le mode offline-first
          </p>
        </div>
        <button
          onClick={() => setShowAddModal(true)}
          className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium"
        >
          + Nouveau Node
        </button>
      </div>

      {/* Install command banner */}
      {installCommand && (
        <div className="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg">
          <p className="text-green-800 font-medium text-sm mb-2">
            ✅ Node créé ! Copie et exécute cette commande sur ton serveur local :
          </p>
          <code className="block bg-white p-3 rounded border text-xs font-mono break-all">
            {installCommand}
          </code>
          <button
            onClick={() => { navigator.clipboard.writeText(installCommand); }}
            className="mt-2 text-xs text-green-700 underline"
          >
            Copier
          </button>
        </div>
      )}

      {/* Stats */}
      <div className="grid grid-cols-4 gap-4 mb-6">
        {[
          { label: 'Total nodes', value: nodes.length },
          { label: 'En ligne', value: nodes.filter(n => n.is_online).length },
          { label: 'Hors ligne', value: nodes.filter(n => !n.is_online).length },
          { label: 'Licences valides', value: nodes.filter(n => n.license_valid).length },
        ].map(stat => (
          <div key={stat.label} className="bg-white rounded-lg border p-4">
            <p className="text-gray-500 text-xs">{stat.label}</p>
            <p className="text-2xl font-bold mt-1">{stat.value}</p>
          </div>
        ))}
      </div>

      {/* Nodes list */}
      {nodes.length === 0 ? (
        <div className="text-center py-16 bg-white rounded-lg border border-dashed">
          <p className="text-gray-400 text-lg">Aucun Edge node configuré</p>
          <p className="text-gray-400 text-sm mt-2">
            Crée un nouveau node pour activer le mode offline-first
          </p>
        </div>
      ) : (
        <div className="space-y-4">
          {nodes.map(node => (
            <div key={node.id} className="bg-white rounded-lg border p-5 flex items-center justify-between">
              <div className="flex items-center gap-4">
                <div className={`w-3 h-3 rounded-full ${node.is_online ? 'bg-green-500' : 'bg-red-400'}`} />
                <div>
                  <div className="flex items-center gap-2">
                    <span className="font-semibold text-gray-900">{node.name}</span>
                    {modeBadge(node.mode)}
                    {!node.license_valid && (
                      <span className="px-2 py-0.5 rounded text-xs bg-red-100 text-red-700">
                        Licence expirée
                      </span>
                    )}
                  </div>
                  <p className="text-sm text-gray-500">
                    {node.site_address ?? 'Adresse non renseignée'} · v{node.edge_version}
                  </p>
                  <p className="text-xs text-gray-400 mt-1">
                    Dernière sync : {node.last_sync_at
                      ? new Date(node.last_sync_at).toLocaleString('fr-FR')
                      : 'jamais'
                    }
                  </p>
                </div>
              </div>
              <div className="flex items-center gap-2">
                {statusBadge(node)}
                <button
                  onClick={() => triggerSync(node.id)}
                  disabled={syncing === node.id || !node.is_online}
                  className="px-3 py-1.5 text-sm bg-orange-50 text-orange-700 border border-orange-200 rounded hover:bg-orange-100 disabled:opacity-50"
                >
                  {syncing === node.id ? '⏳ Sync...' : '🔄 Sync'}
                </button>
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Add Node Modal */}
      {showAddModal && (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
          <div className="bg-white rounded-xl p-6 w-full max-w-md shadow-xl">
            <h2 className="text-lg font-bold mb-4">Nouveau Edge Node</h2>
            <div className="space-y-3">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Nom du site</label>
                <input
                  type="text"
                  placeholder="ex: Entrepôt Nord"
                  value={newNode.name}
                  onChange={e => setNewNode({ ...newNode, name: e.target.value })}
                  className="w-full border rounded-lg px-3 py-2 text-sm"
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Adresse du site</label>
                <input
                  type="text"
                  placeholder="ex: Zone Industrielle, Bâtiment A"
                  value={newNode.site_address}
                  onChange={e => setNewNode({ ...newNode, site_address: e.target.value })}
                  className="w-full border rounded-lg px-3 py-2 text-sm"
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Mode</label>
                <select
                  value={newNode.mode}
                  onChange={e => setNewNode({ ...newNode, mode: e.target.value })}
                  className="w-full border rounded-lg px-3 py-2 text-sm"
                >
                  <option value="hybrid">Hybride (recommandé)</option>
                  <option value="offline">Offline total</option>
                  <option value="cloud">Cloud uniquement</option>
                </select>
              </div>
            </div>
            <div className="flex gap-3 mt-5">
              <button
                onClick={() => setShowAddModal(false)}
                className="flex-1 px-4 py-2 border rounded-lg text-sm hover:bg-gray-50"
              >
                Annuler
              </button>
              <button
                onClick={addNode}
                disabled={!newNode.name}
                className="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700 disabled:opacity-50"
              >
                Créer le node
              </button>
            </div>
          </div>
        </div>
      )}

      <style jsx>{`
        .badge {
          padding: 2px 8px;
          border-radius: 9999px;
          font-size: 11px;
          font-weight: 500;
        }
        .badge-online { background: #dcfce7; color: #166534; }
        .badge-offline { background: #fee2e2; color: #991b1b; }
        .badge-local { background: #fef3c7; color: #92400e; }
      `}</style>
    </div>
  );
}
