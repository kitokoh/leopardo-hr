'use client';

/**
 * Page vitrine « Pack offert » par métier — /packs/[vertical].
 *
 * Même promesse que /restaurateur : le pack métier est OFFERT, activé à la
 * création de l'espace. La page sert les verticales qui n'ont pas (encore) de
 * wizard dédié : station-service, école, agence de voyage (cf.
 * `data/pack-pages.ts`). Le héro réutilise la constellation WebGL du site —
 * vivante, avec repli 2D automatique.
 */

import Link from 'next/link';
import { notFound, useSearchParams } from 'next/navigation';
import { use } from 'react';
import { motion } from 'framer-motion';
import {
  BarChart3,
  CalendarDays,
  Check,
  Clock,
  FileText,
  Gift,
  ShieldCheck,
  Store,
  Ticket,
  Users,
  Wallet,
} from 'lucide-react';
import { useDarkMode } from '@/modules/vitrine/hooks/useDarkMode';
import { Navbar, Footer } from '@/modules/vitrine';
import { HeroScene3D } from '@/modules/vitrine/components/hero/HeroScene3D';
import { Button } from '@/modules/vitrine/components/common';
import {
  isPackVertical,
  PACK_PAGES,
  type PackBenefitIcon,
} from '@/modules/vitrine/data/pack-pages';
import { useVitrineLocale } from '@/modules/vitrine/lib/vitrine-locale';
import { withLocaleHref } from '@/modules/vitrine/lib/locale-href';

const ICONS: Record<PackBenefitIcon, typeof Clock> = {
  clock: Clock,
  calendar: CalendarDays,
  chart: BarChart3,
  users: Users,
  file: FileText,
  wallet: Wallet,
  ticket: Ticket,
  store: Store,
  shield: ShieldCheck,
};

export default function PackVerticalPage({
  params,
}: {
  params: Promise<{ vertical: string }>;
}) {
  const { vertical } = use(params);
  const { isDark, toggleDarkMode } = useDarkMode();
  const { direction, locale } = useVitrineLocale();
  const searchParams = useSearchParams();
  const search = searchParams.toString();

  if (!isPackVertical(vertical)) {
    notFound();
  }
  const copy = PACK_PAGES[vertical][locale] ?? PACK_PAGES[vertical].fr;

  return (
    <div dir={direction} className={`min-h-screen transition-colors duration-500 ${isDark ? 'dark bg-slate-950' : 'bg-white'}`}>
      <Navbar isDark={isDark} onToggleDark={toggleDarkMode} />

      {/* ─── Héro « Pack offert » sur constellation 3D ─── */}
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
            <div className="mt-10 flex flex-wrap items-center justify-center gap-4">
              <Button
                size="lg"
                onClick={() => { window.location.href = withLocaleHref('/signup', search); }}
              >
                {copy.ctaPrimary}
              </Button>
              <Link
                href={withLocaleHref('/contact', search)}
                className="inline-flex items-center gap-2 rounded-xl border border-slate-300 px-6 py-3.5 text-base font-bold text-slate-700 transition hover:border-emerald-500 hover:text-emerald-700 dark:border-slate-700 dark:text-slate-200 dark:hover:text-emerald-300"
              >
                {copy.ctaSecondary}
              </Link>
            </div>
          </motion.div>
        </div>
      </section>

      {/* ─── Bénéfices métier ─── */}
      <section className="relative py-20">
        <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
          <motion.h2
            initial={{ y: 20 }}
            whileInView={{ y: 0 }}
            viewport={{ once: true }}
            transition={{ duration: 0.5 }}
            className="text-center text-3xl font-black tracking-tight text-slate-900 dark:text-white sm:text-4xl"
          >
            {copy.benefitsTitle}
          </motion.h2>
          <div className="mt-12 grid gap-6 sm:grid-cols-2">
            {copy.benefits.map((benefit, index) => {
              const Icon = ICONS[benefit.icon] ?? Clock;
              return (
                <motion.article
                  key={benefit.title}
                  initial={{ y: 24, opacity: 0 }}
                  whileInView={{ y: 0, opacity: 1 }}
                  viewport={{ once: true }}
                  transition={{ duration: 0.5, delay: index * 0.08 }}
                  className="rounded-2xl border border-slate-200/80 bg-slate-50/60 p-6 transition hover:border-emerald-300 hover:shadow-lg dark:border-slate-800/80 dark:bg-slate-900/60 dark:hover:border-emerald-700"
                >
                  <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400">
                    <Icon className="h-6 w-6" aria-hidden="true" />
                  </div>
                  <h3 className="mt-4 text-lg font-bold text-slate-900 dark:text-white">{benefit.title}</h3>
                  <p className="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-400">{benefit.text}</p>
                </motion.article>
              );
            })}
          </div>
        </div>
      </section>

      {/* ─── Inclus dans le pack + CTA final ─── */}
      <section className="relative overflow-hidden bg-slate-950 py-20 text-white">
        <div className="absolute inset-0 bg-[linear-gradient(135deg,rgba(16,185,129,0.14),transparent_36%,rgba(34,211,238,0.10))]" />
        <div className="relative mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 text-center">
          <motion.div
            initial={{ y: 24 }}
            whileInView={{ y: 0 }}
            viewport={{ once: true }}
            transition={{ duration: 0.55 }}
          >
            <h2 className="text-3xl font-black tracking-tight sm:text-4xl">{copy.includedTitle}</h2>
            <ul className="mx-auto mt-10 grid max-w-2xl gap-3 text-left sm:grid-cols-2">
              {copy.included.map((item) => (
                <li
                  key={item}
                  className="flex items-start gap-3 rounded-xl border border-white/10 bg-white/[0.06] px-4 py-3 text-sm font-semibold text-slate-100"
                >
                  <Check className="mt-0.5 h-4 w-4 shrink-0 text-emerald-400" aria-hidden="true" />
                  {item}
                </li>
              ))}
            </ul>
            <div className="mt-10">
              <Button
                size="lg"
                onClick={() => { window.location.href = withLocaleHref('/signup', search); }}
              >
                {copy.ctaPrimary}
              </Button>
              <p className="mt-4 text-sm text-slate-400">{copy.note}</p>
            </div>
          </motion.div>
        </div>
      </section>

      <Footer />
    </div>
  );
}
