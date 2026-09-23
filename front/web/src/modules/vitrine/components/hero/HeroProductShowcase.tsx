'use client';

/**
 * HeroProductShowcase — visuel du héro produit-first (#8067).
 *
 * Remplace la mascotte Leo comme visuel principal : 8 des 9 concurrents du
 * benchmark montrent le produit dès le héro, et pour un produit open-source
 * peu connu le dépôt public est la preuve sociale initiale (pattern Frappe HR).
 * - Screenshot produit réel (dashboard de présence, `public/screenshots/`)
 *   dans un cadre navigateur (`HeroProductVisual`, image `priority` → LCP).
 * - Badge GitHub statique au build (stars, licence, lien repo) — aucun appel
 *   client à l'API GitHub (`data/github-repo.ts`).
 * La mascotte Leo reste un élément de marque secondaire (onboarding,
 * SolutionStack, artworks) — déplacée, pas supprimée.
 */

import { Star, GitFork, Scale } from 'lucide-react';
import type { AppLocale } from '@/lib/i18n';
import { HeroProductVisual } from '@/modules/vitrine/components/sections/HeroProductVisual';
import {
  GITHUB_FORKS,
  GITHUB_LICENSE,
  GITHUB_REPO_SLUG,
  GITHUB_REPO_URL,
  GITHUB_STARS,
} from '@/modules/vitrine/data/github-repo';

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
  fr: 'Code source ouvert sur GitHub',
  en: 'Open source on GitHub',
  tr: 'GitHub üzerinde açık kaynak',
  ar: 'مفتوح المصدر على GitHub',
};

export function HeroGithubBadge({ locale }: HeroProductShowcaseProps) {
  const label = BADGE_LABEL[locale] ?? BADGE_LABEL.fr;
  return (
    <a
      href={GITHUB_REPO_URL}
      target="_blank"
      rel="noopener noreferrer"
      aria-label={`${label} — ${GITHUB_REPO_SLUG}`}
      data-testid="hero-github-badge"
      className="group mx-auto mt-4 inline-flex items-center gap-3 rounded-full border border-slate-200 bg-white/80 px-4 py-2 text-xs font-semibold text-slate-700 shadow-sm backdrop-blur transition-colors hover:border-emerald-400 hover:text-emerald-700 dark:border-slate-700 dark:bg-slate-900/80 dark:text-slate-300 dark:hover:text-emerald-400"
    >
      <span className="inline-flex items-center gap-1.5">
        {/* Logo GitHub (mark officiel, monochrome) */}
        <svg viewBox="0 0 16 16" aria-hidden="true" className="h-4 w-4 fill-current">
          <path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27s1.36.09 2 .27c1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.01 8.01 0 0 0 16 8c0-4.42-3.58-8-8-8Z" />
        </svg>
        <span>{label}</span>
      </span>
      <span className="inline-flex items-center gap-1 text-slate-500 dark:text-slate-400">
        <Star className="h-3.5 w-3.5 text-amber-500" aria-hidden="true" />
        {GITHUB_STARS}
      </span>
      <span className="inline-flex items-center gap-1 text-slate-500 dark:text-slate-400">
        <GitFork className="h-3.5 w-3.5" aria-hidden="true" />
        {GITHUB_FORKS}
      </span>
      <span className="inline-flex items-center gap-1 text-slate-500 dark:text-slate-400">
        <Scale className="h-3.5 w-3.5" aria-hidden="true" />
        {GITHUB_LICENSE}
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
      <HeroGithubBadge locale={locale} />
    </div>
  );
}

export default HeroProductShowcase;
