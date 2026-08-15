/**
 * SITE_URL centralisé (QA 2026-08-15, #2656).
 *
 * Avant : 8 copies du fallback, dont `http://localhost:3000` (canonicals
 * pointant sur localhost en build par défaut) et le domaine Vercel
 * « emprunté » `gestionemployer-backend.vercel.app` — explicitement interdit
 * dans les données structurées (issue #1775 : il évoque une entreprise US
 * sans rapport ; le domaine de marque officiel recommandé est
 * `www.leopardo-rh.com`, voir docs/DEPLOYMENT_PRODUCTION.md).
 *
 * Ordre de résolution :
 *   1. NEXT_PUBLIC_SITE_URL — l'URL de marque réelle (à poser au déploiement,
 *      ex. https://www.leopardo-rh.com une fois le domaine en ligne).
 *   2. Domaine de marque documenté (fallback de build, jamais localhost en
 *      production) — constant partagée depuis ./site (#3190 : source unique).
 *   3. localhost en développement uniquement (les canonicals n'ont pas
 *      d'impact SEO en dev).
 */

import { DEFAULT_SITE_URL } from './site';

export function getSiteUrl(): string {
  const explicit = process.env.NEXT_PUBLIC_SITE_URL;

  if (explicit && /^https?:\/\//i.test(explicit)) {
    return explicit.replace(/\/+$/, '');
  }

  if (process.env.NODE_ENV === 'development') {
    return 'http://localhost:3000';
  }

  return DEFAULT_SITE_URL;
}

/** Forme normalisée (sans slash final) de l'URL du site. */
export const SITE_URL = getSiteUrl();
