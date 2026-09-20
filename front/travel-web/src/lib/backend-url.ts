/**
 * Centralisation de l'URL de l'API backend (audit #1701, durci par #7842).
 *
 * FICHIER MIROIR : `front/web/src/lib/backend-url.ts` et
 * `front/travel-web/src/lib/backend-url.ts` doivent rester STRICTEMENT
 * identiques (même contenu octet par octet) — toute évolution se fait dans
 * les deux copies en même temps.
 *
 * Chaîne de résolution serveur :
 *   `API_PROXY_TARGET` > `BACKEND_API_URL` > `NEXT_PUBLIC_API_URL`
 *
 * #7842 (audit sécurité 2026-09-20) : le fallback vers l'API Render de DEV
 * n'existe plus qu'EN DEV/TEST (poste local sans `.env`), avec un warning
 * console explicite. En production (`NODE_ENV === 'production'`), une
 * variable manquante lève une erreur actionnable au lieu de pointer
 * silencieusement des données réelles vers l'environnement de dev.
 */

export const DEFAULT_BACKEND_API_URL = 'https://gestionemployerbackend.onrender.com/api/v1';

function resolveWithFallbackPolicy(explicit: string | undefined, context: string, envHint: string): string {
  if (explicit) {
    return explicit.replace(/\/$/, '');
  }

  if (process.env.NODE_ENV === 'production') {
    throw new Error(
      `[backend-url] Aucune URL backend configurée (${context}) alors que NODE_ENV=production. ` +
        `Poser ${envHint} dans l'environnement du déploiement (Vercel/Render/Cloudflare) — ` +
        `le fallback silencieux vers l'API de dev a été retiré (audit #7842).`,
    );
  }

  console.warn(
    `[backend-url] ${envHint} absent — fallback DEV vers ${DEFAULT_BACKEND_API_URL} (${context}). ` +
      'Toléré uniquement en dev/test (audit #7842).',
  );

  return DEFAULT_BACKEND_API_URL.replace(/\/$/, '');
}

/** URL de base backend utilisée côté serveur (route handlers). */
export function resolveBackendBaseUrl(): string {
  return resolveWithFallbackPolicy(
    process.env.API_PROXY_TARGET || process.env.BACKEND_API_URL || process.env.NEXT_PUBLIC_API_URL,
    'résolution serveur',
    'API_PROXY_TARGET / BACKEND_API_URL / NEXT_PUBLIC_API_URL',
  );
}

/** URL de base backend utilisée côté client (navigateur). */
export function getApiBaseUrl(): string {
  return resolveWithFallbackPolicy(
    typeof process !== 'undefined' ? process.env.NEXT_PUBLIC_API_URL : undefined,
    'résolution client',
    'NEXT_PUBLIC_API_URL',
  );
}
