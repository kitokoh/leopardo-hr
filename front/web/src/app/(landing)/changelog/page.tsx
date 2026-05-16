'use client';

import { useState } from 'react';
import { Navbar, Footer, HeroSection, useScrollReveal } from '@/modules/vitrine';
import { useVitrineLocale } from '@/modules/vitrine/lib/vitrine-locale';
import { publicChangelogReleases } from '@/modules/vitrine/data/changelog-public';
import { Sparkles } from 'lucide-react';
import { motion } from 'framer-motion';

export default function ChangelogPage() {
  const [isDark, setIsDark] = useState(false);
  useScrollReveal();
  const { copy } = useVitrineLocale();
  const ch = copy.changelog;

  const headline = `${ch.title} ${ch.titleHighlight}`;

  return (
    <div className={`min-h-screen transition-colors duration-500 ${isDark ? 'dark bg-slate-950' : 'bg-white'}`}>
      <Navbar isDark={isDark} onToggleDark={() => setIsDark(!isDark)} />

      <HeroSection
        headline={headline}
        subheadline={ch.subtitle}
        badge={{
          text: ch.badge,
          icon: <Sparkles className="w-3 h-3" />,
        }}
        ctaPrimary={{
          text: copy.cta.primary,
          href: '/signup',
        }}
        ctaSecondary={{
          text: copy.hero.secondaryCta,
          href: '/demo',
        }}
      />

      <section className="relative py-24 overflow-hidden">
        <div className="absolute inset-0 bg-gradient-to-b from-white via-slate-50/80 to-white dark:from-slate-950 dark:via-slate-900/50 dark:to-slate-950" />
        <div className="relative max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-12">
          {publicChangelogReleases.map((release, index) => (
            <motion.article
              key={release.version}
              initial={{ opacity: 0, y: 16 }}
              whileInView={{ opacity: 1, y: 0 }}
              viewport={{ once: true }}
              transition={{ duration: 0.45, delay: index * 0.05 }}
              className="rounded-2xl border border-slate-200/80 dark:border-slate-800/80 bg-white/90 dark:bg-slate-900/60 backdrop-blur-sm shadow-sm p-6 sm:p-8"
            >
              <div className="flex flex-wrap items-baseline justify-between gap-2 mb-4">
                <h2 className="text-lg sm:text-xl font-bold text-slate-900 dark:text-white">{release.title}</h2>
                <div className="flex items-center gap-2 text-sm text-emerald-600 dark:text-emerald-400 font-semibold tabular-nums">
                  <span>v{release.version}</span>
                  <span className="text-slate-300 dark:text-slate-600">·</span>
                  <time dateTime={release.isoDate}>{release.isoDate}</time>
                </div>
              </div>
              <ul className="list-disc list-inside space-y-2 text-sm sm:text-base text-slate-600 dark:text-slate-300 leading-relaxed">
                {release.bullets.map((line) => (
                  <li key={line}>{line}</li>
                ))}
              </ul>
            </motion.article>
          ))}

          <p className="text-center text-sm text-slate-500 dark:text-slate-400 pb-8">{ch.repoNote}</p>
        </div>
      </section>

      <Footer />
    </div>
  );
}
