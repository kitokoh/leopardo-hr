import { Metadata } from 'next';
import { blogPosts } from '@/modules/vitrine/data/blog';
import { generateMetadata as generateSEOMetadata } from '@/modules/vitrine/lib/seo';

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
  const post = blogPosts.find((p) => p.slug === slug);

  if (!post) {
    return {
      title: 'Article non trouvé',
      description: 'L\'article que vous recherchez n\'existe pas.',
    };
  }

  return generateSEOMetadata({
    title: post.title,
    description: post.excerpt,
    keywords: post.tags,
    ogImage: post.image,
    ogType: 'article',
    canonical: `https://leopardo.com/blog/${post.slug}`,
    publishedTime: post.date.toISOString(),
    author: post.author.name,
  });
}

export default function BlogArticleLayout({
  children,
}: BlogArticleLayoutProps) {
  return children;
}
