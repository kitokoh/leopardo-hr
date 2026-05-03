'use client';

import { useState } from 'react';
import { Navbar, Footer, useScrollReveal, BlogArticle } from '@/modules/vitrine';
import { blogPosts } from '@/modules/vitrine/data/blog';
import { notFound } from 'next/navigation';

interface BlogArticlePageProps {
  params: {
    slug: string;
  };
}

export default function BlogArticlePage({ params }: BlogArticlePageProps) {
  const [isDark, setIsDark] = useState(false);
  useScrollReveal();

  // Find the post
  const post = blogPosts.find((p) => p.slug === params.slug);

  if (!post) {
    notFound();
  }

  // Get related posts (same category, excluding current)
  const relatedPosts = blogPosts.filter(
    (p) => p.category === post.category && p.slug !== post.slug
  );

  return (
    <div className={`min-h-screen transition-colors duration-500 ${isDark ? 'dark bg-slate-950' : 'bg-white'}`}>
      <Navbar isDark={isDark} onToggleDark={() => setIsDark(!isDark)} />

      <BlogArticle post={post} relatedPosts={relatedPosts} />

      <Footer />
    </div>
  );
}
