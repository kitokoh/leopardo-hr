import type { MetadataRoute } from 'next';

import { PROTECTED_PREFIXES } from '@/lib/protected-prefixes';
import { SITE_URL as siteUrl } from '@/lib/site-url';

// Miroir du matcher middleware (src/middleware.ts) — routes session-protégées
// (#3375). Source unique : src/lib/protected-prefixes.ts (#3377).
const DISALLOWED = [
  ...PROTECTED_PREFIXES,
  '/admin',
  '/api',
  '/auth',
  // Portail client documents partagés (issue #5233) : URL tokenisées — jamais
  // indexables (RGPD, accès limité au destinataire du lien).
  '/documents/shared',
  '/.env',
  '/.git',
  '/node_modules',
];

export default function robots(): MetadataRoute.Robots {
  return {
    rules: [
      {
        userAgent: '*',
        allow: '/',
        disallow: DISALLOWED,
      },
      {
        // #3377 : un groupe dédié ÉCRASE le groupe `*` pour ce bot — sans
        // disallow explicite ici, Googlebot crawlait les 14 préfixes protégés.
        userAgent: 'Googlebot',
        allow: '/',
        disallow: DISALLOWED,
      },
      {
        userAgent: 'Bingbot',
        allow: '/',
        disallow: DISALLOWED,
      },
      {
        userAgent: 'MJ12bot',
        disallow: '/',
      },
      {
        userAgent: 'AhrefsBot',
        disallow: '/',
      },
      {
        userAgent: 'SemrushBot',
        disallow: '/',
      },
    ],
    sitemap: `${siteUrl}/sitemap.xml`,
  };
}
