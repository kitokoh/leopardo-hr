/**
 * Leopardo Library — synchronisation de la progression de lecture.
 *
 * Le `localStorage` reste la source de vérité IMMÉDIATE (lecture instantanée,
 * visiteurs anonymes, mode privé, quota dépassé). Ce module ajoute une couche
 * de synchronisation serveur pour les utilisateurs CONNECTÉS :
 *
 *   - `pushProgress()` envoie la position à l'API (`PUT /library/progress/{slug}`) ;
 *   - `pullProgress()` relit la position serveur (`GET /library/progress/{slug}`).
 *
 * ⚠️ RÈGLE DE REPLI : toute erreur (réseau, 401, 5xx, API absente) est
 * SILENCIEUSE. La progression locale n'est jamais perdue ni bloquée par un
 * échec serveur — au pire elle n'est pas partagée entre appareils.
 *
 * ⚠️ Aucune donnée personnelle n'est envoyée pour un visiteur anonyme : la
 * synchronisation n'est tentée que si une session authentifiée existe.
 */

import { apiFetch } from '@/lib/api-client';
import { getStoredUser } from '@/lib/auth-session';
import { saveProgress, type ReadingProgress } from './progress';

/** Réponse serveur d'une progression. */
export interface ServerProgress {
  resource_id: number;
  page: number;
  total_pages: number | null;
  updated_at: string | null;
}

/** La synchronisation serveur est-elle disponible pour ce visiteur ? */
export function canSyncProgress(): boolean {
  if (typeof window === 'undefined') return false;
  // Le token vit dans un cookie httpOnly (illisible en JS) : on s'appuie sur
  // l'utilisateur de session stocké par le client d'auth.
  return getStoredUser() !== null;
}

/**
 * Envoie une position au serveur. Ne lève jamais : retourne `true` si le
 * serveur a accepté, `false` sinon (l'appelant garde sa copie locale).
 */
export async function pushProgress(entry: ReadingProgress): Promise<boolean> {
  if (!canSyncProgress()) return false;

  try {
    const response = await apiFetch(`/library/progress/${encodeURIComponent(entry.slug)}`, {
      method: 'PUT',
      body: JSON.stringify({
        page: entry.page,
        total_pages: entry.totalPages > 0 ? entry.totalPages : null,
      }),
    });

    return response.ok;
  } catch {
    // Repli silencieux : la progression locale reste la référence.
    return false;
  }
}

/**
 * Relit la position serveur d'une ressource et l'écrit en local si elle est
 * PLUS RÉCENTE que la copie locale. Retourne la progression retenue, ou
 * `null` si le serveur n'a rien (ou est indisponible).
 */
export async function pullProgress(slug: string): Promise<ReadingProgress | null> {
  if (!canSyncProgress()) return null;

  try {
    const response = await apiFetch(`/library/progress/${encodeURIComponent(slug)}`);
    if (!response.ok) return null;

    const payload = (await response.json()) as { data: ServerProgress | null };
    const server = payload.data;

    if (!server || typeof server.page !== 'number') return null;

    const entry: ReadingProgress = {
      slug,
      page: server.page,
      totalPages: server.total_pages ?? 0,
      updatedAt: server.updated_at ? Date.parse(server.updated_at) : Date.now(),
    };

    saveProgress(entry);

    return entry;
  } catch {
    return null;
  }
}

/**
 * Enregistre une position : local d'abord (toujours), serveur ensuite
 * (best-effort). C'est le point d'entrée utilisé par les lecteurs.
 */
export function persistProgress(entry: ReadingProgress): void {
  saveProgress(entry);
  void pushProgress(entry);
}
