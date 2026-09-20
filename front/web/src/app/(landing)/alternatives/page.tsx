'use client';

import Link from 'next/link';
import { motion } from 'framer-motion';
import { ArrowRight, Scale } from 'lucide-react';
import { useDarkMode } from '@/modules/vitrine/hooks/useDarkMode';
import { Navbar, Footer, useScrollReveal } from '@/modules/vitrine';
import {
  getAlternativePages,
  alternativesHubCopy,
  alternativesAltLabel,
} from '@/modules/vitrine/data/alternatives';
import { useVitrineLocale } from '@/modules/vitrine/lib/vitrine-locale';
import type { AppLocale } from '@/lib/i18n';

/**
 * #7869 — Hub « Alternatives & comparatifs » : page pilier SEO listant les
 * comparatifs /alternatives/[slug] (maillage interne).
 */

export default function AlternativesHubPage() {
  const { isDark, toggleDarkMode } = useDarkMode();
  const { locale, direction } = useVitrineLocale();
  useScrollReveal();

  const copy = alternativesHubCopy[locale] ?? alternativesHubCopy.fr;
  const pages = getAlternativePages(locale);

  return (
    <div
      dir={direction}
      className={`min-h-screen transition-colors duration-500 ${isDark ? 'dark bg-slate-950' : 'bg-white'}`}
    >
      <Navbar isDark={isDark} onToggleDark={toggleDarkMode} />

      <section className="relative pt-32 pb-16 overflow-hidden">
        <div className="absolute inset-0 bg-gradient-to-br from-slate-50 via-emerald-50/30 to-cyan-50/20 dark:from-slate-950 dark:via-emerald-950/20 dark:to-cyan-950/10" />
        <div className="relative max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
          <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.5 }}>
            <p className="inline-flex items-center gap-2 text-sm font-semibold text-emerald-600 dark:text-emerald-400 mb-4">
              <Scale className="w-4 h-4" />
              {copy.eyebrow}
            </p>
            <h1 className="text-4xl sm:text-5xl font-bold text-slate-900 dark:text-white mb-6">
              {copy.title}
            </h1>
            <p className="text-lg text-slate-600 dark:text-slate-300 max-w-2xl mx-auto">
              {copy.subtitle}
            </p>
          </motion.div>
        </div>
      </section>

      <section className="pb-20">
        <div className="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="grid sm:grid-cols-2 gap-6">
            {pages.map((page, index) => (
              <motion.div
                key={page.slug}
                initial={{ opacity: 0, y: 20 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.4, delay: index * 0.05 }}
              >
                <Link
                  href={`/alternatives/${page.slug}`}
                  className="group block h-full rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 hover:border-emerald-300 dark:hover:border-emerald-700 hover:shadow-lg transition-all"
                >
                  <p className="text-sm font-semibold text-emerald-600 dark:text-emerald-400 mb-2">
                    {(alternativesAltLabel[locale] ?? alternativesAltLabel.fr)(page.competitor)}
                  </p>
                  <h2 className="text-xl font-bold text-slate-900 dark:text-white mb-3">
                    Leopardo vs {page.competitor}
                  </h2>
                  <p className="text-sm text-slate-600 dark:text-slate-400 mb-4 line-clamp-3">
                    {page.metaDescription}
                  </p>
                  <span className="inline-flex items-center gap-2 text-sm font-semibold text-emerald-600 dark:text-emerald-400">
                    {copy.cardCta}
                    <ArrowRight className="w-4 h-4 group-hover:translate-x-1 transition-transform" />
                  </span>
                </Link>
              </motion.div>
            ))}
          </div>

          <p className="mt-10 text-xs text-slate-500 dark:text-slate-500 max-w-3xl">
            {copy.disclaimer}
          </p>
        </div>
      </section>

      <Footer />
    </div>
  );
}
