'use client';

import { useState, useEffect } from 'react';
import {
  checkEdgeHealth,
  HEALTH_POLL_INTERVAL_MS,
  type EdgeHealth,
  type SyncStatus,
} from '@/lib/edge-health';

const EDGE_API = process.env.NEXT_PUBLIC_EDGE_API ?? 'http://leopardo.local:7878';

export default function HomePage() {
  const [syncStatus, setSyncStatus] = useState<SyncStatus>('checking');
  const [health, setHealth] = useState<EdgeHealth | null>(null);
  const [checking, setChecking] = useState(false);

  const checkEdge = async () => {
    setChecking(true);
    setSyncStatus('checking');
    const result = await checkEdgeHealth(EDGE_API);
    setHealth(result.health);
    setSyncStatus(result.status);
    setChecking(false);
  };

  useEffect(() => {
    checkEdge();
    const interval = setInterval(checkEdge, HEALTH_POLL_INTERVAL_MS);
    return () => clearInterval(interval);
  }, []);

  const statusColor = {
    checking: 'bg-yellow-500',
    online: 'bg-green-500',
    offline: 'bg-gray-500',
    error: 'bg-red-500',
  }[syncStatus];

  const statusLabel = {
    checking: 'Vérification…',
    online: 'Edge en ligne',
    offline: 'Hors ligne',
    error: 'Erreur de connexion',
  }[syncStatus];

  return (
    <div className="min-h-screen bg-slate-900 flex flex-col">
      {/* Header */}
      <header className="bg-slate-800 border-b border-slate-700 px-6 py-4 flex items-center justify-between">
        <div className="flex items-center gap-2">
          <div className="w-8 h-8 bg-indigo-500 rounded-lg flex items-center justify-center font-bold text-sm">L</div>
          <span className="text-white font-semibold text-lg">Leopardo Edge</span>
        </div>
        <div className="flex items-center gap-2">
          <span className={`w-2.5 h-2.5 rounded-full ${statusColor} animate-pulse`} />
          <span className="text-slate-300 text-sm">{statusLabel}</span>
        </div>
      </header>

      {/* Main */}
      <main className="flex-1 px-6 py-8 max-w-2xl mx-auto w-full">
        {/* Status card */}
        <div className="bg-slate-800 rounded-xl border border-slate-700 p-6 mb-6">
          <h2 className="text-white font-semibold text-base mb-4">Statut du node Edge</h2>
          {health ? (
            <div className="space-y-3">
              <div className="flex justify-between text-sm">
                <span className="text-slate-400">Node ID</span>
                <span className="text-white font-mono">{health.node_id ?? '—'}</span>
              </div>
              <div className="flex justify-between text-sm">
                <span className="text-slate-400">Statut</span>
                <span className={`font-medium ${syncStatus === 'online' ? 'text-green-400' : 'text-red-400'}`}>
                  {health.status}
                </span>
              </div>
              <div className="flex justify-between text-sm">
                <span className="text-slate-400">En attente de sync</span>
                <span className="text-white">{health.pending_sync ?? '—'}{typeof health.pending_sync === 'number' ? ' enregistrement(s)' : ''}</span>
              </div>
              {health.last_sync && (
                <div className="flex justify-between text-sm">
                  <span className="text-slate-400">Dernière synchronisation</span>
                  <span className="text-white">{new Date(health.last_sync).toLocaleString(typeof window !== 'undefined' ? navigator.language : 'fr-FR')}</span>
                </div>
              )}
            </div>
          ) : (
            <p className="text-slate-400 text-sm">
              {syncStatus === 'checking'
                ? 'Connexion à http://leopardo.local…'
                : 'Node Edge non joignable. Les pointages seront enregistrés localement.'}
            </p>
          )}
        </div>

        {/* Actions */}
        <div className="flex gap-3">
          <button
            onClick={checkEdge}
            disabled={checking}
            className="flex-1 bg-slate-700 hover:bg-slate-600 disabled:opacity-50 text-white text-sm font-medium py-2.5 px-4 rounded-lg transition-colors"
          >
            {checking ? 'Vérification…' : 'Actualiser'}
          </button>
          <button
            disabled
            title="La synchronisation nécessite un nodeId et un jeton Edge authentifié."

            className="flex-1 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-40 disabled:cursor-not-allowed text-white text-sm font-medium py-2.5 px-4 rounded-lg transition-colors"
          >
            Synchronisation disponible depuis le node Edge authentifié
          </button>
        </div>

        {/* Offline notice */}
        {syncStatus === 'offline' && (
          <div className="mt-6 bg-slate-800 border border-yellow-600/40 rounded-xl p-4 text-sm text-yellow-300">
            ⚠️ Le node Edge local n&apos;est pas joignable. Assurez-vous d&apos;être connecté
            au réseau local ou que le service Edge est démarré sur le serveur.
          </div>
        )}
      </main>

      {/* Footer */}
      <footer className="px-6 py-4 text-center text-slate-600 text-xs">
        Leopardo RH — Interface Edge locale · {EDGE_API}
      </footer>
    </div>
  );
}
