import { NextResponse } from 'next/server';
import { getAllBlogPosts } from '@/lib/mdx';

const siteUrl = process.env.NEXT_PUBLIC_SITE_URL || 'https://leopardo.com';

interface SitemapEntry {
  url: string;
  lastmod?: string;
  changefreq?: 'always' | 'hourly' | 'daily' | 'weekly' | 'monthly' | 'yearly' | 'never';
  priority?: number;
}

export async function GET() {
  const today = new Date().toISOString().split('T')[0];

  // Static pages
  const staticEntries: SitemapEntry[] = [
    { url: `${siteUrl}/`, lastmod: today, changefreq: 'weekly', priority: 1.0 },
    { url: `${siteUrl}/employes`, lastmod: today, changefreq: 'weekly', priority: 0.9 },
    { url: `${siteUrl}/documents`, lastmod: today, changefreq: 'weekly', priority: 0.9 },
    { url: `${siteUrl}/comptabilite`, lastmod: today, changefreq: 'weekly', priority: 0.9 },
    { url: `${siteUrl}/marketing`, lastmod: today, changefreq: 'weekly', priority: 0.9 },
    { url: `${siteUrl}/pricing`, lastmod: today, changefreq: 'monthly', priority: 0.8 },
    { url: `${siteUrl}/changelog`, lastmod: today, changefreq: 'weekly', priority: 0.65 },
    { url: `${siteUrl}/demo`, lastmod: today, changefreq: 'monthly', priority: 0.8 },
    { url: `${siteUrl}/about`, lastmod: today, changefreq: 'monthly', priority: 0.7 },
    { url: `${siteUrl}/blog`, lastmod: today, changefreq: 'weekly', priority: 0.8 },
    { url: `${siteUrl}/privacy`, lastmod: today, changefreq: 'yearly', priority: 0.4 },
    { url: `${siteUrl}/terms`, lastmod: today, changefreq: 'yearly', priority: 0.4 },
    { url: `${siteUrl}/guides/rh-startup`, lastmod: today, changefreq: 'monthly', priority: 0.7 },
    { url: `${siteUrl}/guides/checklist-paie`, lastmod: today, changefreq: 'monthly', priority: 0.7 },
    { url: `${siteUrl}/guides/planning-employes`, lastmod: today, changefreq: 'monthly', priority: 0.7 },
  ];

  // Dynamic blog posts from MDX content
  const blogPosts = getAllBlogPosts();
  const blogEntries: SitemapEntry[] = blogPosts.map((post) => ({
    url: `${siteUrl}/blog/${post.slug}`,
    lastmod: post.date || today,
    changefreq: 'monthly' as const,
    priority: 0.6,
  }));

  const entries = [...staticEntries, ...blogEntries];

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
