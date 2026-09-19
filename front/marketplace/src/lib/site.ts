/**
 * URL publique canonique du site Leopardo Marché (#7809).
 *
 * Surcharge par `NEXT_PUBLIC_SITE_URL` (préviews Vercel/Cloudflare) ; défaut
 * = URL de production documentée dans docs/ops/DEPLOYMENT_URLS.md (#7815).
 */
export const SITE_URL = (
  process.env.NEXT_PUBLIC_SITE_URL || "https://leopardo-marche.vercel.app"
).replace(/\/+$/, "");
