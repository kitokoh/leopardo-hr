import { Metadata } from 'next';
import { headers } from 'next/headers';
import { notFound } from 'next/navigation';
import { SITE_URL } from '@/lib/site-url';
import { generateMetadata as generateSEOMetadata } from '@/modules/vitrine/lib/seo';
import { getBlogPost } from '@/modules/vitrine/data/blog';
import type { AppLocale } from '@/lib/i18n';

/**
 * #4611 : metadata PROPRE par article (title/description/canonical/hreflang +
 * og:type article). Avant : le layout du listing (canonical=/blog, « Blog &
 * Resources ») s'appliquait à tous les articles → soft-duplicates Google et
 * hreflang du sitemap contredits par le HTML. Le rendu client de la page est
 * inchangé (layout serveur, children pass-through).
 */
export async function generateMetadata({
  params,
}: {
  params: Promise<{ slug: string }>;
}): Promise<Metadata> {
  const { slug } = await params;
  // #4004 : ?lang= normalisé en en-tête x-vitrine-lang par le middleware.
  const headerList = await headers();
  const lang = (headerList.get('x-vitrine-lang') ?? 'fr') as AppLocale;
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

export default function BlogArticleLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return children;
}
