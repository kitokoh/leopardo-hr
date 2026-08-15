import { SITE_URL } from '@/lib/site-url';
import { Metadata } from 'next';
import { headers } from 'next/headers';
import { cache } from 'react';
import { getBlogPost } from '@/modules/vitrine/data/blog';
import { generateMetadata as generateSEOMetadata } from '@/modules/vitrine/lib/seo';
import type { AppLocale } from '@/lib/i18n';

// Issue #3923 : les métadonnées suivent la locale SSR (Accept-Language) —
// plus de titre/description FR pour les visiteurs en/tr/ar.
const getSsrLocale = cache(async (): Promise<AppLocale> => {
  const headerList = await headers();
  const base = (headerList.get('accept-language') ?? '')
    .split(',')[0]
    .trim()
    .toLowerCase()
    .slice(0, 2);
  return (['fr', 'en', 'ar', 'tr'] as const).includes(base as AppLocale) ? (base as AppLocale) : 'fr';
});

interface BlogArticleLayoutProps {
  params: Promise<{
    slug: string;
  }>;
  children: React.ReactNode;
}

export async function generateMetadata({
  params,
}: BlogArticleLayoutProps): Promise<Metadata> {
  const { slug } = await params;
  const locale = await getSsrLocale();
  const post = getBlogPost(slug, locale);

  if (!post) {
    const notFoundCopy: Record<AppLocale, { title: string; description: string }> = {
      fr: { title: 'Article non trouvé', description: "L'article que vous recherchez n'existe pas." },
      en: { title: 'Article not found', description: 'The article you are looking for does not exist.' },
      tr: { title: 'Makale bulunamadi', description: 'Aradiginiz makale mevcut degil.' },
      ar: { title: 'المقال غير موجود', description: 'المقال الذي تبحث عنه غير موجود.' },
    };
    const nf = notFoundCopy[locale];
    return { title: nf.title, description: nf.description };
  }

  return generateSEOMetadata({
    title: post.title,
    description: post.excerpt,
    keywords: post.tags,
    ogImage: post.image,
    ogType: 'article',
    canonical: `${SITE_URL}/blog/${post.slug}`,
    publishedTime: post.date.toISOString(),
    author: post.author.name,
  });
}

export default function BlogArticleLayout({
  children,
}: BlogArticleLayoutProps) {
  return children;
}
