import { NextResponse } from 'next/server';

const siteUrl = process.env.NEXT_PUBLIC_SITE_URL || 'https://leopardo.com';

interface SitemapEntry {
  url: string;
  lastmod?: string;
  changefreq?: 'always' | 'hourly' | 'daily' | 'weekly' | 'monthly' | 'yearly' | 'never';
  priority?: number;
}

export async function GET() {
  const entries: SitemapEntry[] = [
    // Landing Page
    {
      url: `${siteUrl}/`,
      lastmod: new Date().toISOString().split('T')[0],
      changefreq: 'weekly',
      priority: 1.0,
    },

    // Module Pages
    {
      url: `${siteUrl}/employes`,
      lastmod: new Date().toISOString().split('T')[0],
      changefreq: 'weekly',
      priority: 0.9,
    },
    {
      url: `${siteUrl}/documents`,
      lastmod: new Date().toISOString().split('T')[0],
      changefreq: 'weekly',
      priority: 0.9,
    },
    {
      url: `${siteUrl}/comptabilite`,
      lastmod: new Date().toISOString().split('T')[0],
      changefreq: 'weekly',
      priority: 0.9,
    },
    {
      url: `${siteUrl}/marketing`,
      lastmod: new Date().toISOString().split('T')[0],
      changefreq: 'weekly',
      priority: 0.9,
    },

    // Info Pages
    {
      url: `${siteUrl}/pricing`,
      lastmod: new Date().toISOString().split('T')[0],
      changefreq: 'monthly',
      priority: 0.8,
    },
    {
      url: `${siteUrl}/about`,
      lastmod: new Date().toISOString().split('T')[0],
      changefreq: 'monthly',
      priority: 0.7,
    },

    // Blog Pages
    {
      url: `${siteUrl}/blog`,
      lastmod: new Date().toISOString().split('T')[0],
      changefreq: 'weekly',
      priority: 0.8,
    },

    // Blog Articles
    {
      url: `${siteUrl}/blog/guide-complet-rh-startup`,
      lastmod: new Date().toISOString().split('T')[0],
      changefreq: 'monthly',
      priority: 0.7,
    },
    {
      url: `${siteUrl}/blog/automatiser-paie-2024`,
      lastmod: new Date().toISOString().split('T')[0],
      changefreq: 'monthly',
      priority: 0.7,
    },
    {
      url: `${siteUrl}/blog/gestion-absences-efficace`,
      lastmod: new Date().toISOString().split('T')[0],
      changefreq: 'monthly',
      priority: 0.7,
    },
    {
      url: `${siteUrl}/blog/productivite-rh-outils`,
      lastmod: new Date().toISOString().split('T')[0],
      changefreq: 'monthly',
      priority: 0.6,
    },
    {
      url: `${siteUrl}/blog/tendances-rh-2024`,
      lastmod: new Date().toISOString().split('T')[0],
      changefreq: 'monthly',
      priority: 0.6,
    },
    {
      url: `${siteUrl}/blog/ia-rh-futur`,
      lastmod: new Date().toISOString().split('T')[0],
      changefreq: 'monthly',
      priority: 0.6,
    },
    {
      url: `${siteUrl}/blog/checklist-paie-2024`,
      lastmod: new Date().toISOString().split('T')[0],
      changefreq: 'monthly',
      priority: 0.6,
    },
    {
      url: `${siteUrl}/blog/modele-planning-employes`,
      lastmod: new Date().toISOString().split('T')[0],
      changefreq: 'monthly',
      priority: 0.6,
    },
    {
      url: `${siteUrl}/blog/conformite-rgpd-documents`,
      lastmod: new Date().toISOString().split('T')[0],
      changefreq: 'monthly',
      priority: 0.6,
    },
    {
      url: `${siteUrl}/blog/email-marketing-rh`,
      lastmod: new Date().toISOString().split('T')[0],
      changefreq: 'monthly',
      priority: 0.6,
    },

    // Guides Pages
    {
      url: `${siteUrl}/guides/rh-startup`,
      lastmod: new Date().toISOString().split('T')[0],
      changefreq: 'monthly',
      priority: 0.7,
    },
    {
      url: `${siteUrl}/guides/checklist-paie`,
      lastmod: new Date().toISOString().split('T')[0],
      changefreq: 'monthly',
      priority: 0.7,
    },
    {
      url: `${siteUrl}/guides/planning-employes`,
      lastmod: new Date().toISOString().split('T')[0],
      changefreq: 'monthly',
      priority: 0.7,
    },
  ];

  const xml = generateSitemapXML(entries);

  return new NextResponse(xml, {
    headers: {
      'Content-Type': 'application/xml; charset=utf-8',
      'Cache-Control': 'public, s-maxage=3600, stale-while-revalidate=86400',
    },
  });
}

function generateSitemapXML(entries: SitemapEntry[]): string {
  const xml = `<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
${entries
  .map(
    (entry) => `  <url>
    <loc>${escapeXml(entry.url)}</loc>
    ${entry.lastmod ? `<lastmod>${entry.lastmod}</lastmod>` : ''}
    ${entry.changefreq ? `<changefreq>${entry.changefreq}</changefreq>` : ''}
    ${entry.priority ? `<priority>${entry.priority}</priority>` : ''}
  </url>`
  )
  .join('\n')}
</urlset>`;

  return xml;
}

function escapeXml(str: string): string {
  return str
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&apos;');
}
