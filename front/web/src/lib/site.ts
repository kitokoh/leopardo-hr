/**
 * URL canonique du site vitrine/dashboard (audit expert 2026-08-15, issue #2607).
 *
 * Avant : le défaut était `https://gestionemployer-backend.vercel.app` (domaine
 * de dev) répété dans sitemap.ts, robots.ts et les canonicals — faux canonicals
 * en production. Ici, une seule source de vérité : `NEXT_PUBLIC_SITE_URL` >
 * défaut `https://leopardo-rh.com` (domaine produit officiel).
 */
export const DEFAULT_SITE_URL = 'https://leopardo-rh.com';

export function getSiteUrl(): string {
  return (process.env.NEXT_PUBLIC_SITE_URL || DEFAULT_SITE_URL).replace(/\/+$/, '');
}
// Email de contact affiché sur la page contact (donnée d'affichage, non
// traduite — #2755 : les emails/URLs sont de la donnée, pas du texte UI).
export const CONTACT_EMAIL = 'contact@leopardo-rh.com';
