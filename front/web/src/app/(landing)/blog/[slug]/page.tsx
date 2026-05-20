'use client';

import { use, useState } from 'react';
import { Navbar, Footer, useScrollReveal, BlogArticle } from '@/modules/vitrine';
import { getBlogPost, getBlogPosts } from '@/modules/vitrine/data/blog';
import { useVitrineLocale } from '@/modules/vitrine/lib/vitrine-locale';
import type { AppLocale } from '@/lib/i18n';
import { notFound } from 'next/navigation';

interface BlogArticlePageProps {
  params: Promise<{
    slug: string;
  }>;
}

const articleCopy: Record<AppLocale, {
  dateLocale: string;
  readingTime: string;
  tableOfContents: string;
  authorRole: string;
  relatedTitle: string;
}> = {
  fr: {
    dateLocale: 'fr-FR',
    readingTime: 'min de lecture',
    tableOfContents: 'Table des matieres',
    authorRole: 'Auteur et expert en gestion RH',
    relatedTitle: 'Articles recommandes',
  },
  en: {
    dateLocale: 'en-US',
    readingTime: 'min read',
    tableOfContents: 'Table of contents',
    authorRole: 'Author and HR operations expert',
    relatedTitle: 'Recommended articles',
  },
  tr: {
    dateLocale: 'tr-TR',
    readingTime: 'dk okuma',
    tableOfContents: 'Icindekiler',
    authorRole: 'Yazar ve IK operasyonlari uzmani',
    relatedTitle: 'Onerilen yazilar',
  },
  ar: {
    dateLocale: 'ar',
    readingTime: 'دقيقة قراءة',
    tableOfContents: 'جدول المحتويات',
    authorRole: 'كاتب وخبير في عمليات الموارد البشرية',
    relatedTitle: 'مقالات مقترحة',
  },
};

export default function BlogArticlePage({ params }: BlogArticlePageProps) {
  const [isDark, setIsDark] = useState(false);
  const { locale, direction } = useVitrineLocale();
  const copy = articleCopy[locale] ?? articleCopy.fr;
  useScrollReveal();
  const { slug } = use(params);

  const post = getBlogPost(slug, locale);

  if (!post) {
    notFound();
  }

  const relatedPosts = getBlogPosts(locale).filter(
    (p) => p.category === post.category && p.slug !== post.slug
  );

  return (
    <div dir={direction} className={`min-h-screen transition-colors duration-500 ${isDark ? 'dark bg-slate-950' : 'bg-white'}`}>
      <Navbar isDark={isDark} onToggleDark={() => setIsDark(!isDark)} />

      <BlogArticle
        post={post}
        relatedPosts={relatedPosts}
        dateLocale={copy.dateLocale}
        readingTimeLabel={copy.readingTime}
        tableOfContentsLabel={copy.tableOfContents}
        authorRoleLabel={copy.authorRole}
        relatedTitle={copy.relatedTitle}
      />

      <Footer />
    </div>
  );
}
