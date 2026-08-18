'use client';

import { use, useState } from 'react';
import { useDarkMode } from '@/modules/vitrine/hooks/useDarkMode';
import Link from 'next/link';
import { notFound } from 'next/navigation';
import { Navbar, Footer, useScrollReveal } from '@/modules/vitrine';
import { CTASection } from '@/modules/vitrine';
import { motion } from 'framer-motion';
import { ArrowLeft, ArrowRight, Building2, CheckCircle, TrendingUp } from 'lucide-react';
import { getCaseStudy, getModuleLabel } from '@/modules/vitrine/lib/case-studies';
import { useVitrineLocale } from '@/modules/vitrine/lib/vitrine-locale';
import { caseStudyUiCopy } from '@/modules/vitrine/data/case-studies';

interface CaseStudyPageProps {
  params: Promise<{
    slug: string;
  }>;
}

const colorMap: Record<string, { bg: string; text: string; border: string; badge: string }> = {
  emerald: { bg: 'bg-emerald-50 dark:bg-emerald-900/20', text: 'text-emerald-700 dark:text-emerald-400', border: 'border-emerald-200 dark:border-emerald-800', badge: 'bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-400' },
  blue: { bg: 'bg-blue-50 dark:bg-blue-900/20', text: 'text-blue-700 dark:text-blue-400', border: 'border-blue-200 dark:border-blue-800', badge: 'bg-blue-100 dark:bg-blue-900/50 text-blue-700 dark:text-blue-400' },
  amber: { bg: 'bg-amber-50 dark:bg-amber-900/20', text: 'text-amber-700 dark:text-amber-400', border: 'border-amber-200 dark:border-amber-800', badge: 'bg-amber-100 dark:bg-amber-900/50 text-amber-700 dark:text-amber-400' },
  violet: { bg: 'bg-violet-50 dark:bg-violet-900/20', text: 'text-violet-700 dark:text-violet-400', border: 'border-violet-200 dark:border-violet-800', badge: 'bg-violet-100 dark:bg-violet-900/50 text-violet-700 dark:text-violet-400' },
  slate: { bg: 'bg-slate-50 dark:bg-slate-800/40', text: 'text-slate-700 dark:text-slate-300', border: 'border-slate-200 dark:border-slate-700', badge: 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300' },
};

const accentByIndustry: Record<string, string> = {
  Technologie: 'emerald',
  Retail: 'blue',
  Industrie: 'amber',
  Juridique: 'violet',
  RH: 'emerald',
  Finance: 'blue',
  PME: 'slate',
  Startup: 'emerald',
  Groupe: 'amber',
  Engagement: 'violet',
  Marketing: 'blue',
};

export default function CaseStudyPage({ params }: CaseStudyPageProps) {
  const { isDark, toggleDarkMode } = useDarkMode();
  useScrollReveal();
  const { slug } = use(params);

  const { locale } = useVitrineLocale();
  const ui = caseStudyUiCopy[locale] ?? caseStudyUiCopy.fr;
  const study = getCaseStudy(slug);

  if (!study) {
    notFound();
  }

  const accent = accentByIndustry[study.industry] ?? 'emerald';
  const colors = colorMap[accent] ?? colorMap.emerald;

  return (
    <div className={`min-h-screen transition-colors duration-500 ${isDark ? 'dark bg-slate-950' : 'bg-white'}`}>
      <Navbar isDark={isDark} onToggleDark={toggleDarkMode} />

      {/* Hero */}
      <section className="relative pt-32 pb-16 overflow-hidden">
        <div className="absolute inset-0 bg-gradient-to-br from-slate-50 via-emerald-50/30 to-cyan-50/20 dark:from-slate-950 dark:via-emerald-950/20 dark:to-cyan-950/10" />
        <div className="absolute top-20 right-0 w-96 h-96 rounded-full bg-emerald-400/5 blur-3xl" />

        <div className="relative max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.5 }}
          >
            <Link
              href="/case-studies"
              className="inline-flex items-center gap-2 text-sm font-semibold text-slate-500 dark:text-slate-400 hover:text-emerald-600 dark:hover:text-emerald-400 transition-colors mb-6"
            >
              <ArrowLeft className="w-4 h-4" />
              {ui.backLink}
            </Link>

            <div className="flex flex-wrap items-center gap-3 mb-6">
              <span className={`inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold ${colors.badge}`}>
                <Building2 className="w-3 h-3" />
                {study.industry}
              </span>
              <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 text-xs font-semibold">
                <TrendingUp className="w-3 h-3" />
                {ui.useCase.replace('{module}', getModuleLabel(study.module, locale))}
              </span>
            </div>

            <h1 className="text-4xl sm:text-5xl font-black text-slate-900 dark:text-white tracking-tight mb-6">
              {study.title}
            </h1>
            <p className="text-lg text-slate-500 dark:text-slate-400 max-w-2xl leading-relaxed">
              {study.description}
            </p>
          </motion.div>
        </div>
      </section>

      {/* Résultats clés */}
      <section className="py-16 px-4 bg-transparent dark:bg-slate-900 border-y border-slate-200 dark:border-slate-800">
        <div className="max-w-4xl mx-auto">
          <h2 className="text-2xl font-black text-slate-900 dark:text-white text-center mb-10">
            {ui.resultsTitle}
          </h2>
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
            {study.metrics.map((metric, i) => (
              <motion.div
                key={metric.label}
                initial={{ opacity: 0, y: 20 }}
                whileInView={{ opacity: 1, y: 0 }}
                viewport={{ once: true }}
                transition={{ duration: 0.4, delay: i * 0.1 }}
                className={`${colors.bg} rounded-2xl p-6 text-center border ${colors.border}`}
              >
                <p className={`text-3xl font-black ${colors.text}`}>{metric.value}</p>
                <p className="text-slate-500 dark:text-slate-400 text-sm mt-2">{metric.label}</p>
              </motion.div>
            ))}
          </div>
        </div>
      </section>

      {/* Module concerné */}
      <section className="py-16 px-4">
        <div className="max-w-3xl mx-auto">
          <div className="rounded-2xl border border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900 p-8 text-center">
            <div className="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-300">
              <CheckCircle className="h-6 w-6" />
            </div>
            <h2 className="text-xl font-black text-slate-900 dark:text-white mb-2">
              {ui.moduleIllustrates.replace('{module}', getModuleLabel(study.module, locale))}
            </h2>
            <p className="text-sm text-slate-500 dark:text-slate-400 leading-relaxed mb-6">
              {ui.moduleExplore}
            </p>
            <div className="flex flex-wrap justify-center gap-3">
              <Link
                href={study.moduleHref}
                className="inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-emerald-500 to-emerald-600 text-white text-sm font-bold rounded-xl hover:from-emerald-600 hover:to-emerald-700 transition-all shadow-lg shadow-emerald-500/20"
              >
                {ui.discoverModule.replace('{module}', getModuleLabel(study.module, locale))}
                <ArrowRight className="w-4 h-4" />
              </Link>
              <Link
                href="/case-studies"
                className="inline-flex items-center gap-2 px-6 py-3 rounded-xl border border-slate-200/80 dark:border-slate-800/80 text-slate-700 dark:text-slate-200 text-sm font-bold hover:border-emerald-300 hover:text-emerald-700 dark:hover:text-emerald-400 transition-all"
              >
                {ui.seeAll}
              </Link>
            </div>
          </div>
        </div>
      </section>

      <CTASection
        title={ui.ctaTitle}
        description={ui.ctaDescription}
        primaryCta={{ text: ui.ctaPrimaryText, href: '/signup' }}
        secondaryCta={{ text: ui.demoCta, href: '/demo' }}
      />

      <Footer />
    </div>
  );
}
