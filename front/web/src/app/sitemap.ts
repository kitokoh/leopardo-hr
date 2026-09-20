import type { MetadataRoute } from 'next';
import { getBlogPosts, type BlogPost } from '@/modules/vitrine/data/blog';
import { getAllPublicRestaurantSlugs } from '@/lib/restaurants-public-api';
import { getEnvConfig } from '@/modules/vitrine/lib/env';
import { getSiteUrl } from '@/lib/site';
import { getAllCaseStudySlugs } from '@/modules/vitrine/lib/case-studies';
import { getAlternativePages } from '@/modules/vitrine/data/alternatives';

const siteUrl = getSiteUrl();
const locales = ['fr', 'en', 'tr', 'ar'] as const;

function localizedAlternates(path: string) {
  const cleanPath = path === '/' ? '' : path;
  const defaultUrl = `${siteUrl}${cleanPath || '/'}`;

  return {
    languages: {
      ...Object.fromEntries(
        locales.map((locale) => [
          locale,
          locale === 'fr' ? defaultUrl : `${defaultUrl}?lang=${locale}`,
        ])
      ),
      // AI-SEO : variante de repli pour les langues non couvertes (aligné sur
      // seo.ts generateMetadata et les alternates du layout racine).
      'x-default': defaultUrl,
    },
  };
}

function page(
  path: string,
  changeFrequency: MetadataRoute.Sitemap[number]['changeFrequency'],
  priority: number,
  withAlternates = true,
): MetadataRoute.Sitemap[number] {
  return {
    url: `${siteUrl}${path === '/' ? '/' : path}`,
    changeFrequency,
    priority,
    // #4401 : /privacy et /terms sont 100 % FR (aucune localisation,
    // middleware sans x-vitrine-lang) — des variantes ?lang= fantômes
    // serviraient du HTML FR sous des URLs censées être en/tr/ar.
    ...(withAlternates ? { alternates: localizedAlternates(path) } : {}),
  };
}

// RESTO-903 (#7748) : le sitemap interroge l'annuaire public des restaurants
// (slugs dynamiques) — revalidation horaire raisonnable, les publications de
// profils sont rares et le fetch sous-jacent est déjà caché 60 s.
export const revalidate = 3600;

export default async function sitemap(): Promise<MetadataRoute.Sitemap> {
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
    // BC-25 : vitrine « Je suis restaurateur » — page publique indexable, elle
    // avait une route mais ni métadonnées dédiées ni entrée sitemap.
    page('/restaurateur', 'monthly', 0.6),
    // RESTO-903 (#7748) : annuaire public des restaurants (page SSR indexable).
    page('/restaurants', 'daily', 0.8),
    page('/privacy', 'yearly', 0.4, false),
    page('/terms', 'yearly', 0.4, false),
    // #7593 — mentions légales (page FR, comme privacy/terms).
    page('/mentions-legales', 'yearly', 0.3, false),
    // #7869 : hub SEO d'interception « Alternatives & comparatifs ».
    page('/alternatives', 'weekly', 0.8),
    page('/guides/rh-startup', 'monthly', 0.7),
    page('/guides/checklist-paie', 'monthly', 0.7),
    page('/guides/planning-employes', 'monthly', 0.7),
    // Audit expert 2026-08-15 (issue #2608) : pages manquantes ajoutées.
    // #4467 : /blog rejoint le bloc `if (enableBlog)` — la route répond 404
    // quand le flag est off (blog/layout.tsx → notFound()), le sitemap ne
    // doit pas publier d'URL qui 404 (régression #2647/#2904 : les posts
    // étaient gated, l'entrée statique oubliée).
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

  // RESTO-903 (#7748) : profils publics /restaurants/{slug} (SSR indexables,
  // GET /public/restaurants paginé). Best-effort : une API indisponible ne
  // casse JAMAIS le sitemap (liste vide) — les pages statiques restent servies.
  // Pas de variantes ?lang= : le contenu du profil (menu, description) vient
  // du restaurateur et n'est pas localisé par le catalogue.
  const restaurantPages: MetadataRoute.Sitemap = (await getAllPublicRestaurantSlugs()).map((slug) => ({
    url: `${siteUrl}/restaurants/${slug}`,
    changeFrequency: 'daily' as const,
    priority: 0.7,
  }));

  // #7869 : pages comparatives /alternatives/[slug] (statiques, indexables).
  const alternativePages: MetadataRoute.Sitemap = getAlternativePages('fr').map((p) => ({
    url: `${siteUrl}/alternatives/${p.slug}`,
    lastModified: p.reviewedAt ? new Date(p.reviewedAt) : undefined,
    changeFrequency: 'monthly' as const,
    priority: 0.7,
    alternates: localizedAlternates(`/alternatives/${p.slug}`),
  }));

  const allStatic = [...staticPages, ...caseStudyPages, ...restaurantPages, ...alternativePages];

  // Blog posts: source réelle = src/modules/vitrine/data/blog (getBlogPosts).
  // Déduplication des slugs toutes locales confondues : un seul entry par slug,
  // le post de la locale par défaut 'fr' gagne (itérée en premier).
  //
  // #2276 / #2904 (régression merge hybride #2469) : le blog est gated par
  // NEXT_PUBLIC_ENABLE_BLOG (blog/layout.tsx → notFound() si off → 404 live).
  // Le sitemap ne doit JAMAIS publier d'URLs /blog/* quand le flag est off,
  // sinon crawl 404 massif. `enableBlog` était relu mais inutilisé.
  // #4467 : l'entrée statique `/blog` (liste) rejoint aussi ce gate — elle
  // répondait 404 quand le flag est off alors que le sitemap la publiait.
  if (enableBlog) {
    const blogIndexPage: MetadataRoute.Sitemap = [page('/blog', 'weekly', 0.7)];
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

    return [...allStatic, ...blogIndexPage, ...blogPages];
  }

  return allStatic;
}
