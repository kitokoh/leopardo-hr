/**
 * URL de base de l'API backend — résolution côté SERVEUR (#8022) pour les
 * route handlers (proxy compte acheteur + commandes).
 *
 * Chaîne de résolution : `MARKET_API_PROXY_TARGET` > `BACKEND_API_URL` >
 * `NEXT_PUBLIC_MARKET_API_BASE`. Les deux premières permettent un réseau
 * interne (Vercel → backend) différent de l'URL publique, sans toucher au
 * bundle navigateur ; la dernière est la convention existante du client
 * (lib/api.ts). La valeur retournée se termine toujours par `/api/v1`.
 *
 * Fail-fast (#7963) : variable absente ou invalide en production ou pendant
 * `next build` → erreur explicite, jamais de repli en dur vers un backend
 * distant. Seul le dev/test replie sur le backend LOCAL
 * `http://localhost:8000` (signalé par un console.warn).
 */

import { PHASE_PRODUCTION_BUILD } from "next/constants";

const LOCAL_FALLBACK_ORIGIN = "http://localhost:8000";

let warnedFallback = false;

function isNextBuildPhase(): boolean {
  return process.env.NEXT_PHASE === PHASE_PRODUCTION_BUILD;
}

function isProductionRuntime(): boolean {
  return (
    (process.env.NODE_ENV === "production" ||
      process.env.VERCEL_ENV === "production") &&
    !isNextBuildPhase()
  );
}

function withApiV1(origin: string): string {
  const trimmed = origin.trim().replace(/\/+$/, "");
  return trimmed.endsWith("/api/v1") ? trimmed : `${trimmed}/api/v1`;
}

export function resolveBackendBaseUrl(): string {
  const configured =
    process.env.MARKET_API_PROXY_TARGET ??
    process.env.BACKEND_API_URL ??
    process.env.NEXT_PUBLIC_MARKET_API_BASE;

  if (configured && configured.trim().length > 0) {
    try {
      // Valide que la valeur est bien une URL absolue http(s).
      const url = new URL(configured.trim());
      if (url.protocol === "http:" || url.protocol === "https:") {
        return withApiV1(configured);
      }
    } catch {
      // Tombe dans le fail-fast ci-dessous.
    }
    if (isNextBuildPhase() || isProductionRuntime()) {
      throw new Error(
        `[marketplace] URL backend invalide (« ${configured} »). Définissez ` +
          "MARKET_API_PROXY_TARGET, BACKEND_API_URL ou " +
          "NEXT_PUBLIC_MARKET_API_BASE avec une URL absolue http(s) (#7963).",
      );
    }
  }

  if (isNextBuildPhase() || isProductionRuntime()) {
    throw new Error(
      "[marketplace] URL backend absente. Définissez MARKET_API_PROXY_TARGET, " +
        "BACKEND_API_URL ou NEXT_PUBLIC_MARKET_API_BASE (ex. " +
        "https://api.exemple.com) — aucun repli en dur (#7963).",
    );
  }

  if (!warnedFallback) {
    warnedFallback = true;
    console.warn(
      "[marketplace] URL backend absente — repli dev/test sur " +
        `${LOCAL_FALLBACK_ORIGIN} (backend local ; interdit au build et en ` +
        "production, #7963).",
    );
  }
  return `${LOCAL_FALLBACK_ORIGIN}/api/v1`;
}
