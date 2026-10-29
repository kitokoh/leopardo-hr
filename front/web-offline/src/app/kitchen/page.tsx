'use client';

import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
  KdsQueue,
  type KdsActionKind,
  type KdsStorage,
  type KdsTicket,
  type KdsTicketStatus,
} from '@/lib/kds-queue';
import { drainQueue, fetchKitchenTickets } from '@/lib/kds-sync';

/**
 * Leopardo Edge — Kitchen Display System (#8448).
 *
 * Écran cuisine offline-first : les tickets restent affichés et les gestes
 * (commencer / prêt / servi) restent possibles quand l'API est injoignable.
 * Les gestes sont mis en file localement puis rejoués au retour du réseau.
 *
 * Le composant ne contient AUCUNE logique de file : tout est dans
 * `kds-queue.ts` (état + idempotence) et `kds-sync.ts` (politique d'erreur),
 * qui sont testés sans navigateur. Ici on ne fait que du rendu et du câblage.
 */

const EDGE_API = process.env.NEXT_PUBLIC_EDGE_API ?? '';
const POLL_INTERVAL_MS = 15_000;

/** `localStorage` peut lever (mode privé, quota) : on dégrade sans casser. */
function browserStorage(): KdsStorage {
  return {
    read: () => {
      try {
        return window.localStorage.getItem('leopardo.kds.v1');
      } catch {
        return null;
      }
    },
    write: (value: string) => {
      try {
        window.localStorage.setItem('leopardo.kds.v1', value);
      } catch {
        // Quota plein ou stockage bloqué : la file reste en mémoire pour la
        // session. Mieux vaut un KDS qui fonctionne sans persistance qu'un
        // écran d'erreur pendant le service.
      }
    },
  };
}

const STATUS_LABEL: Record<KdsTicketStatus, string> = {
  received: 'Reçu',
  in_preparation: 'En préparation',
  ready: 'Prêt',
  served: 'Servi',
};

const STATUS_STYLE: Record<KdsTicketStatus, string> = {
  received: 'border-slate-600 bg-slate-800',
  in_preparation: 'border-amber-500/60 bg-amber-950/40',
  ready: 'border-emerald-500/60 bg-emerald-950/40',
  served: 'border-slate-700 bg-slate-900',
};

