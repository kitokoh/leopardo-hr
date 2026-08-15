import type { MetadataRoute } from 'next';

import { SITE_URL as siteUrl } from '@/lib/site-url';

export default function robots(): MetadataRoute.Robots {
  return {
    rules: [
      {
        userAgent: '*',
        allow: '/',
        disallow: [
        // Miroir du matcher middleware (src/middleware.ts) — routes session-protégées (#3375).
        '/admin', '/api', '/auth', '/dashboard',
        '/absences', '/attendance', '/billing', '/contracts', '/employees',
        '/partner', '/payroll', '/reports', '/training', '/settings',
        '/smart-attendance', '/social', '/social-marketing',
        '/.env', '/.git', '/node_modules',
      ],
      },
      {
        userAgent: 'Googlebot',
        allow: '/',
      },
      {
        userAgent: 'Bingbot',
        allow: '/',
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
