/**
 * Résolution de l'URL d'API pour les adaptateurs des surfaces publiques.
 *
 * ## Pourquoi ce fichier existe
 *
 * Les gateways des surfaces sont appelées **des deux côtés** : en SSR depuis les
 * pages serveur, et depuis le navigateur par les composants interactifs
 * (checkout, compte, réservation, suivi). Les deux contextes n'ont pas la même
 * bonne réponse :
 *
 * - **Serveur** : il faut l'URL absolue du backend (`resolveBackendBaseUrl`),
 *   sinon `fetch` n'a pas de base.
 * - **Navigateur** : il faut le **proxy same-origin** `/api/v1` (#7297). Appeler
 *   l'API en direct depuis le navigateur contourne le proxy, donc le cookie
 *   `HttpOnly` n'est jamais posé ni relu — la session est perdue et les appels
 *   authentifiés répondent 401. C'est exactement le bug constaté le 2026-09-13
 *   sur `front/web`.
 *
 * ## Ce que ce n'est pas
 *
 * Ce n'est pas une seconde source de vérité : côté serveur on délègue
 * intégralement à `resolveBackendBaseUrl()`, qui porte le fail-fast de
 * production (#7842/#7963). Ici on n'ajoute que la branche navigateur.
 *
 * L'échappatoire `NEXT_PUBLIC_API_DIRECT=true` est conservée à l'identique de
 * `lib/api-client.ts` : elle existe pour les cas de debug explicites, jamais par
 * défaut.
 */

import { resolveBackendBaseUrl } from '@/lib/backend-url';

/** Base du proxy same-origin — la seule voie navigateur par défaut. */
export const SAME_ORIGIN_API_BASE = '/api/v1';

/**
 * URL de base à utiliser par un adaptateur de surface.
 *
 * Rend `/api/v1` dans le navigateur, l'URL absolue du backend côté serveur.
 */
export function resolveSurfaceApiBaseUrl(): string {
  if (typeof window !== 'undefined') {
    if (process.env.NEXT_PUBLIC_API_DIRECT === 'true' && process.env.NEXT_PUBLIC_API_URL) {
      return process.env.NEXT_PUBLIC_API_URL.replace(/\/$/, '');
    }

    return SAME_ORIGIN_API_BASE;
  }

  return resolveBackendBaseUrl();
}
