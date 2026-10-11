'use client';

/**
 * Hub vitrine « Packs métiers offerts » — /packs.
 *
 * La porte d'entrée grand public du Business OS : un pack OFFERT par corps
 * de métier. Chaque carte mène vers la page du pack (wizard pour les
 * restaurants, page dédiée pour les autres verticales).
 */

import Link from 'next/link';
import { useSearchParams } from 'next/navigation';
import { motion } from 'framer-motion';
import { ArrowRight, Check, Fuel, Gift, GraduationCap, Plane, UtensilsCrossed } from 'lucide-react';
import { useDarkMode } from '@/modules/vitrine/hooks/useDarkMode';
import { Navbar, Footer } from '@/modules/vitrine';
import { HeroScene3D } from '@/modules/vitrine/components/hero/HeroScene3D';
import { PACKS_HUB_COPY, packsHubCardHref } from '@/modules/vitrine/data/pack-pages';
import { useVitrineLocale } from '@/modules/vitrine/lib/vitrine-locale';
import { withLocaleHref } from '@/modules/vitrine/lib/locale-href';

const CARD_ICONS = [UtensilsCrossed, Fuel, GraduationCap, Plane];

export default function PacksHubPage() {
  const { isDark, toggleDarkMode } = useDarkMode();
  const { direction, locale } = useVitrineLocale();
  const searchParams = useSearchParams();
  const search = searchParams.toString();
  const copy = PACKS_HUB_COPY[locale] ?? PACKS_HUB_COPY.fr;

  return (
    <div dir={direction} className={`min-h-screen transition-colors duration-500 ${isDark ? 'dark bg-slate-950' : 'bg-white'}`}>
      <Navbar isDark={isDark} onToggleDark={toggleDarkMode} />

      <section className="relative overflow-hidden pt-32 pb-20">
        <div className="absolute inset-0 bg-gradient-to-br from-slate-50 via-emerald-50/30 to-cyan-50/20 dark:from-slate-950 dark:via-emerald-950/20 dark:to-cyan-950/10" />
        <HeroScene3D />

        <div className="relative mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 text-center">
          <motion.div
            initial={{ opacity: 0, y: 24 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.55 }}
          >
            <div className="mb-6 inline-flex items-center gap-2 rounded-full border border-emerald-300/40 bg-emerald-100/70 px-4 py-2 text-sm font-bold text-emerald-800 dark:border-emerald-400/30 dark:bg-emerald-400/10 dark:text-emerald-200">
              <Gift className="h-4 w-4" aria-hidden="true" />
              {copy.badge}
            </div>
            <h1 className="text-4xl font-black tracking-tight text-slate-900 dark:text-white sm:text-5xl">
              {copy.title}{' '}
              <span className="bg-gradient-to-r from-emerald-600 to-cyan-600 bg-clip-text text-transparent dark:from-emerald-300 dark:to-cyan-300">
                {copy.highlight}
              </span>
            </h1>
            <p className="mx-auto mt-6 max-w-2xl text-lg leading-8 text-slate-600 dark:text-slate-300">
              {copy.subtitle}
            </p>
            <div className="mt-8 flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-sm font-semibold text-slate-700 dark:text-slate-200">
              {copy.points.map((point) => (
                <span key={point} className="inline-flex items-center gap-1.5">
                  <Check className="h-4 w-4 text-emerald-500" aria-hidden="true" />
                  {point}
                </span>
              ))}
            </div>
          </motion.div>
        </div>
      </section>

      <section className="relative pb-24">
        <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
          <div className="grid gap-6 sm:grid-cols-2">
            {copy.cards.map((card, index) => {
              const Icon = CARD_ICONS[index] ?? Gift;
              return (
                <motion.div
                  key={card.slug}
                  initial={{ y: 24, opacity: 0 }}
                  whileInView={{ y: 0, opacity: 1 }}
                  viewport={{ once: true }}
                  transition={{ duration: 0.5, delay: index * 0.08 }}
                >
                  <Link
                    href={withLocaleHref(packsHubCardHref(card.slug), search)}
                    className="group relative flex h-full flex-col rounded-2xl border border-slate-200/80 bg-white/80 p-6 backdrop-blur transition hover:border-emerald-300 hover:shadow-xl dark:border-slate-800/80 dark:bg-slate-900/70 dark:hover:border-emerald-700"
                  >
                    <span className="absolute right-5 top-5 inline-flex items-center rounded-full bg-emerald-500/10 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-emerald-700 ring-1 ring-emerald-500/25 dark:text-emerald-300">
                      {copy.freeBadge}
                    </span>
                    <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400">
                      <Icon className="h-6 w-6" aria-hidden="true" />
                    </div>
                    <h2 className="mt-4 text-xl font-bold text-slate-900 dark:text-white">{card.name}</h2>
                    <p className="mt-2 flex-1 text-sm leading-6 text-slate-600 dark:text-slate-400">{card.line}</p>
                    <span className="mt-4 inline-flex items-center gap-1.5 text-sm font-semibold text-emerald-600 dark:text-emerald-400">
                      {card.linkLabel}
                      <ArrowRight className="h-4 w-4 transition-transform group-hover:translate-x-1" aria-hidden="true" />
                    </span>
                  </Link>
                </motion.div>
              );
            })}
          </div>
          <motion.p
            initial={{ opacity: 0 }}
            whileInView={{ opacity: 1 }}
            viewport={{ once: true }}
            transition={{ duration: 0.6, delay: 0.3 }}
            className="mt-10 text-center text-sm text-slate-500 dark:text-slate-400"
          >
            {copy.note}
          </motion.p>
        </div>
      </section>

      <Footer />
    </div>
  );
}
