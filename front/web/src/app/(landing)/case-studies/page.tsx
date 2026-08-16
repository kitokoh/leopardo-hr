'use client';

import { useState } from 'react';
import { useDarkMode } from '@/modules/vitrine/hooks/useDarkMode';
import { Navbar, HeroSection, CTASection, Footer, useScrollReveal } from '@/modules/vitrine';
import { useVitrineLocale } from '@/modules/vitrine/lib/vitrine-locale';
import { caseStudiesByLocale } from '@/modules/vitrine/data/case-studies';
import { motion } from 'framer-motion';
import { TrendingUp, Clock, Users, Building2, CheckCircle, Info } from 'lucide-react';


const colorMap: Record<string, { bg: string; text: string; border: string; badge: string }> = {
  emerald: { bg: 'bg-emerald-50 dark:bg-emerald-900/20', text: 'text-emerald-700 dark:text-emerald-400', border: 'border-emerald-200 dark:border-emerald-800', badge: 'bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-400' },
  blue: { bg: 'bg-blue-50 dark:bg-blue-900/20', text: 'text-blue-700 dark:text-blue-400', border: 'border-blue-200 dark:border-blue-800', badge: 'bg-blue-100 dark:bg-blue-900/50 text-blue-700 dark:text-blue-400' },
  amber: { bg: 'bg-amber-50 dark:bg-amber-900/20', text: 'text-amber-700 dark:text-amber-400', border: 'border-amber-200 dark:border-amber-800', badge: 'bg-amber-100 dark:bg-amber-900/50 text-amber-700 dark:text-amber-400' },
};

export default function CaseStudiesPage() {
  const { isDark, toggleDarkMode } = useDarkMode();
  const { copy, locale } = useVitrineLocale();
  const caseStudies = caseStudiesByLocale[locale] ?? caseStudiesByLocale.fr;
  const cs = copy.caseStudies;
  useScrollReveal();

  return (
    <div className={`min-h-screen transition-colors duration-500 ${isDark ? 'dark bg-slate-950' : 'bg-white'}`}>
      <Navbar isDark={isDark} onToggleDark={toggleDarkMode} />

      <HeroSection
        headline={cs.heroTitle}
        subheadline={cs.heroSubtitle}
        ctaPrimary={{ text: cs.heroPrimary, href: '/signup' }}
        ctaSecondary={{ text: cs.heroSecondary, href: '/testimonials' }}
        badge={{ text: cs.heroBadge, icon: <TrendingUp className="w-3 h-3" /> }}
      />

      {/* Honesty notice — these case studies are illustrative (fictional data) */}
      <section className="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 -mt-10">
        <div className="flex items-start gap-3 rounded-2xl border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/20 px-5 py-4">
          <Info className="w-5 h-5 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" aria-hidden="true" />
          <p className="text-sm text-amber-800 dark:text-amber-200 leading-relaxed">{cs.demoNotice}</p>
        </div>
      </section>

      {/* Case Studies */}
      <section className="py-24">
        <div className="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-16">
          {caseStudies.map((item, i) => {
            const colors = colorMap[item.color] || colorMap.emerald;
            return (
              <motion.article
                key={i}
                initial={{ opacity: 0, y: 40 }}
                whileInView={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.5 }}
                viewport={{ once: true }}
                className={`rounded-3xl border ${colors.border} overflow-hidden`}
              >
                {/* Header */}
                <div className={`${colors.bg} px-8 py-6`}>
                  <div className="flex flex-wrap items-center gap-4">
                    <div className={`px-3 py-1 rounded-full text-xs font-bold ${colors.badge}`}>
                      {item.industry}
                    </div>
                    <span className="px-3 py-1 rounded-full text-xs font-bold bg-white/70 dark:bg-slate-900/70 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                      {cs.demoBadge}
                    </span>
                    <h2 className="text-2xl font-black text-slate-900 dark:text-white">{item.company}</h2>
                    <div className="flex items-center gap-4 text-sm text-slate-500 dark:text-slate-400 ml-auto">
                      <span className="flex items-center gap-1"><Users className="w-4 h-4" />{item.employees} {cs.employees}</span>
                      <span className="flex items-center gap-1"><Building2 className="w-4 h-4" />{item.country}</span>
                    </div>
                  </div>
                </div>

                {/* Body */}
                <div className="bg-white dark:bg-slate-900 px-8 py-8">
                  <div className="grid md:grid-cols-2 gap-8 mb-8">
                    <div>
                      <h3 className="font-bold text-slate-900 dark:text-white text-sm uppercase tracking-wider mb-3 flex items-center gap-2">
                        <Clock className="w-4 h-4 text-red-500" /> {cs.challenge}
                      </h3>
                      <p className="text-slate-600 dark:text-slate-400 text-sm leading-relaxed">{item.challenge}</p>
                    </div>
                    <div>
                      <h3 className="font-bold text-slate-900 dark:text-white text-sm uppercase tracking-wider mb-3 flex items-center gap-2">
                        <CheckCircle className="w-4 h-4 text-emerald-500" /> {cs.solution}
                      </h3>
                      <p className="text-slate-600 dark:text-slate-400 text-sm leading-relaxed">{item.solution}</p>
                    </div>
                  </div>

                  {/* Results */}
                  <div className="grid grid-cols-3 gap-4 mb-8">
                    {item.results.map((r, j) => (
                      <div key={j} className={`${colors.bg} rounded-xl p-4 text-center`}>
                        <p className={`text-2xl font-black ${colors.text}`}>{r.metric}</p>
                        <p className="text-slate-500 dark:text-slate-400 text-xs mt-1">{r.label}</p>
                      </div>
                    ))}
                  </div>

                  {/* Testimonial */}
                  <blockquote className="border-l-4 border-emerald-500 pl-4 py-2">
                    <p className="text-slate-700 dark:text-slate-300 italic text-sm">&ldquo;{item.testimonial}&rdquo;</p>
                    <cite className="text-slate-500 text-xs mt-2 block not-italic">&mdash; {item.author}</cite>
                  </blockquote>
                </div>
              </motion.article>
            );
          })}
        </div>
      </section>

      <CTASection
        title={cs.ctaTitle}
        description={cs.ctaDescription}
        primaryCta={{ text: cs.ctaPrimary, href: '/signup' }}
        secondaryCta={{ text: cs.ctaSecondary, href: '/demo' }}
      />

      <Footer />
    </div>
  );
}
