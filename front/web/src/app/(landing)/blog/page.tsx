'use client';

import { useState } from 'react';
import { useDarkMode } from '@/modules/vitrine/hooks/useDarkMode';
import { useSearchParams } from 'next/navigation';
import {
  Navbar,
  HeroSection,
  CTASection,
  Footer,
  useScrollReveal,
  BlogGrid,
} from '@/modules/vitrine';
import { getBlogPosts } from '@/modules/vitrine/data/blog';
import { useVitrineLocale } from '@/modules/vitrine/lib/vitrine-locale';
import type { AppLocale } from '@/lib/i18n';
import { NewsletterForm } from '@/components/NewsletterForm';
import { motion } from 'framer-motion';
import { BookOpen } from 'lucide-react';

// #4704 : catalogue localisé déplacé dans data/blog-copy.ts
import { blogCopy } from '@/modules/vitrine/data/blog-copy';

export default function BlogPage() {
  const searchParams = useSearchParams();
  const { isDark, toggleDarkMode } = useDarkMode();
  const { locale, direction } = useVitrineLocale();
  const copy = blogCopy[locale] ?? blogCopy.fr;
  const posts = getBlogPosts(locale);
  useScrollReveal();

  const blogCards = [...posts]
    // #3263 : les posts archivés (2023-2024) ne doivent pas passer pour du
    // contenu frais — ils sont triés en fin de liste et badgeés « Archivé ».
    .sort((a, b) => Number(Boolean(a.archived)) - Number(Boolean(b.archived)))
    .map((post) => ({
      slug: post.slug,
      title: post.title,
      excerpt: post.excerpt,
      image: post.image,
      date: post.date,
      author: post.author,
      category: post.category,
      readingTime: post.readingTime,
      archived: post.archived,
      // #4704 : libellés localisés portés par la carte (BlogGrid les
      // surcharge explicitement — requis car BlogCard n'a plus de défaut FR).
      dateLocale: copy.dateLocale,
      readingTimeLabel: copy.readingTime,
    }));

  const categories = Array.from(new Set(posts.map((post) => post.category)));

  return (
    <div dir={direction} className={`min-h-screen transition-colors duration-500 ${isDark ? 'dark bg-slate-950' : 'bg-white'}`}>
      <Navbar isDark={isDark} onToggleDark={toggleDarkMode} />

      <HeroSection
        headline={copy.hero.headline}
        subheadline={copy.hero.subheadline}
        ctaPrimary={{ text: copy.hero.cta, href: '#newsletter' }}
        badge={{ text: copy.hero.badge, icon: <BookOpen className="w-3 h-3" /> }}
      />

      <BlogGrid
        title={copy.grid.title}
        subtitle={copy.grid.subtitle}
        posts={blogCards}
        initialCategory={searchParams.get('category')}
        categories={categories}
        itemsPerPage={9}
        showPagination
        showFilters
        allLabel={copy.grid.all}
        previousLabel={copy.grid.previous}
        nextLabel={copy.grid.next}
        dateLocale={copy.dateLocale}
        readingTimeLabel={copy.readingTime}
        archivedLabel={copy.archived}
        badge={{ text: copy.grid.badge, icon: <BookOpen className="w-3 h-3" /> }}
      />

      <section id="newsletter" className="relative py-32 overflow-hidden">
        <div className="absolute inset-0 bg-gradient-to-r from-emerald-500/10 via-cyan-500/10 to-emerald-500/10 dark:from-emerald-500/5 dark:via-cyan-500/5 dark:to-emerald-500/5" />

        <div className="relative max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            transition={{ duration: 0.6 }}
            className="text-center"
          >
            <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-emerald-500/[0.08] border border-emerald-500/15 text-emerald-700 dark:text-emerald-400 text-sm font-semibold mb-6">
              <span className="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse" />
              {copy.newsletter.badge}
            </div>
            <h2 className="text-4xl sm:text-5xl lg:text-6xl font-black text-slate-900 dark:text-white mb-6 tracking-tight">
              {copy.newsletter.title}
            </h2>
            <p className="text-lg text-slate-600 dark:text-slate-400 mb-8 leading-relaxed">
              {copy.newsletter.description}
            </p>

            <NewsletterForm
              locale={locale}
              ariaLabel={copy.newsletter.title}
              placeholder={copy.newsletter.placeholder}
              submitLabel={copy.newsletter.submit}
              submittingLabel={copy.newsletter.submitting}
              successFallback={copy.newsletter.success}
              errorFallback={copy.newsletter.error}
            />

            <p className="text-sm text-slate-500 dark:text-slate-400 mt-4">
              {copy.newsletter.note}
            </p>
          </motion.div>
        </div>
      </section>

      <CTASection
        headline={copy.cta.headline}
        subheadline={copy.cta.subheadline}
        ctaPrimary={{ text: copy.cta.primary, href: '/contact' }}
        ctaSecondary={{ text: copy.cta.secondary, href: '/signup' }}
        background="gradient"
      />

      <Footer />
    </div>
  );
}
