'use client';

/**
 * #8489 (I-02) — point d'entree « Trade » de la vitrine (/trade).
 *
 * La porte d'entree nommee du Leopardo Business OS : elle prolonge le
 * catalogue de solutions existant (/packs, /restaurateur) sans le dupliquer —
 * elle repond d'abord au CHOIX DE MODELE (International / Domestique) puis a
 * l'ACTION (creer un espace / rejoindre une entreprise), la ou /packs entre
 * par le metier. Aucun nouveau tenant, role ou permission n'est cree ici.
 *
 * Le mode est preselectionnable par `?mode=` (lien de campagne), normalise
 * fail-closed par `normalizeTradeIntent` — une valeur inconnue retombe sur le
 * defaut, jamais sur un mode invente.
 */

import Link from 'next/link';
import { motion } from 'framer-motion';
import { Check, Compass } from 'lucide-react';
import { useSearchParams } from 'next/navigation';
import { Navbar, Footer } from '@/modules/vitrine';
import { HeroScene3D } from '@/modules/vitrine/components/hero/HeroScene3D';
import { TradeOnboardingChoice } from '@/modules/vitrine/components/TradeOnboardingChoice';
import { useVitrineLocale } from '@/modules/vitrine/lib/vitrine-locale';
import { withLocaleHref } from '@/modules/vitrine/lib/locale-href';
import { t } from '@/lib/i18n/locale-catalog';
import { normalizeTradeIntent } from '@/modules/vitrine/lib/trade-intent';

export default function TradePage() {
  const { direction, locale } = useVitrineLocale();
  const searchParams = useSearchParams();
  const search = searchParams.toString();
  const tr = (key: string) => t(locale, `vitrine.trade.${key}`);

  // Fail-closed : seul international/domestic est accepte ; tout le reste
  // (absent, valeur inconnue) retombe sur le defaut normalise.
  const intent = normalizeTradeIntent({ mode: searchParams.get('mode') });

  const steps = [tr('step1'), tr('step2'), tr('step3'), tr('step4')];

  return (
    <div
      dir={direction}
      className={`min-h-screen bg-white `}
    >
      <Navbar />

      <section className="relative overflow-hidden pt-32 pb-16">
        <div className="absolute inset-0 bg-gradient-to-br from-slate-50 via-emerald-50/30 to-cyan-50/20 dark:from-slate-950 dark:via-emerald-950/20 dark:to-cyan-950/10" />
        <HeroScene3D dimmed />

        <div className="relative mx-auto max-w-4xl px-4 text-center sm:px-6 lg:px-8">
          <motion.div
            initial={{ opacity: 0, y: 24 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.55 }}
          >
            <div className="mb-6 inline-flex items-center gap-2 rounded-full border border-emerald-300/40 bg-emerald-100/70 px-4 py-2 text-sm font-bold text-emerald-800 dark:border-emerald-400/30 dark:bg-emerald-400/10 dark:text-emerald-200">
              <Compass className="h-4 w-4" aria-hidden="true" />
              {tr('badge')}
            </div>
            <h1 className="text-4xl font-black tracking-tight text-slate-900 dark:text-white sm:text-5xl">
              {tr('title')}{' '}
              <span className="bg-gradient-to-r from-emerald-600 to-cyan-600 bg-clip-text text-transparent dark:from-emerald-300 dark:to-cyan-300">
                {tr('highlight')}
              </span>
            </h1>
            <p className="mx-auto mt-6 max-w-2xl text-lg leading-8 text-slate-600 dark:text-slate-300">
              {tr('subtitle')}
            </p>
            <p className="mt-4 text-sm font-semibold text-slate-700 dark:text-slate-200">
              {tr('reassurance')}
            </p>
            <p className="mt-6 text-sm font-bold uppercase tracking-[0.2em] text-emerald-700 dark:text-emerald-300">
              {tr('modesTitle')}
            </p>
          </motion.div>
        </div>
      </section>

      <section className="relative pb-16">
        <div className="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
          <TradeOnboardingChoice initialMode={intent.mode} />
        </div>
      </section>

      <section className="relative pb-24">
        <div className="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
          <h2 className="text-center text-xl font-bold text-slate-900 dark:text-white">
            {tr('stepsTitle')}
          </h2>
          <ol className="mt-6 grid grid-cols-1 gap-3 sm:grid-cols-2">
            {steps.map((step, index) => (
              <li
                key={step}
                className="flex items-start gap-3 rounded-xl border border-slate-200/80 p-4 text-sm text-slate-700 dark:border-slate-800/80 dark:text-slate-300"
              >
                <span className="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-emerald-500/10 text-xs font-bold text-emerald-700 dark:text-emerald-300">
                  {index + 1}
                </span>
                {step}
              </li>
            ))}
          </ol>
          <p className="mt-6 text-center text-xs text-slate-500 dark:text-slate-400">
            {tr('note')}
          </p>
          <div className="mt-8 flex flex-wrap items-center justify-center gap-3">
            <Link
              href={withLocaleHref('/', search)}
              className="inline-flex items-center rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-900"
            >
              {tr('backHome')}
            </Link>
            <span className="inline-flex items-center gap-1.5 text-sm font-semibold text-emerald-700 dark:text-emerald-300">
              <Check className="h-4 w-4" aria-hidden="true" />
              {tr('reassurance')}
            </span>
          </div>
        </div>
      </section>

      <Footer />
    </div>
  );
}
