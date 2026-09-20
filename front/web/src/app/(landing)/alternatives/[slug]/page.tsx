'use client';

import { use } from 'react';
import Link from 'next/link';
import { notFound } from 'next/navigation';
import { motion } from 'framer-motion';
import { ArrowLeft, ArrowRight, CheckCircle, Scale } from 'lucide-react';
import { useDarkMode } from '@/modules/vitrine/hooks/useDarkMode';
import { Navbar, Footer, useScrollReveal } from '@/modules/vitrine';
import {
  getAlternativePage,
  getAlternativePages,
  alternativesUiCopy,
} from '@/modules/vitrine/data/alternatives';
import { useVitrineLocale } from '@/modules/vitrine/lib/vitrine-locale';
import type { AppLocale } from '@/lib/i18n';

/**
 * #7869 — Page comparatif « Alternative à X » : résumé → tableau → quand
 * choisir le concurrent (section honnête) → quand choisir Leopardo → FAQ →
 * CTA essai. Contenu : src/modules/vitrine/data/alternatives.ts.
 */

interface AlternativePageProps {
  params: Promise<{ slug: string }>;
}

export default function AlternativeComparisonPage({ params }: AlternativePageProps) {
  const { isDark, toggleDarkMode } = useDarkMode();
  const { locale, direction } = useVitrineLocale();
  useScrollReveal();
  const { slug } = use(params);

  const page = getAlternativePage(slug, locale);

  if (!page) {
    notFound();
  }

  const ui = alternativesUiCopy[locale] ?? alternativesUiCopy.fr;
  const others = getAlternativePages(locale).filter((p) => p.slug !== page.slug);

  return (
    <div
      dir={direction}
      className={`min-h-screen transition-colors duration-500 ${isDark ? 'dark bg-slate-950' : 'bg-white'}`}
    >
      <Navbar isDark={isDark} onToggleDark={toggleDarkMode} />

      {/* Hero */}
      <section className="relative pt-32 pb-12 overflow-hidden">
        <div className="absolute inset-0 bg-gradient-to-br from-slate-50 via-emerald-50/30 to-cyan-50/20 dark:from-slate-950 dark:via-emerald-950/20 dark:to-cyan-950/10" />
        <div className="relative max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
          <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.5 }}>
            <Link
              href="/alternatives"
              className="inline-flex items-center gap-2 text-sm font-semibold text-slate-500 dark:text-slate-400 hover:text-emerald-600 dark:hover:text-emerald-400 transition-colors mb-6"
            >
              <ArrowLeft className="w-4 h-4" />
              {ui.backLink}
            </Link>
            <h1 className="text-3xl sm:text-4xl font-bold text-slate-900 dark:text-white mb-4">
              {page.title}
            </h1>
            <p className="text-lg text-slate-600 dark:text-slate-300">{page.intro}</p>
            <p className="mt-4 text-xs text-slate-500 dark:text-slate-500">
              {ui.reviewedAt}{' '}
              {new Date(page.reviewedAt).toLocaleDateString(ui.dateLocale, {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
              })}
            </p>
          </motion.div>
        </div>
      </section>

      {/* Tableau comparatif */}
      <section className="py-12">
        <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
          <h2 className="flex items-center gap-2 text-2xl font-bold text-slate-900 dark:text-white mb-6">
            <Scale className="w-6 h-6 text-emerald-600 dark:text-emerald-400" />
            {ui.tableTitle}
          </h2>
          <div className="overflow-x-auto rounded-2xl border border-slate-200 dark:border-slate-800">
            <table className="w-full text-sm">
              <thead>
                <tr className="bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white">
                  <th className="px-4 py-3 text-start font-semibold">{ui.tableCriterion}</th>
                  <th className="px-4 py-3 text-start font-semibold">{page.competitor}</th>
                  <th className="px-4 py-3 text-start font-semibold text-emerald-700 dark:text-emerald-400">
                    Leopardo
                  </th>
                </tr>
              </thead>
              <tbody>
                {page.criteria.map((row) => (
                  <tr
                    key={row.label}
                    className="border-t border-slate-200 dark:border-slate-800 align-top"
                  >
                    <td className="px-4 py-3 font-medium text-slate-900 dark:text-white">
                      {row.label}
                    </td>
                    <td className="px-4 py-3 text-slate-600 dark:text-slate-400">
                      {row.competitor}
                    </td>
                    <td className="px-4 py-3 text-slate-700 dark:text-slate-300">
                      {row.leopardo}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      </section>

      {/* Quand choisir le concurrent / Leopardo */}
      <section className="py-8">
        <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 grid md:grid-cols-2 gap-8">
          <div className="rounded-2xl border border-slate-200 dark:border-slate-800 p-6">
            <h2 className="text-xl font-bold text-slate-900 dark:text-white mb-4">
              {ui.whenCompetitor(page.competitor)}
            </h2>
            <ul className="space-y-3">
              {page.competitorStrengths.map((item) => (
                <li key={item} className="flex gap-3 text-sm text-slate-600 dark:text-slate-400">
                  <CheckCircle className="w-5 h-5 shrink-0 text-slate-400 dark:text-slate-600" />
                  {item}
                </li>
              ))}
            </ul>
          </div>
          <div className="rounded-2xl border border-emerald-200 dark:border-emerald-800 bg-emerald-50/50 dark:bg-emerald-950/20 p-6">
            <h2 className="text-xl font-bold text-slate-900 dark:text-white mb-4">
              {ui.whenLeopardo}
            </h2>
            <ul className="space-y-3">
              {page.leopardoStrengths.map((item) => (
                <li key={item} className="flex gap-3 text-sm text-slate-700 dark:text-slate-300">
                  <CheckCircle className="w-5 h-5 shrink-0 text-emerald-600 dark:text-emerald-400" />
                  {item}
                </li>
              ))}
            </ul>
          </div>
        </div>
      </section>

      {/* FAQ */}
      <section className="py-12">
        <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
          <h2 className="text-2xl font-bold text-slate-900 dark:text-white mb-6">{ui.faqTitle}</h2>
          <div className="space-y-4">
            {page.faqs.map((faq) => (
              <details
                key={faq.question}
                className="group rounded-xl border border-slate-200 dark:border-slate-800 p-5"
              >
                <summary className="cursor-pointer font-semibold text-slate-900 dark:text-white">
                  {faq.question}
                </summary>
                <p className="mt-3 text-sm text-slate-600 dark:text-slate-400">{faq.answer}</p>
              </details>
            ))}
          </div>
        </div>
      </section>

      {/* CTA */}
      <section className="py-12">
        <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="rounded-3xl bg-gradient-to-br from-emerald-600 to-cyan-600 p-8 sm:p-12 text-center text-white">
            <h2 className="text-3xl font-bold mb-3">{ui.ctaTitle}</h2>
            <p className="text-emerald-50 mb-8 max-w-xl mx-auto">{ui.ctaSubtitle}</p>
            <div className="flex flex-wrap justify-center gap-4">
              <Link
                href="/signup"
                className="inline-flex items-center gap-2 rounded-xl bg-white px-6 py-3 font-semibold text-emerald-700 hover:bg-emerald-50 transition-colors"
              >
                {ui.ctaTrial}
                <ArrowRight className="w-4 h-4" />
              </Link>
              <Link
                href="/demo"
                className="inline-flex items-center gap-2 rounded-xl border border-white/60 px-6 py-3 font-semibold text-white hover:bg-white/10 transition-colors"
              >
                {ui.ctaDemo}
              </Link>
            </div>
          </div>
        </div>
      </section>

      {/* Autres comparatifs + disclaimer */}
      <section className="pb-16">
        <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
          <h2 className="text-lg font-bold text-slate-900 dark:text-white mb-4">
            {ui.otherComparisons}
          </h2>
          <div className="flex flex-wrap gap-3 mb-8">
            {others.map((other) => (
              <Link
                key={other.slug}
                href={`/alternatives/${other.slug}`}
                className="rounded-full border border-slate-200 dark:border-slate-700 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 hover:border-emerald-300 dark:hover:border-emerald-700 hover:text-emerald-700 dark:hover:text-emerald-400 transition-colors"
              >
                Leopardo vs {other.competitor}
              </Link>
            ))}
          </div>
          <p className="text-xs text-slate-500 dark:text-slate-500">{ui.disclaimer}</p>
        </div>
      </section>

      <Footer />
    </div>
  );
}
