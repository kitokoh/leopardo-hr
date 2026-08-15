/**
 * Leopardo Edge — logique de health-check (issue #3971).
 *
 * Extraite de `src/app/page.tsx` pour être testable : la machine à états
 * du polling Edge (checking → online / error / offline) est une logique
 * critique (contrat #3719/#3772) qui n'avait AUCUNE couverture de test.
 */

export type SyncStatus = 'checking' | 'online' | 'offline' | 'error';

export interface EdgeHealth {
  status: string;
  node_id?: string;
  pending_sync?: number;
  last_sync?: string;
}

export interface HealthCheckResult {
  status: SyncStatus;
  health: EdgeHealth | null;
}

export const HEALTH_TIMEOUT_MS = 4_000;
export const HEALTH_POLL_INTERVAL_MS = 30_000;

export const EDGE_API_DEFAULT = 'http://leopardo.local:7878';

/**
 * Interroge `/api/v1/edge/health` sur le node Edge.
 *
 * - `res.ok` → `online` + payload santé
 * - réponse non-OK → `error` (le node répond, mais en erreur)
 * - réseau/timeout → `offline`
 *
 * @param fetcher injectable pour les tests (défaut : fetch global).
 */
export async function checkEdgeHealth(
  baseUrl: string = EDGE_API_DEFAULT,
  fetcher: typeof fetch = fetch
): Promise<HealthCheckResult> {
  try {
    const res = await fetcher(`${baseUrl}/api/v1/edge/health`, {
      signal: AbortSignal.timeout(HEALTH_TIMEOUT_MS),
    });

    if (res.ok) {
      const health = (await res.json()) as EdgeHealth;

      return { status: 'online', health };
    }

    return { status: 'error', health: null };
  } catch {
    return { status: 'offline', health: null };
  }
}
