/**
 * URL de base de l'API backend — source de vérité unique (audit #1701,
 * durcissement fail-fast #7842, retrait du repli en dur #7963).
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
 * Fail-fast (#7842, étendu au build par #7963) : en PRODUCTION
 * (`NODE_ENV === 'production'` ou `VERCEL_ENV === 'production'`), l'absence
 * de variable lève une erreur explicite — plus AUCUN repli silencieux vers
 * l'API dev Render (instance onrender.com historique, #7842), et plus
 * aucune URL backend distante codée en dur (#7963 : l'ancienne constante
 * pointant le backend DEV Render est SUPPRIMÉE).
 *
 * Build Next (#7963) : pendant `next build` (`NEXT_PHASE ===
 * PHASE_PRODUCTION_BUILD`, posé par Next dans le processus de build ET ses
 * workers « collect page data » — même détection que `site-url.ts`),
 * l'absence de variable lève AUSSI une erreur explicite : un build sans URL
 * backend configurée doit ÉCHOUER plutôt que de figer un repli dans le
 * bundle. (Historique : #7842 avait introduit l'exception inverse — jamais
 * de throw au build — pour la CI lighthouse ; #7963 la renverse : les
 * workflows CI définissent désormais NEXT_PUBLIC_API_URL explicitement,
 * ex. http://localhost:8000/api/v1 pour les builds de test.)
 *
 * Dev/test : le repli sur le backend LOCAL `http://localhost:8000/api/v1`
 * (`php artisan serve` sur le poste) est conservé mais signalé par un
 * `console.warn` — une cible de poste, jamais une instance partagée (aucun
 * mélange de données possible). Le fail-fast reste intact là où il protège
 * réellement : au RUNTIME serveur (première résolution d'URL lors d'une
 * requête, où `NEXT_PHASE` n'est plus posé) et côté client (variable non
 * `NEXT_PUBLIC_`, donc jamais inlinée dans le bundle navigateur).
 */

import { PHASE_PRODUCTION_BUILD } from "next/constants";

/**
 * Repli dev/test UNIQUEMENT (#7963) : le backend Laravel local
 * (`php artisan serve`, port 8000). Plus aucune URL distante en dur.
 */
export const DEFAULT_BACKEND_API_URL = "http://localhost:8000/api/v1";

function isNextBuildPhase(): boolean {
  return (
    typeof process !== "undefined" &&
    process.env.NEXT_PHASE === PHASE_PRODUCTION_BUILD
  );
}

function isProductionRuntime(): boolean {
  return (
    (process.env.NODE_ENV === "production" ||
      process.env.VERCEL_ENV === "production") &&
    !isNextBuildPhase()
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
    if (isNextBuildPhase()) {
      throw new Error(
        "[backend-url] Aucune URL d'API backend configurée pendant `next build`. " +
          "Définissez API_PROXY_TARGET, BACKEND_API_URL ou NEXT_PUBLIC_API_URL " +
          "dans l'environnement de build (ex. http://localhost:8000/api/v1 en " +
          "CI, l'URL réelle de l'API en déploiement) — le build échoue " +
          "explicitement plutôt que de figer un repli (#7963).",
      );
    }
    if (isProductionRuntime()) {
      throw new Error(
        "[backend-url] Aucune URL d'API backend configurée en production. " +
          "Définissez API_PROXY_TARGET, BACKEND_API_URL ou NEXT_PUBLIC_API_URL " +
          "(ex. https://api.exemple.com/api/v1) dans l'environnement de " +
          "déploiement. Le repli silencieux vers l'API dev onrender.com a été " +
          "retiré (#7842) et plus aucune URL distante n'est codée en dur (#7963).",
      );
    }
    if (!warnedServerFallback) {
      warnedServerFallback = true;
      console.warn(
        "[backend-url] API_PROXY_TARGET / BACKEND_API_URL / NEXT_PUBLIC_API_URL " +
          `absentes — repli dev/test sur ${DEFAULT_BACKEND_API_URL} (backend ` +
          "local ; interdit au build et au runtime de production, #7842/#7963).",
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
    if (isNextBuildPhase()) {
      throw new Error(
        "[backend-url] NEXT_PUBLIC_API_URL absente pendant `next build`. " +
          "Définissez NEXT_PUBLIC_API_URL dans l'environnement de build " +
          "(ex. http://localhost:8000/api/v1 en CI, l'URL réelle de l'API en " +
          "déploiement) — le build échoue explicitement plutôt que de figer " +
          "un repli (#7963).",
      );
    }
    if (isProductionRuntime()) {
      throw new Error(
        "[backend-url] NEXT_PUBLIC_API_URL absente en production. Définissez " +
          "NEXT_PUBLIC_API_URL (ex. https://api.exemple.com/api/v1) dans " +
          "l'environnement de build. Le repli silencieux vers l'API dev " +
          "onrender.com a été retiré (#7842) et plus aucune URL distante " +
          "n'est codée en dur (#7963).",
      );
    }
    if (!warnedClientFallback) {
      warnedClientFallback = true;
      console.warn(
        "[backend-url] NEXT_PUBLIC_API_URL absente — repli dev/test sur " +
          `${DEFAULT_BACKEND_API_URL} (backend local ; interdit au build et ` +
          "au runtime de production, #7842/#7963).",
      );
    }
    return DEFAULT_BACKEND_API_URL.replace(/\/$/, "");
  }

  return configured.replace(/\/$/, "");
}
