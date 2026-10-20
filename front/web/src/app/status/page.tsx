'use client';
// ============================================================
// Status Page (#8700) — visibilité publique pour les bêta-testeurs
// ============================================================
//
// Objectif : pendant un incident, un bêta-testeur doit pouvoir lire « oui, on
// est au courant » sans écrire à personne. La page ne raconte rien d'elle-même :
// elle affiche le résultat de `GET /api/status`, qui sonde réellement chaque
// service côté serveur (voir `lib/platform-status.ts`).
//
// Design : volontairement sobre et identique aux autres écrans publics
// (`/offline`), avec un état par composant et la latence mesurée. Pas de
// « tout va bien » en gros vert sans preuve : chaque ligne porte son code HTTP.
import { useCallback, useEffect, useState, useSyncExternalStore } from 'react';
import { getCopy, getPreferredLocale, type AppLocale } from '@/lib/i18n';

type StatusState = 'operational' | 'degraded' | 'down' | 'unknown';

type StatusComponent = {
  id: string;
  labelKey: string;
  state: StatusState;
  probeUrl: string;
  httpCode: number | null;
  latencyMs: number | null;
  detail?: string | null;
};

type StatusSnapshot = {
  state: StatusState;
  checkedAt: string;
  components: StatusComponent[];
};

const emptySubscribe = () => () => {};

const STATE_STYLES: Record<StatusState, string> = {
  operational: 'bg-emerald-50 text-emerald-700 border-emerald-200',
  degraded: 'bg-amber-50 text-amber-800 border-amber-200',
  down: 'bg-red-50 text-red-700 border-red-200',
  unknown: 'bg-gray-50 text-gray-600 border-gray-200',
};

const STATE_DOT: Record<StatusState, string> = {
  operational: 'bg-emerald-500',
  degraded: 'bg-amber-500',
  down: 'bg-red-500',
  unknown: 'bg-gray-400',
};

export default function StatusPage() {
  const locale = useSyncExternalStore<AppLocale>(emptySubscribe, getPreferredLocale, () => 'fr');
  const labels = getCopy(locale).statusPage;

  const [snapshot, setSnapshot] = useState<StatusSnapshot | null>(null);
  const [error, setError] = useState(false);
  const [loading, setLoading] = useState(true);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const response = await fetch('/api/status', { cache: 'no-store' });
      if (!response.ok) throw new Error(`HTTP ${response.status}`);
      setSnapshot((await response.json()) as StatusSnapshot);
      setError(false);
    } catch {
      // Une erreur ici n'est PAS « le service est down » : c'est la page qui ne
      // peut pas lire son propre backend. On l'affiche comme telle plutôt que
      // de peindre tous les services en rouge, ce qui serait un faux signal.
      setError(true);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
    // Rafraîchissement automatique : sans ça, un onglet laissé ouvert pendant
    // un incident affiche indéfiniment l'état d'il y a trois heures.
    const timer = setInterval(() => void load(), 60_000);
    return () => clearInterval(timer);
  }, [load]);

  const stateLabel = (state: StatusState): string => {
    switch (state) {
      case 'operational':
        return labels.stateOperational;
      case 'degraded':
        return labels.stateDegraded;
      case 'down':
        return labels.stateDown;
      default:
        return labels.stateUnknown;
    }
  };

  const overallMessage = (state: StatusState): string => {
    switch (state) {
      case 'operational':
        return labels.overallOperational;
      case 'degraded':
        return labels.overallDegraded;
      case 'down':
        return labels.overallDown;
      default:
        return labels.overallUnknown;
    }
  };

  const componentLabel = (component: StatusComponent): string => {
    const tree = labels as unknown as Record<string, string>;
    return tree[component.labelKey] ?? component.id;
  };

  return (
    <main className="min-h-screen bg-transparent p-6">
      <div className="mx-auto max-w-3xl">
        <h1 className="text-2xl font-bold text-gray-900">{labels.title}</h1>
        <p className="mt-2 text-sm text-gray-500">{labels.subtitle}</p>

        {loading && !snapshot ? (
          <div className="mt-8 rounded-lg border border-gray-200 bg-white p-6 text-sm text-gray-500">
            {labels.refresh}…
          </div>
        ) : null}

        {error ? (
          <div className="mt-8 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
            {labels.overallUnknown}
          </div>
        ) : null}

        {snapshot ? (
          <>
            <div
              className={`mt-8 flex items-center gap-3 rounded-lg border p-4 text-sm font-medium ${STATE_STYLES[snapshot.state]}`}
            >
              <span className={`inline-block h-2.5 w-2.5 rounded-full ${STATE_DOT[snapshot.state]}`} />
              {overallMessage(snapshot.state)}
              <span className="ml-auto font-normal opacity-70">
                {labels.checkedAt} {new Date(snapshot.checkedAt).toLocaleTimeString(locale)}
              </span>
            </div>

            <div className="mt-6 overflow-hidden rounded-lg border border-gray-200 bg-white">
              <table className="min-w-full text-sm">
                <thead className="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                  <tr>
                    <th className="px-4 py-3 font-medium">{labels.componentColumn}</th>
                    <th className="px-4 py-3 font-medium">{labels.stateColumn}</th>
                    <th className="px-4 py-3 font-medium">{labels.latencyColumn}</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-100">
                  {snapshot.components.map((component) => (
                    <tr key={component.id}>
                      <td className="px-4 py-3">
                        <div className="font-medium text-gray-900">{componentLabel(component)}</div>
                        {/* L'URL sondée est affichée : le visiteur peut vérifier lui-même. */}
                        <a
                          href={component.probeUrl}
                          className="text-xs text-gray-400 hover:underline"
                          target="_blank"
                          rel="noreferrer"
                        >
                          {component.probeUrl}
                        </a>
                      </td>
                      <td className="px-4 py-3">
                        <span
                          className={`inline-flex items-center gap-2 rounded-full border px-2.5 py-1 text-xs font-medium ${STATE_STYLES[component.state]}`}
                        >
                          <span className={`inline-block h-1.5 w-1.5 rounded-full ${STATE_DOT[component.state]}`} />
                          {stateLabel(component.state)}
                          {component.httpCode !== null ? (
                            <span className="opacity-60">HTTP {component.httpCode}</span>
                          ) : null}
                        </span>
                      </td>
                      <td className="px-4 py-3 text-gray-500">
                        {component.latencyMs !== null ? `${component.latencyMs} ms` : '—'}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            <p className="mt-4 text-xs leading-relaxed text-gray-500">{labels.note}</p>

            <button
              onClick={() => void load()}
              className="mt-4 rounded-lg bg-blue-600 px-6 py-2 text-sm text-white hover:bg-blue-700"
            >
              {labels.refresh}
            </button>
          </>
        ) : null}
      </div>
    </main>
  );
}
