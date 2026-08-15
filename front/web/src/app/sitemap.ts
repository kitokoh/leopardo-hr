import type { MetadataRoute } from 'next';
import { getBlogPosts, type BlogPost } from '@/modules/vitrine/data/blog';
import { getEnvConfig } from '@/modules/vitrine/lib/env';
import { getSiteUrl } from '@/lib/site';
import { getAllCaseStudySlugs } from '@/modules/vitrine/lib/case-studies';

const siteUrl = getSiteUrl();
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
  const { enableBlog } = getEnvConfig();

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
    page('/docs', today, 'monthly', 0.7),
    page('/download', today, 'monthly', 0.75),
    page('/contact', today, 'monthly', 0.6),
    page('/faq', today, 'monthly', 0.6),
    page('/testimonials', today, 'monthly', 0.6),
    page('/case-studies', today, 'monthly', 0.6),
    ...getAllCaseStudySlugs().map((slug) => page(`/case-studies/${slug}`, today, 'monthly', 0.6)),
    page('/videos', today, 'monthly', 0.55),
    page('/branding', today, 'monthly', 0.5),
    page('/careers', today, 'monthly', 0.5),
    page('/mobile', today, 'monthly', 0.6),
    page('/privacy', today, 'yearly', 0.4),
    page('/terms', today, 'yearly', 0.4),
    page('/guides/rh-startup', today, 'monthly', 0.7),
    page('/guides/checklist-paie', today, 'monthly', 0.7),
    page('/guides/planning-employes', today, 'monthly', 0.7),
    // #3378 : /blog gated sur NEXT_PUBLIC_ENABLE_BLOG (404 si off), /offline
    // retiré (route interne du service worker — pas une page crawlable),
    // /share déjà retiré (#3399/#3355, route POST-only).
    page('/signup', today, 'monthly', 0.6),
    page('/checkout', today, 'monthly', 0.5),
  ];

  // Blog posts: source réelle = src/modules/vitrine/data/blog (getBlogPosts).
  // Déduplication des slugs toutes locales confondues : un seul entry par slug,
  // le post de la locale par défaut 'fr' gagne (itérée en premier).
  //
  // #2276 / #2904 (régression merge hybride #2469) : le blog est gated par
  // NEXT_PUBLIC_ENABLE_BLOG (blog/layout.tsx → notFound() si off → 404 live).
  // Le sitemap ne doit JAMAIS publier d'URLs /blog/* quand le flag est off,
  // sinon crawl 404 massif. `enableBlog` était relu mais inutilisé.
  if (enableBlog) {
    staticPages.push(page('/blog', today, 'weekly', 0.7));

    const postsBySlug = new Map<string, BlogPost>();
    for (const locale of locales) {
      for (const post of getBlogPosts(locale)) {
        if (!postsBySlug.has(post.slug)) {
          postsBySlug.set(post.slug, post);
        }
      }
    }

    const blogPages: MetadataRoute.Sitemap = [...postsBySlug.values()].map((post) => ({
      url: `${siteUrl}/blog/${post.slug}`,
      lastModified: post.date ? new Date(post.date) : today,
      changeFrequency: 'monthly' as const,
      priority: 0.6,
      alternates: localizedAlternates(`/blog/${post.slug}`),
    }));

    return [...staticPages, ...blogPages];
  }

  return staticPages;
}
