/**
 * BC-10 (#9220) — accès serveur à l'annuaire public des portails carrières.
 *
 * Même pattern que `restaurants-public-api.ts` : appel direct à l'API Laravel
 * depuis un Server Component, aucune auth, 404/panne → liste vide.
 *
 * Le repli sur liste vide est délibéré : un sitemap qui échoue à cause d'une
 * API indisponible priverait TOUT le site de son sitemap. Mieux vaut publier
 * les pages statiques et perdre temporairement les pages carrières.
 */

import { resolveBackendBaseUrl } from '@/lib/backend-url';

export interface PublicCareersEntry {
  slug: string;
  name: string;
  country: string | null;
  last_published_at: string | null;
}

/**
 * Entreprises ayant au moins une offre publiée.
 *
 * Best-effort : toute erreur rend une liste vide plutôt que de propager.
 */
export async function getPublicCareersDirectory(): Promise<PublicCareersEntry[]> {
  try {
    const response = await fetch(`${resolveBackendBaseUrl()}/public/careers`, {
      headers: { Accept: 'application/json' },
      // Le sitemap est régénéré toutes les heures : inutile de revalider plus
      // souvent, et un cache évite de marteler l'API à chaque crawl.
      next: { revalidate: 3600 },
    });

    if (!response.ok) {
      return [];
    }

    const payload = (await response.json()) as { data?: unknown };

    if (!Array.isArray(payload.data)) {
      return [];
    }

    return payload.data.filter(
      (entry): entry is PublicCareersEntry =>
        typeof entry === 'object' &&
        entry !== null &&
        typeof (entry as PublicCareersEntry).slug === 'string' &&
        (entry as PublicCareersEntry).slug !== '',
    );
  } catch {
    return [];
  }
}
