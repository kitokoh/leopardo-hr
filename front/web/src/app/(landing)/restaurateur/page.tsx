'use client';

/**
 * Page vitrine « Je suis restaurateur » — pré-qualification publique.
 * Route : /restaurateur (le hub applicatif client vit sur /restaurant)
 *
 * Le héro porte la promesse d'entrée pour les corps de métier : le pack
 * Restaurant est OFFERT. Le wizard enchaîne pour composer ce pack sur mesure.
 */

import { motion } from 'framer-motion';
import { Check, Gift } from 'lucide-react';
import { useDarkMode } from '@/modules/vitrine/hooks/useDarkMode';
import { Navbar, Footer } from '@/modules/vitrine';
import { RestaurantSolutionWizard } from '@/modules/vitrine/components/RestaurantSolutionWizard';
import { RESTAURANT_HERO_COPY } from '@/modules/vitrine/data/restaurant-wizard';
import { useVitrineLocale } from '@/modules/vitrine/lib/vitrine-locale';

export default function RestaurantPage() {
  const { isDark, toggleDarkMode } = useDarkMode();
  const { direction, locale } = useVitrineLocale();
  const hero = RESTAURANT_HERO_COPY[locale] ?? RESTAURANT_HERO_COPY.fr;

  return (
    <div dir={direction} className={`min-h-screen transition-colors duration-500 ${isDark ? 'dark bg-slate-950' : 'bg-white'}`}>
      <Navbar isDark={isDark} onToggleDark={toggleDarkMode} />

      <section className="relative pt-32 pb-24 overflow-hidden">
        <div className="absolute inset-0 bg-gradient-to-br from-slate-50 via-emerald-50/30 to-cyan-50/20 dark:from-slate-950 dark:via-emerald-950/20 dark:to-cyan-950/10" />
        <div className="absolute top-24 left-1/2 -translate-x-1/2 w-[36rem] h-72 rounded-full bg-emerald-400/10 blur-3xl" />

        <div className="relative mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 text-center">
          <motion.div
            initial={{ opacity: 0, y: 24 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.55 }}
          >
            <div className="mb-6 inline-flex items-center gap-2 rounded-full border border-emerald-300/40 bg-emerald-100/70 px-4 py-2 text-sm font-bold text-emerald-800 dark:border-emerald-400/30 dark:bg-emerald-400/10 dark:text-emerald-200">
              <Gift className="h-4 w-4" aria-hidden="true" />
              {hero.badge}
            </div>
            <h1 className="text-4xl font-black tracking-tight text-slate-900 dark:text-white sm:text-5xl">
              {hero.title}{' '}
              <span className="bg-gradient-to-r from-emerald-600 to-cyan-600 bg-clip-text text-transparent dark:from-emerald-300 dark:to-cyan-300">
                {hero.highlight}
              </span>
            </h1>
            <p className="mx-auto mt-6 max-w-2xl text-lg leading-8 text-slate-600 dark:text-slate-300">
              {hero.subtitle}
            </p>
            <div className="mt-8 flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-sm font-semibold text-slate-700 dark:text-slate-200">
              {hero.points.map((point) => (
                <span key={point} className="inline-flex items-center gap-1.5">
                  <Check className="h-4 w-4 text-emerald-500" aria-hidden="true" />
                  {point}
                </span>
              ))}
            </div>
          </motion.div>
        </div>

        <div className="relative mt-14">
          <RestaurantSolutionWizard />
        </div>
      </section>

      <Footer />
    </div>
  );
}
