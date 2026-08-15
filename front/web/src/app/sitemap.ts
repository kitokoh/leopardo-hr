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

function page(path: string, changeFrequency: MetadataRoute.Sitemap[number]['changeFrequency'], priority: number): MetadataRoute.Sitemap[number] {
  return {
    url: `${siteUrl}${path === '/' ? '/' : path}`,
    changeFrequency,
    priority,
    alternates: localizedAlternates(path),
  };
}

export default function sitemap(): MetadataRoute.Sitemap {
  // #3807 : lastModified stable — la génération par requête (new Date()) faisait
  // churner les lastmod chaque jour sans changement de contenu. Les pages
  // statiques n'émettent plus de lastmod ; seuls les posts blog gardent une
  // date réelle (date de publication).
  const { enableBlog } = getEnvConfig();

  const staticPages: MetadataRoute.Sitemap = [
    page('/', 'weekly', 1.0),
    page('/employes', 'weekly', 0.9),
    page('/documents', 'weekly', 0.9),
    page('/comptabilite', 'weekly', 0.9),
    page('/marketing', 'weekly', 0.9),
    page('/pricing', 'monthly', 0.8),
    page('/demo', 'monthly', 0.8),
    page('/integrations', 'monthly', 0.75),
    page('/about', 'monthly', 0.7),
    page('/changelog', 'weekly', 0.65),
    page('/docs', 'monthly', 0.7),
    page('/download', 'monthly', 0.75),
    page('/contact', 'monthly', 0.6),
    page('/faq', 'monthly', 0.6),
    page('/testimonials', 'monthly', 0.6),
    page('/case-studies', 'monthly', 0.6),
    page('/videos', 'monthly', 0.55),
    page('/branding', 'monthly', 0.5),
    page('/careers', 'monthly', 0.5),
    page('/mobile', 'monthly', 0.6),
    page('/privacy', 'yearly', 0.4),
    page('/terms', 'yearly', 0.4),
    page('/guides/rh-startup', 'monthly', 0.7),
    page('/guides/checklist-paie', 'monthly', 0.7),
    page('/guides/planning-employes', 'monthly', 0.7),
    // Audit expert 2026-08-15 (issue #2608) : pages manquantes ajoutées.
    page('/blog', 'weekly', 0.7),
    page('/offline', 'monthly', 0.4),
  ];

  // #3807 : /signup et /checkout sont noindex (pageMetadata.signup/checkout.robots
  // = "noindex, follow") — les publier dans le sitemap envoie des signaux
  // contradictoires aux crawlers. Retirées du sitemap.
  //
  // #3807 : les études de cas individuelles (pages indexables) étaient absentes
  // du sitemap malgré getAllCaseStudySlugs() — crawl 404 évité, URLs exposées.
  const caseStudyPages: MetadataRoute.Sitemap = getAllCaseStudySlugs().map((slug) => ({
    url: `${siteUrl}/case-studies/${slug}`,
    changeFrequency: 'monthly' as const,
    priority: 0.55,
    alternates: localizedAlternates(`/case-studies/${slug}`),
  }));

  const allStatic = [...staticPages, ...caseStudyPages];

  // Blog posts: source réelle = src/modules/vitrine/data/blog (getBlogPosts).
  // Déduplication des slugs toutes locales confondues : un seul entry par slug,
  // le post de la locale par défaut 'fr' gagne (itérée en premier).
  //
  // #2276 / #2904 (régression merge hybride #2469) : le blog est gated par
  // NEXT_PUBLIC_ENABLE_BLOG (blog/layout.tsx → notFound() si off → 404 live).
  // Le sitemap ne doit JAMAIS publier d'URLs /blog/* quand le flag est off,
  // sinon crawl 404 massif. `enableBlog` était relu mais inutilisé.
  if (enableBlog) {
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
      lastModified: post.date ? new Date(post.date) : undefined,
      changeFrequency: 'monthly' as const,
      priority: 0.6,
      alternates: localizedAlternates(`/blog/${post.slug}`),
    }));

    return [...allStatic, ...blogPages];
  }

  return allStatic;
}
