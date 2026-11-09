/**
 * Progression de lecture — synchronisation serveur (#9242).
 *
 * Le `localStorage` reste la source de vérité pour les visiteurs ANONYMES
 * (`progress.ts`). Ce module ajoute la synchronisation pour un employé
 * authentifié : sa position le suit d'un appareil à l'autre.
 *
 * Contrat de fusion (le point délicat) :
 * - **la position la plus récente gagne** (`updatedAt` le plus grand) — jamais
 *   « le serveur écrase toujours », sinon une lecture commencée hors ligne
 *   serait perdue au retour du réseau ;
 * - **le serveur ne reçoit jamais une position plus ancienne** que la sienne :
 *   on ne pousse que si le local est strictement plus récent ;
 * - **aucune erreur ne remonte à l'interface** : un échec réseau laisse le
 *   `localStorage` intact et la lecture continue (dégradation silencieuse,
 *   même contrat que `progress.ts`).
 */

import { loadProgress, saveProgress, type ReadingProgress } from './progress';

/** Forme renvoyée par `GET /library/progress`. */
export interface ServerProgress {
  slug: string;
  page: number;
  total_pages: number;
  updated_at: string | null;
}

/** Convertit une entrée serveur en entrée locale (horodatage en ms). */
export function fromServer(entry: ServerProgress): ReadingProgress {
  const parsed = entry.updated_at ? Date.parse(entry.updated_at) : Number.NaN;

  return {
    slug: entry.slug,
    page: entry.page,
    totalPages: entry.total_pages,
    updatedAt: Number.isNaN(parsed) ? 0 : parsed,
  };
}

/**
 * Fusionne les progressions serveur et locales.
 *
 * Pour chaque slug présent d'un côté ou de l'autre, la version la plus récente
 * (`updatedAt`) est retenue. Le résultat est trié du plus récent au plus ancien,
 * comme `mergeProgress`.
 */
export function mergeWithServer(
  local: ReadingProgress[],
  server: ReadingProgress[],
): ReadingProgress[] {
  const bySlug = new Map<string, ReadingProgress>();

  for (const entry of [...server, ...local]) {
    const existing = bySlug.get(entry.slug);
    if (!existing || entry.updatedAt > existing.updatedAt) {
      bySlug.set(entry.slug, entry);
    }
  }

  return [...bySlug.values()].sort((a, b) => b.updatedAt - a.updatedAt);
}

/**
 * Vrai si la position locale doit être poussée vers le serveur.
 *
 * On ne pousse que si le local est **strictement plus récent** : pousser une
 * position identique ou plus ancienne écraserait une lecture faite sur un autre
 * appareil.
 */
export function shouldPush(local: ReadingProgress, server: ReadingProgress | null): boolean {
  if (!server) return true;
  return local.updatedAt > server.updatedAt;
}

/** Options d'appel HTTP (injectables pour les tests). */
export interface SyncOptions {
  /** Base de l'API (ex. `https://api.example.com/api/v1`). */
  baseUrl: string;
  /** Jeton d'authentification (Sanctum). */
  token: string;
  /** Implémentation de `fetch` (injectable pour les tests). */
  fetchImpl?: typeof fetch;
}

/**
 * Récupère les progressions serveur de l'employé.
 *
 * Retourne `[]` en cas d'échec : la synchronisation est un bonus, jamais un
 * prérequis à la lecture.
 */
export async function fetchServerProgress(options: SyncOptions): Promise<ReadingProgress[]> {
  const doFetch = options.fetchImpl ?? fetch;

  try {
    const response = await doFetch(`${options.baseUrl}/library/progress`, {
      headers: {
        Accept: 'application/json',
        Authorization: `Bearer ${options.token}`,
      },
    });

    if (!response.ok) return [];

    const body = (await response.json()) as { data?: ServerProgress[] };
    if (!Array.isArray(body.data)) return [];

    return body.data.map(fromServer);
  } catch {
    return [];
  }
}

/**
 * Pousse une position vers le serveur.
 *
 * Retourne `true` si le serveur a accepté, `false` sinon (réseau, 4xx, 5xx).
 * N'échoue jamais : l'appelant n'a pas à gérer d'exception.
 */
export async function pushProgress(
  entry: ReadingProgress,
  options: SyncOptions,
): Promise<boolean> {
  const doFetch = options.fetchImpl ?? fetch;

  try {
    const response = await doFetch(
      `${options.baseUrl}/library/progress/${encodeURIComponent(entry.slug)}`,
      {
        method: 'PUT',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          Authorization: `Bearer ${options.token}`,
        },
        body: JSON.stringify({ page: entry.page, total_pages: entry.totalPages }),
      },
    );

    return response.ok;
  } catch {
    return false;
  }
}

/**
 * Synchronisation complète : récupère, fusionne, persiste localement, puis
 * pousse les positions locales plus récentes que celles du serveur.
 *
 * Retourne l'état fusionné (celui que l'interface doit afficher).
 */
export async function syncProgress(options: SyncOptions): Promise<ReadingProgress[]> {
  const local = loadProgress();
  const server = await fetchServerProgress(options);
  const merged = mergeWithServer(local, server);

  // Persiste la fusion : une position serveur plus récente remplace la locale.
  for (const entry of merged) {
    saveProgress(entry);
  }

  // Pousse uniquement ce que le serveur ne connaît pas ou connaît plus ancien.
  const serverBySlug = new Map(server.map((entry) => [entry.slug, entry]));
  await Promise.all(
    merged
      .filter((entry) => shouldPush(entry, serverBySlug.get(entry.slug) ?? null))
      .map((entry) => pushProgress(entry, options)),
  );

  return merged;
}
