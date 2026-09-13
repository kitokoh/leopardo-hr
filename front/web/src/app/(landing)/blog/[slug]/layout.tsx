import { Metadata } from 'next';
import { headers } from 'next/headers';
import { notFound } from 'next/navigation';
import { SITE_URL } from '@/lib/site-url';
import { generateMetadata as generateSEOMetadata } from '@/modules/vitrine/lib/seo';
import { getBlogPost } from '@/modules/vitrine/data/blog';
import type { AppLocale } from '@/lib/i18n';
import { ArticleJsonLd, BreadcrumbJsonLd } from '@/components/JsonLd';
import { breadcrumbLabels, localizedUrl } from '@/lib/ai-search';

/**
 * #4611 : metadata PROPRE par article (title/description/canonical/hreflang +
 * og:type article). Avant : le layout du listing (canonical=/blog, « Blog &
 * Resources ») s'appliquait à tous les articles → soft-duplicates Google et
 * hreflang du sitemap contredits par le HTML. Le rendu client de la page est
 * inchangé (layout serveur, children pass-through).
 *
 * #AI-SEO : le JSON-LD Article et le fil d'Ariane sont désormais émis ICI,
 * côté serveur. Auparavant `ArticleJsonLd` vivait dans `BlogArticle` (composant
 * client) : le balisage n'existait qu'après exécution du JavaScript, donc
 * invisible pour les crawlers IA (GPTBot, ClaudeBot, PerplexityBot…) qui
 * n'exécutent pas JS.
 */
async function resolveLocale(): Promise<AppLocale> {
  // #4004 : ?lang= normalisé en en-tête x-vitrine-lang par le middleware.
  const headerList = await headers();
  return (headerList.get('x-vitrine-lang') ?? 'fr') as AppLocale;
}

export async function generateMetadata({
  params,
}: {
  params: Promise<{ slug: string }>;
}): Promise<Metadata> {
  const { slug } = await params;
  const lang = await resolveLocale();
  const post = getBlogPost(slug, lang);

  if (!post) {
    notFound();
  }

  return generateSEOMetadata({
    title: post.title,
    description: post.excerpt,
    ogImage: post.image,
    ogType: 'article',
    canonical: `${SITE_URL}/blog/${post.slug}`,
    locale: lang,
    publishedTime: post.date instanceof Date ? post.date.toISOString() : undefined,
  });
}

export default async function BlogArticleLayout({
  children,
  params,
}: {
  children: React.ReactNode;
  params: Promise<{ slug: string }>;
}) {
  const { slug } = await params;
  const lang = await resolveLocale();
  const post = getBlogPost(slug, lang);

  if (!post) {
    notFound();
  }

  const labels = breadcrumbLabels(lang);
  const articleUrl = localizedUrl(`/blog/${post.slug}`, lang);

  return (
    <>
      <ArticleJsonLd
        title={post.title}
        description={post.excerpt}
        url={articleUrl}
        image={new URL(post.image, SITE_URL).toString()}
        datePublished={new Date(post.date).toISOString()}
        author={post.author.name}
        inLanguage={lang}
      />
      <BreadcrumbJsonLd
        items={[
          { name: labels.home, url: localizedUrl('/', lang) },
          { name: labels.blog, url: localizedUrl('/blog', lang) },
          { name: post.title, url: articleUrl },
        ]}
      />
      {children}
    </>
  );
}
