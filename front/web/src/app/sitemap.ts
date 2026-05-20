import type { MetadataRoute } from 'next';
import { getAllPosts } from '@/lib/mdx';

const siteUrl = process.env.NEXT_PUBLIC_SITE_URL || 'https://leopardo.com';
const locales = ['fr', 'en', 'tr', 'ar'] as const;

function localizedAlternates(path: string) {
  const cleanPath = path === '/' ? '' : path;

  return {
    languages: Object.fromEntries(
      locales.map((locale) => [
        locale,
        locale === 'fr'
          ? `${siteUrl}${cleanPath || '/'}`
          : `${siteUrl}${cleanPath || '/'}?lang=${locale}`,
      ])
    ),
  };
}

function page(path: string, lastModified: Date, changeFrequency: MetadataRoute.Sitemap[number]['changeFrequency'], priority: number): MetadataRoute.Sitemap[number] {
  return {
    url: `${siteUrl}${path === '/' ? '/' : path}`,
    lastModified,
    changeFrequency,
    priority,
    alternates: localizedAlternates(path),
  };
}

export default function sitemap(): MetadataRoute.Sitemap {
  const today = new Date();

  const staticPages: MetadataRoute.Sitemap = [
    page('/', today, 'weekly', 1.0),
    page('/employes', today, 'weekly', 0.9),
    page('/documents', today, 'weekly', 0.9),
    page('/comptabilite', today, 'weekly', 0.9),
    page('/marketing', today, 'weekly', 0.9),
    page('/pricing', today, 'monthly', 0.8),
    page('/demo', today, 'monthly', 0.8),
    page('/integrations', today, 'monthly', 0.75),
    page('/about', today, 'monthly', 0.7),
    page('/changelog', today, 'weekly', 0.65),
    page('/blog', today, 'weekly', 0.8),
    page('/privacy', today, 'yearly', 0.4),
    page('/terms', today, 'yearly', 0.4),
    page('/guides/rh-startup', today, 'monthly', 0.7),
    page('/guides/checklist-paie', today, 'monthly', 0.7),
    page('/guides/planning-employes', today, 'monthly', 0.7),
  ];

  const blogPosts = getAllPosts();
  const blogPages: MetadataRoute.Sitemap = blogPosts.map((post) => ({
    url: `${siteUrl}/blog/${post.slug}`,
    lastModified: post.date ? new Date(post.date) : today,
    changeFrequency: 'monthly' as const,
    priority: 0.6,
    alternates: localizedAlternates(`/blog/${post.slug}`),
  }));

  return [...staticPages, ...blogPages];
}
