'use client';

import { useState } from 'react';
import { useDarkMode } from '@/modules/vitrine/hooks/useDarkMode';
import { Clock3, ShieldCheck, Sparkles } from 'lucide-react';
import { Footer, HeroSection, Navbar, useScrollReveal } from '@/modules/vitrine';
import { SignupForm } from '@/modules/vitrine/components/forms';
import { useVitrineLocale } from '@/modules/vitrine/lib/vitrine-locale';
import type { AppLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';

type SignupCopy = {
  hero: {
    badge: string;
    headline: string;
    subheadline: string;
    cta: string;
  };
  sideBadge: string;
  title: string;
  stepsTitle: string;
  steps: string[];
};

/**
 * #7235 — le récit de la page vit dans le catalogue i18n partagé
 * (`shared/i18n/locales/*.json` → clé `signupPage`), pas dans le composant :
 * aucun littéral utilisateur ici, et les 4 langues restent synchronisées par
 * l'outillage i18n du dépôt.
 */
function buildSignupCopy(locale: AppLocale): SignupCopy {
  const k = (key: string) => t(locale, `signupPage.${key}`);
  return {
    hero: {
      badge: k('badge'),
      headline: k('headline'),
      subheadline: k('subheadline'),
      cta: k('cta'),
    },
    sideBadge: k('sideBadge'),
    title: k('sideTitle'),
    stepsTitle: k('stepsTitle'),
    steps: [k('step1'), k('step2'), k('step3')],
  };
}

export default function SignupPage() {
  const { isDark, toggleDarkMode } = useDarkMode();
  const { locale, direction } = useVitrineLocale();
  const copy = buildSignupCopy(locale);
  useScrollReveal();

  return (
    <div
      dir={direction}
      className={`min-h-screen transition-colors duration-500 ${isDark ? 'dark bg-slate-950' : 'bg-white'}`}
    >
      <Navbar isDark={isDark} onToggleDark={toggleDarkMode} />

      <HeroSection
        headline={copy.hero.headline}
        subheadline={copy.hero.subheadline}
        ctaPrimary={{ text: copy.hero.cta, href: '#signup-form' }}
        badge={{
          text: copy.hero.badge,
          icon: <Sparkles className="w-3 h-3" />,
        }}
      />

      <main id="signup-form" className="relative overflow-hidden py-24">
        <div className="absolute inset-0 bg-gradient-to-b from-white via-slate-50/60 to-white dark:from-slate-950 dark:via-slate-900/50 dark:to-slate-950" />

        <div className="relative mx-auto grid max-w-6xl grid-cols-1 items-start gap-12 px-4 sm:px-6 lg:grid-cols-[0.9fr_1.1fr] lg:px-8">
          <section>
            <div className="mb-5 inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-sm font-semibold text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300">
              <ShieldCheck className="h-4 w-4" />
              {copy.sideBadge}
            </div>
            <h2 className="mb-8 text-3xl font-black tracking-tight text-slate-950 dark:text-white sm:text-4xl">
              {copy.title}
            </h2>

            <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900/70">
              <div className="mb-4 flex items-center gap-2 text-sm font-bold text-slate-900 dark:text-white">
                <Clock3 className="h-4 w-4 text-emerald-500" />
                {copy.stepsTitle}
              </div>
              <ol className="space-y-3 text-sm leading-6 text-slate-600 dark:text-slate-300">
                {copy.steps.map((step, index) => (
                  <li key={step} className="flex gap-3">
                    <span className="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-bold text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                      {index + 1}
                    </span>
                    <span>{step}</span>
                  </li>
                ))}
              </ol>
            </div>
          </section>

          <SignupForm page="/signup" />
        </div>
      </main>

      <Footer />
    </div>
  );
}
