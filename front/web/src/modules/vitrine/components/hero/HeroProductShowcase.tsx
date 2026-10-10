'use client';

/**
 * HeroProductShowcase — visuel du héro produit-first (#8067).
 *
 * Montre le produit réel dès le héro (pattern des leaders du segment) :
 * - Screenshot produit réel (dashboard de présence, `public/screenshots/`)
 *   dans un cadre navigateur (`HeroProductVisual`, image `priority` → LCP).
 * - Badge de réassurance « cloud ou auto-hébergé » : la promesse Business OS
 *   est la maîtrise de l'outil, pas un modèle de licence. Aucun appel réseau,
 *   le badge est statique au build.
 * La mascotte Leo reste un élément de marque secondaire (onboarding,
 * SolutionStack, artworks) — déplacée, pas supprimée.
 */

import { Cloud, Server, ShieldCheck } from 'lucide-react';
import type { AppLocale } from '@/lib/i18n';
import { HeroProductVisual } from '@/modules/vitrine/components/sections/HeroProductVisual';

export interface HeroProductShowcaseProps {
  locale: AppLocale;
}

/** Alt localisé — l'image porte la preuve produit, elle n'est pas décorative. */
const ALT_TEXT: Record<AppLocale, string> = {
  fr: 'Tableau de bord Leopardo : présence des équipes en temps réel, pointages et indicateurs de paie.',
  en: 'Leopardo dashboard: real-time team attendance, clock-ins and payroll indicators.',
  tr: 'Leopardo panosu: gerçek zamanlı ekip yoklaması, giriş-çıkışlar ve bordro göstergeleri.',
  ar: 'لوحة تحكم Leopardo: حضور الفرق في الوقت الفعلي، تسجيلات الدخول ومؤشرات الرواتب.',
};

const BADGE_LABEL: Record<AppLocale, string> = {
  fr: 'Cloud ou auto-hébergé — vous choisissez',
  en: 'Cloud or self-hosted — your choice',
  tr: 'Bulut veya kendi sunucunuz — seçim sizin',
  ar: 'السحابة أو خادمك الخاص — الخيار لك',
};

const BADGE_NOTE: Record<AppLocale, string> = {
  fr: 'Vos données, chez vous',
  en: 'Your data, your server',
  tr: 'Verileriniz sizde',
  ar: 'بياناتك عندك',
};

export function HeroTrustBadge({ locale }: HeroProductShowcaseProps) {
  const label = BADGE_LABEL[locale] ?? BADGE_LABEL.fr;
  const note = BADGE_NOTE[locale] ?? BADGE_NOTE.fr;
  return (
    <a
      href="/pricing"
      aria-label={label}
      data-testid="hero-trust-badge"
      className="group mx-auto mt-4 inline-flex items-center gap-3 rounded-full border border-slate-200 bg-white/80 px-4 py-2 text-xs font-semibold text-slate-700 shadow-sm backdrop-blur transition-colors hover:border-emerald-400 hover:text-emerald-700 dark:border-slate-700 dark:bg-slate-900/80 dark:text-slate-300 dark:hover:text-emerald-400"
    >
      <span className="inline-flex items-center gap-1.5">
        <Cloud className="h-4 w-4 text-emerald-600 dark:text-emerald-400" aria-hidden="true" />
        <Server className="h-4 w-4 text-cyan-600 dark:text-cyan-400" aria-hidden="true" />
        <span>{label}</span>
      </span>
      <span className="inline-flex items-center gap-1 text-slate-500 dark:text-slate-400">
        <ShieldCheck className="h-3.5 w-3.5 text-emerald-500" aria-hidden="true" />
        {note}
      </span>
    </a>
  );
}

export function HeroProductShowcase({ locale }: HeroProductShowcaseProps) {
  const alt = ALT_TEXT[locale] ?? ALT_TEXT.fr;
  return (
    <div className="relative mx-auto flex w-full max-w-2xl flex-col items-center px-2 sm:px-4">
      {/* Halo de marque conservé derrière le cadre (tokens produit, P05). */}
      <div aria-hidden="true" className="absolute inset-0 -z-10 flex items-center justify-center">
        <div className="h-3/4 w-3/4 rounded-full bg-gradient-to-br from-emerald-400/25 via-cyan-400/15 to-transparent blur-3xl dark:from-emerald-500/20 dark:via-cyan-500/10" />
      </div>
      <HeroProductVisual src="/screenshots/web-dashboard.png" alt={alt} />
      <HeroTrustBadge locale={locale} />
    </div>
  );
}

export default HeroProductShowcase;
