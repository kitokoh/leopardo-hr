/**
 * URL de base de l'API backend — source de vérité unique (audit #1701,
 * durcissement fail-fast #7842).
 *
 * ⚠️ SYNCHRONISATION MANUELLE (#7842) : ce fichier existe en DEUX copies
 * volontairement IDENTIQUES (pas de package npm partagé dans ce dépôt,
 * factorisation hors périmètre) :
 *   - front/web/src/lib/backend-url.ts
 *   - front/travel-web/src/lib/backend-url.ts
 * Toute modification doit être reportée à l'identique dans l'autre copie.
 *
 * Chaîne de résolution côté serveur (route handlers, SSR) :
 *   `API_PROXY_TARGET` > `BACKEND_API_URL` > `NEXT_PUBLIC_API_URL`
 * Côté client (navigateur) : `NEXT_PUBLIC_API_URL` uniquement.
 *
 * Fail-fast (#7842) : en PRODUCTION (`NODE_ENV === 'production'` ou
 * `VERCEL_ENV === 'production'`), l'absence de variable lève une erreur
 * explicite — plus AUCUN repli silencieux vers l'API dev Render
 * `gestionemployerbackend.onrender.com`. En dev/test, le repli est conservé
 * mais signalé par un `console.warn`.
 */

export const DEFAULT_BACKEND_API_URL =
  "https://gestionemployerbackend.onrender.com/api/v1";

function isProductionRuntime(): boolean {
  return (
    process.env.NODE_ENV === "production" ||
    process.env.VERCEL_ENV === "production"
  );
}

let warnedServerFallback = false;
let warnedClientFallback = false;

/** URL de base backend utilisée côté serveur (route handlers, SSR). */
export function resolveBackendBaseUrl(): string {
  const configured =
    process.env.API_PROXY_TARGET ||
    process.env.BACKEND_API_URL ||
    process.env.NEXT_PUBLIC_API_URL;

  if (!configured) {
    if (isProductionRuntime()) {
      throw new Error(
        "[backend-url] Aucune URL d'API backend configurée en production. " +
          "Définissez API_PROXY_TARGET, BACKEND_API_URL ou NEXT_PUBLIC_API_URL " +
          "(ex. https://api.exemple.com/api/v1) dans l'environnement de " +
          "déploiement. Le repli silencieux vers l'API dev onrender.com a été " +
          "retiré (#7842).",
      );
    }
    if (!warnedServerFallback) {
      warnedServerFallback = true;
      console.warn(
        "[backend-url] API_PROXY_TARGET / BACKEND_API_URL / NEXT_PUBLIC_API_URL " +
          `absentes — repli dev/test sur ${DEFAULT_BACKEND_API_URL} ` +
          "(interdit en production, #7842).",
      );
    }
    return DEFAULT_BACKEND_API_URL.replace(/\/$/, "");
  }

  return configured.replace(/\/$/, "");
}

/** URL de base backend utilisée côté client (navigateur). */
export function getApiBaseUrl(): string {
  const configured =
    typeof process !== "undefined" ? process.env.NEXT_PUBLIC_API_URL : undefined;

  if (!configured) {
    if (isProductionRuntime()) {
      throw new Error(
        "[backend-url] NEXT_PUBLIC_API_URL absente en production. Définissez " +
          "NEXT_PUBLIC_API_URL (ex. https://api.exemple.com/api/v1) dans " +
          "l'environnement de build. Le repli silencieux vers l'API dev " +
          "onrender.com a été retiré (#7842).",
      );
    }
    if (!warnedClientFallback) {
      warnedClientFallback = true;
      console.warn(
        "[backend-url] NEXT_PUBLIC_API_URL absente — repli dev/test sur " +
          `${DEFAULT_BACKEND_API_URL} (interdit en production, #7842).`,
      );
    }
    return DEFAULT_BACKEND_API_URL.replace(/\/$/, "");
  }

  return configured.replace(/\/$/, "");
}
