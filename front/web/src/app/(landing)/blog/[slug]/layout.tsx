import { SITE_URL } from '@/lib/site-url';
import { Metadata } from 'next';
import { headers } from 'next/headers';
import { blogPosts } from '@/modules/vitrine/data/blog';
import { generateMetadata as generateSEOMetadata, localizedPageMetadata, resolveSsrLang } from '@/modules/vitrine/lib/seo';

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
  const headerList = await headers();
  const lang = headerList.get('x-lang') ?? resolveSsrLang(headerList.get('accept-language'));
  const post = blogPosts.find((p) => p.slug === slug);

  if (!post) {
    const meta = localizedPageMetadata('blog', lang);
    return { title: meta.title, description: meta.description };
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
