/**
 * #9580 (BC-41 BRANDING) — accès serveur au contrat public d'une Brand.
 *
 * ## La SEULE couche qui fait du réseau
 *
 * Même patron que `lib/restaurants-public-api.ts` et `lib/showcase-public-api.ts` :
 * appel direct à l'API Laravel depuis un Server Component, aucune auth, 404
 * fail-closed → `null`. On ne reprend pas `api-client` ici : ce dernier porte
 * la session, le refresh de token et le proxy authentifié, dont une surface
 * anonyme n'a aucun besoin — le charger pour trois `fetch` publics
 * embarquerait du code de session dans une page publique.
 *
 * ## `null` plutôt que `throw`
 *
 * Un `throw` en SSR produit une page d'erreur 500 pour un visiteur qui a
 * simplement tapé un slug inexistant. `null` laisse la page appeler
 * `notFound()`, ce qui donne un 404 propre et indexable comme tel.
 */

import { resolveBackendBaseUrl } from '@/lib/backend-url';

import { parsePublicBrand, type PublicBrand } from '../domain/brand';

/** Cache court : une Brand publiée doit apparaître vite, sans marteler l'API. */
const REVALIDATE_SECONDS = 60;

/**
 * Récupère une Brand publique par son slug.
 *
 * Le `lang` est passé en query string : l'API résout la locale rendue (avec
 * repli sur la locale par défaut de la Brand), donc le front n'a pas à
 * décider quelle traduction existe.
 */
export async function fetchPublicBrand(
  slug: string,
  lang?: string,
): Promise<PublicBrand | null> {
  const trimmed = slug.trim();

  // Refus local d'un slug vide : inutile d'aller interroger l'API pour
  // obtenir le 404 qu'on sait déjà.
  if (trimmed === '') {
    return null;
  }

  const query = lang ? `?lang=${encodeURIComponent(lang)}` : '';

  try {
    const response = await fetch(
      `${resolveBackendBaseUrl()}/public/brands/${encodeURIComponent(trimmed)}${query}`,
      {
        headers: { Accept: 'application/json' },
        next: { revalidate: REVALIDATE_SECONDS },
      },
    );

    if (!response.ok) {
      return null;
    }

    const payload = (await response.json()) as { data?: unknown };

    // On parse même ce que l'API renvoie : un DTO partiel (déploiement
    // partiel, champ retiré) doit aboutir à un 404, pas à une page dont un
    // champ est `undefined` au rendu.
    return parsePublicBrand(payload.data);
  } catch {
    return null;
  }
}

/**
 * Récupère une Brand publique par le domaine courant (domaine personnalisé).
 *
 * Utilisé quand l'application est servie sur le host de la Brand : le serveur
 * web route le domaine vers Next, et l'en-tête `Host` porte l'identité.
 */
export async function fetchPublicBrandByHost(
  host: string,
  lang?: string,
): Promise<PublicBrand | null> {
  const trimmed = host.trim();

  if (trimmed === '') {
    return null;
  }

  const query = lang ? `?lang=${encodeURIComponent(lang)}` : '';

  try {
    const response = await fetch(
      `${resolveBackendBaseUrl()}/public/brands/by-host${query}`,
      {
        headers: { Accept: 'application/json', 'X-Forwarded-Host': trimmed },
        next: { revalidate: REVALIDATE_SECONDS },
      },
    );

    if (!response.ok) {
      return null;
    }

    const payload = (await response.json()) as { data?: unknown };

    return parsePublicBrand(payload.data);
  } catch {
    return null;
  }
}
