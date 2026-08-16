'use client';

import { useDarkMode } from '@/modules/vitrine/hooks/useDarkMode';
import { Navbar, Footer, HeroSection, CTASection, useScrollReveal } from '@/modules/vitrine';
import { useVitrineLocale } from '@/modules/vitrine/lib/vitrine-locale';
import { getCareersContent } from '@/modules/vitrine/data/careers';
import { motion } from 'framer-motion';
import { Briefcase, MapPin, Clock, Heart, Zap, Globe, Users } from 'lucide-react';

const valueIcons = [Heart, Zap, Globe, Users];

export default function CareersPage() {
  const { isDark, toggleDarkMode } = useDarkMode();
  const { locale } = useVitrineLocale();
  useScrollReveal();
  const content = getCareersContent(locale);

  return (
    <div className={`min-h-screen transition-colors duration-500 ${isDark ? 'dark bg-slate-950' : 'bg-white'}`}>
      <Navbar isDark={isDark} onToggleDark={toggleDarkMode} />
      <HeroSection
        headline={content.hero.headline}
        subheadline={content.hero.subheadline}
        ctaPrimary={{ text: content.hero.cta, href: '#openings' }}
        badge={{ text: content.hero.badge, icon: <Briefcase className="w-3 h-3" /> }}
      />

      <section className="py-24 bg-transparent dark:bg-slate-900/50">
        <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="text-center mb-16">
            <h2 className="text-3xl sm:text-4xl font-black text-slate-900 dark:text-white mb-4">{content.values.title}</h2>
            <p className="text-lg text-slate-600 dark:text-slate-400">{content.values.subtitle}</p>
          </div>
          <div className="grid sm:grid-cols-2 lg:grid-cols-4 gap-8">
            {content.values.items.map((val, i) => {
              const Icon = valueIcons[i % valueIcons.length];
              return (
                <motion.div
                  key={i}
                  initial={{ opacity: 0, y: 20 }}
                  whileInView={{ opacity: 1, y: 0 }}
                  viewport={{ once: true }}
                  transition={{ delay: i * 0.1 }}
                  className="text-center p-6"
                >
                  <div className="w-14 h-14 rounded-2xl bg-emerald-500/10 flex items-center justify-center mx-auto mb-4">
                    <Icon className="w-7 h-7 text-emerald-600 dark:text-emerald-400" />
                  </div>
                  <h3 className="text-lg font-bold text-slate-900 dark:text-white mb-2">{val.title}</h3>
                  <p className="text-sm text-slate-600 dark:text-slate-400 leading-relaxed">{val.description}</p>
                </motion.div>
              );
            })}
          </div>
        </div>
      </section>

      <section className="py-24">
        <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
          <h2 className="text-3xl font-black text-slate-900 dark:text-white mb-10 text-center">{content.benefits.title}</h2>
          <div className="grid sm:grid-cols-2 gap-6">
            {content.benefits.items.map((benefit, i) => (
              <div key={i} className="flex items-center gap-3 p-4 rounded-xl bg-transparent dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800">
                <div className="w-2 h-2 rounded-full bg-emerald-500 flex-shrink-0" />
                <span className="text-slate-700 dark:text-slate-300">{benefit}</span>
              </div>
            ))}
          </div>
        </div>
      </section>

      <section id="openings" className="py-24 bg-transparent dark:bg-slate-900/50">
        <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="text-center mb-12">
            <h2 className="text-3xl sm:text-4xl font-black text-slate-900 dark:text-white mb-4">{content.openings.title}</h2>
            <p className="text-lg text-slate-600 dark:text-slate-400">{content.openings.items.length} {content.openings.subtitle}</p>
          </div>
          <div className="space-y-4">
            {content.openings.items.map((job, i) => (
              <motion.div
                key={i}
                initial={{ opacity: 0, y: 10 }}
                whileInView={{ opacity: 1, y: 0 }}
                viewport={{ once: true }}
                transition={{ delay: i * 0.05 }}
                className="p-6 bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700"
              >
                <div className="flex items-start justify-between gap-4">
                  <div className="flex-1">
                    <h3 className="text-lg font-bold text-slate-900 dark:text-white">{job.title}</h3>
                    <p className="text-sm text-slate-600 dark:text-slate-400 mt-1 mb-3">{job.description}</p>
                    <div className="flex flex-wrap gap-3 text-xs text-slate-500 dark:text-slate-400">
                      <span className="flex items-center gap-1"><Briefcase className="w-3 h-3" />{job.department}</span>
                      <span className="flex items-center gap-1"><MapPin className="w-3 h-3" />{job.location}</span>
                      <span className="flex items-center gap-1"><Clock className="w-3 h-3" />{job.type}</span>
                    </div>
                  </div>
                </div>
              </motion.div>
            ))}
          </div>
        </div>
      </section>

      <CTASection
        headline={content.cta.headline}
        subheadline={content.cta.subheadline}
        ctaPrimary={{ text: content.cta.ctaText, href: '/contact' }}
        background="gradient"
      />

      <Footer />
    </div>
  );
}
