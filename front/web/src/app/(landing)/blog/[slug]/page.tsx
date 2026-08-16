'use client';

import { use, useState } from 'react';
import { useDarkMode } from '@/modules/vitrine/hooks/useDarkMode';
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
  archived: string;
}> = {
  fr: {
    dateLocale: 'fr-FR',
    readingTime: 'min de lecture',
    tableOfContents: 'Table des matieres',
    authorRole: 'Auteur et expert en gestion RH',
    relatedTitle: 'Articles recommandes',
    archived: "Article archivé — contenu publié en 2024, conservé pour référence.",
  },
  en: {
    dateLocale: 'en-US',
    readingTime: 'min read',
    tableOfContents: 'Table of contents',
    authorRole: 'Author and HR operations expert',
    relatedTitle: 'Recommended articles',
    archived: 'Archived article — content published in 2024, kept for reference.',
  },
  tr: {
    dateLocale: 'tr-TR',
    readingTime: 'dk okuma',
    tableOfContents: 'Icindekiler',
    authorRole: 'Yazar ve IK operasyonlari uzmani',
    relatedTitle: 'Onerilen yazilar',
    archived: "Arsivlenmis makale — 2024'te yayinlanan icerik, referans icin saklaniyor.",
  },
  ar: {
    dateLocale: 'ar',
    readingTime: 'دقيقة قراءة',
    tableOfContents: 'جدول المحتويات',
    authorRole: 'كاتب وخبير في عمليات الموارد البشرية',
    relatedTitle: 'مقالات مقترحة',
    archived: 'مقالة مؤرشف — نُشر المحتوى في 2024، تم الاحتفاظ به للرجوع إليه.',
  },
};

export default function BlogArticlePage({ params }: BlogArticlePageProps) {
  const { isDark, toggleDarkMode } = useDarkMode();
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
      <Navbar isDark={isDark} onToggleDark={toggleDarkMode} />

      {post.archived && (
        <div className="mx-auto max-w-3xl px-4 pt-6">
          <div className="rounded-xl border border-amber-200 dark:border-amber-800/60 bg-amber-50 dark:bg-amber-950/40 px-4 py-3 text-sm text-amber-800 dark:text-amber-200">
            ⚠️ {copy.archived}{' '}
            {{ 'fr': "Les chiffres et recommandations peuvent ne plus refléter l'état actuel du produit.", 'en': 'Figures and recommendations may no longer reflect the current state of the product.', 'tr': 'Rakamlar ve oneriler urunun guncel durumunu yansitmayabilir.', 'ar': 'قد لا تعكس الأرقام والتوصيات الحالة الحالية للمنتج.' }[locale]}
          </div>
        </div>
      )}

      <BlogArticle
        post={post}
        relatedPosts={relatedPosts}
        dateLocale={copy.dateLocale}
        readingTimeLabel={copy.readingTime}
        tableOfContentsLabel={copy.tableOfContents}
        authorRoleLabel={copy.authorRole}
        relatedTitle={copy.relatedTitle}
        locale={locale}
      />

      <Footer />
    </div>
  );
}