export default function KitchenPage() {
  const queueRef = useRef<KdsQueue | null>(null);
  const [tickets, setTickets] = useState<KdsTicket[]>([]);
  const [pendingCount, setPendingCount] = useState(0);
  const [online, setOnline] = useState(true);
  const [branchId, setBranchId] = useState<number | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  if (queueRef.current === null && typeof window !== 'undefined') {
    queueRef.current = new KdsQueue(browserStorage());
  }

  const refresh = useCallback(async () => {
    const queue = queueRef.current;
    if (!queue || branchId === null) {
      return;
    }

    // 1. Pousser les gestes en attente AVANT de relire : sinon la réponse
    //    serveur écraserait un geste local pas encore transmis.
    const sync = await drainQueue(queue, { baseUrl: EDGE_API });
    const reachable = sync.retry.length === 0;

    // 2. Relire la file serveur si elle répond.
    if (reachable) {
      const serverTickets = await fetchKitchenTickets(branchId, { baseUrl: EDGE_API });
      if (serverTickets) {
        queue.reconcile(serverTickets);
      } else {
        setOnline(false);
      }
    } else {
      setOnline(false);
    }

    if (reachable) {
      setOnline(true);
    }

    setTickets(queue.visibleTickets());
    setPendingCount(queue.load().pending.length);
  }, [branchId]);

  // Branche : paramètre d'URL, sinon dernière valeur connue. La file cuisine
  // est TOUJOURS bornée à une branche (contrat #6197) — pas de vue « toutes ».
  useEffect(() => {
    const params = new URLSearchParams(window.location.search);
    const raw = params.get('branch_id');
    const parsed = raw !== null && /^\d+$/.test(raw) ? Number(raw) : null;

    if (parsed !== null) {
      setBranchId(parsed);
      try {
        window.localStorage.setItem('leopardo.kds.branch', String(parsed));
      } catch {
        // sans persistance, la branche reste en mémoire pour la session
      }
      return;
    }

    try {
      const stored = window.localStorage.getItem('leopardo.kds.branch');
      if (stored && /^\d+$/.test(stored)) {
        setBranchId(Number(stored));
      }
    } catch {
      // rien à faire : l'écran demandera la branche
    }
  }, []);

  useEffect(() => {
    if (branchId === null) {
      return;
    }

    void refresh();
    const interval = setInterval(() => void refresh(), POLL_INTERVAL_MS);

    // Le retour du réseau est le signal le plus fiable pour vider la file :
    // attendre le prochain tick laisserait des gestes en attente 15 s de plus.
    const onOnline = () => void refresh();
    window.addEventListener('online', onOnline);

    return () => {
      clearInterval(interval);
      window.removeEventListener('online', onOnline);
    };
  }, [branchId, refresh]);

  const act = (orderId: string, kind: KdsActionKind) => {
    const queue = queueRef.current;
    if (!queue) {
      return;
    }

    const next = queue.enqueue(orderId, kind);

    if (next === null) {
      // Geste refusé (transition qui reculerait) : on le dit, on ne fait pas
      // semblant d'avoir réussi.
      setNotice('Ce ticket a déjà avancé — action ignorée.');
      return;
    }

    setNotice(null);
    setTickets(queue.visibleTickets());
    setPendingCount(next.pending.length);

    // Tentative immédiate ; si le réseau est absent, le geste reste en file.
    void refresh();
  };

  const columns = useMemo(() => {
    const byStatus: Record<string, KdsTicket[]> = {
      received: [],
      in_preparation: [],
      ready: [],
    };

    for (const ticket of tickets) {
      if (ticket.status in byStatus) {
        byStatus[ticket.status].push(ticket);
      }
    }

    return byStatus;
  }, [tickets]);

  if (branchId === null) {
    return (
      <div className="min-h-screen bg-slate-900 flex items-center justify-center p-6">
        <div className="bg-slate-800 border border-slate-700 rounded-xl p-6 max-w-md text-center">
          <h1 className="text-white font-semibold text-lg mb-2">Cuisine — Leopardo Edge</h1>
          <p className="text-slate-400 text-sm">
            Ouvrez cet écran avec <code className="text-slate-300">?branch_id=&lt;id&gt;</code> pour
            choisir la succursale.
          </p>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-slate-900 flex flex-col">
      <header className="bg-slate-800 border-b border-slate-700 px-6 py-4 flex items-center justify-between">
        <div className="flex items-center gap-2">
          <div className="w-8 h-8 bg-indigo-500 rounded-lg flex items-center justify-center font-bold text-sm">
            L
          </div>
          <span className="text-white font-semibold text-lg">Cuisine</span>
          <span className="text-slate-500 text-sm">· branche {branchId}</span>
        </div>
        <div className="flex items-center gap-4">
          {pendingCount > 0 && (
            <span className="text-amber-300 text-sm">
              {pendingCount} geste{pendingCount > 1 ? 's' : ''} en attente
            </span>
          )}
          <div className="flex items-center gap-2">
            <span
              className={`w-2.5 h-2.5 rounded-full ${online ? 'bg-green-500' : 'bg-amber-500'} animate-pulse`}
            />
            <span className="text-slate-300 text-sm">{online ? 'En ligne' : 'Hors ligne'}</span>
          </div>
        </div>
      </header>

      {notice && (
        <div className="bg-amber-950/60 border-b border-amber-700/50 px-6 py-2 text-amber-200 text-sm">
          {notice}
        </div>
      )}

      {!online && (
        <div className="bg-slate-800 border-b border-yellow-600/40 px-6 py-2 text-yellow-300 text-sm">
          Réseau indisponible — la cuisine continue, les gestes seront synchronisés au retour.
        </div>
      )}

      <main className="flex-1 grid grid-cols-1 md:grid-cols-3 gap-4 p-4">
        {(['received', 'in_preparation', 'ready'] as const).map((status) => (
          <section key={status} className="flex flex-col min-h-0">
            <h2 className="text-slate-300 text-sm font-semibold uppercase tracking-wide mb-2 px-1">
              {STATUS_LABEL[status]} ({columns[status].length})
            </h2>
            <div className="flex-1 space-y-3 overflow-y-auto">
              {columns[status].length === 0 && (
                <p className="text-slate-600 text-sm px-1">Aucun ticket.</p>
              )}
              {columns[status].map((ticket) => (
                <article
                  key={ticket.orderId}
                  className={`rounded-xl border p-4 ${STATUS_STYLE[ticket.status]}`}
                >
                  <div className="flex items-center justify-between mb-2">
                    <span className="text-white font-semibold">{ticket.reference}</span>
                    <span className="text-slate-400 text-xs">
                      {new Date(ticket.createdAt).toLocaleTimeString()}
                    </span>
                  </div>
                  <ul className="space-y-1 mb-3">
                    {ticket.lines.map((line) => (
                      <li key={line.id} className="text-slate-200 text-sm">
                        <span className="text-indigo-300 font-mono">{line.quantity}×</span>{' '}
                        {line.name}
                        {line.notes && (
                          <span className="block text-amber-300 text-xs pl-6">{line.notes}</span>
                        )}
                      </li>
                    ))}
                  </ul>
                  <div className="flex gap-2">
                    {ticket.status === 'received' && (
                      <button
                        onClick={() => act(ticket.orderId, 'start')}
                        className="flex-1 bg-amber-600 hover:bg-amber-500 text-white text-sm font-medium py-2 rounded-lg"
                      >
                        Commencer
                      </button>
                    )}
                    {ticket.status === 'in_preparation' && (
                      <button
                        onClick={() => act(ticket.orderId, 'ready')}
                        className="flex-1 bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-medium py-2 rounded-lg"
                      >
                        Prêt
                      </button>
                    )}
                    {ticket.status === 'ready' && (
                      <button
                        onClick={() => act(ticket.orderId, 'serve')}
                        className="flex-1 bg-slate-600 hover:bg-slate-500 text-white text-sm font-medium py-2 rounded-lg"
                      >
                        Servi
                      </button>
                    )}
                  </div>
                </article>
              ))}
            </div>
          </section>
        ))}
      </main>
    </div>
  );
}
