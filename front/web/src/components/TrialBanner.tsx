'use client';

import Link from 'next/link';
import { ArrowRight, CalendarClock, Sparkles } from 'lucide-react';
import { t } from '@/lib/i18n/locale-catalog';
import type { AppLocale, StoredAuthUser } from '@/lib/i18n';

/**
 * #7235 — Bandeau d'essai.
 *
 * Les boutons « tester 14 jours » de la vitrine disparaissent : on s'inscrit
 * directement, et c'est l'APPLICATION qui porte l'information — jours d'essai
 * restants puis passage au plan supérieur. Le bandeau ne s'affiche que pour un
 * tenant réellement en essai et dont la date de fin est connue : jamais
 * d'invention de date côté client (un payload sans `subscription_end` ne rend
 * rien plutôt qu'un compte à rebours faux).
 */
export function TrialBanner({
  user,
  locale,
  variant = 'bar',
}: {
  user?: StoredAuthUser | null;
  locale: AppLocale;
  /**
   * `bar` (défaut) : bandeau pleine largeur sous l'en-tête.
   * `compact` : pastille pour la barre du haut (à côté de la langue), sur
   * demande du PM — l'essai ne doit plus prendre une ligne entière.
   */
  variant?: 'bar' | 'compact';
}) {
  const status = (user?.company?.status ?? '').toString().toLowerCase();
  const subscriptionEnd = user?.company?.subscription_end;

  if (status !== 'trial' || !subscriptionEnd) {
    return null;
  }

  // La date vient de l'API au format `YYYY-MM-DD` : on raisonne en JOURS
  // CALENDAIRES (minuit à minuit). Un calcul sur l'instant courant
  // (`Date.now()`) décalait d'un jour — une échéance « aujourd'hui + 5 »
  // s'affichait « 6 jours restants » (relevé par le test e2e).
  const endDate = new Date(`${subscriptionEnd}T00:00:00`);
  if (Number.isNaN(endDate.getTime())) {
    return null;
  }
  const today = new Date();
  today.setHours(0, 0, 0, 0);

  const daysLeft = Math.round((endDate.getTime() - today.getTime()) / 86_400_000);
  const expired = daysLeft < 0;
  const isLastDay = daysLeft === 0;
  // Le dernier jour reste « dernier jour » (et non « 1 jour restant ») : le
  // libellé est plus clair le jour même de l'échéance.

  const message = expired
    ? t(locale, 'trial.endedTitle')
    : isLastDay
      ? t(locale, 'trial.lastDay')
      : t(locale, 'trial.daysLeft').replace('{n}', String(daysLeft));

  if (variant === 'compact') {
    return (
      <Link
        href="/billing"
        data-testid="trial-badge"
        data-trial-days-left={daysLeft}
        title={expired ? t(locale, 'trial.endedTitle') : message}
        className={`inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1 text-[11px] font-bold transition ${
          expired
            ? 'border-red-300 bg-red-50 text-red-700 hover:bg-red-100 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300'
            : 'border-amber-300 bg-amber-50 text-amber-800 hover:bg-amber-100 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-300'
        }`}
      >
        {expired ? <CalendarClock className="h-3.5 w-3.5" aria-hidden="true" /> : <Sparkles className="h-3.5 w-3.5" aria-hidden="true" />}
        <span>{message}</span>
      </Link>
    );
  }

  return (
    <div
      data-testid="trial-banner"
      data-trial-days-left={daysLeft}
      className={`border-b px-4 py-2.5 md:px-8 ${
        expired
          ? 'border-amber-300/60 bg-amber-50 dark:border-amber-900/60 dark:bg-amber-950/30'
          : 'border-emerald-200/60 bg-emerald-50 dark:border-emerald-900/60 dark:bg-emerald-950/30'
      }`}
    >
      <div className="mx-auto flex w-full max-w-7xl flex-wrap items-center justify-between gap-3">
        <div className="flex min-w-0 items-center gap-2.5">
          <span
            className={`flex h-7 w-7 shrink-0 items-center justify-center rounded-lg ${
              expired ? 'bg-amber-500 text-white' : 'bg-emerald-500 text-white'
            }`}
          >
            {expired ? (
              <Sparkles className="h-4 w-4" aria-hidden="true" />
            ) : (
              <CalendarClock className="h-4 w-4" aria-hidden="true" />
            )}
          </span>
          <p className="min-w-0 text-xs font-bold leading-5 text-slate-800 dark:text-slate-100">
            <span className="mr-1.5 rounded-full bg-white/70 px-2 py-0.5 text-[10px] font-black uppercase tracking-wide text-slate-600 dark:bg-slate-900/60 dark:text-slate-300">
              {t(locale, 'trial.trialBadge')}
            </span>
            {message}
            {expired && (
              <span className="ml-1 font-medium text-slate-600 dark:text-slate-300">
                {t(locale, 'trial.endedBody')}
              </span>
            )}
          </p>
        </div>

        <Link
          href="/billing"
          data-testid="trial-upgrade"
          className="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-bold text-white transition hover:bg-slate-800 dark:bg-white dark:text-slate-900 dark:hover:bg-slate-200"
        >
          {expired ? t(locale, 'trial.manageCta') : t(locale, 'trial.upgradeCta')}
          <ArrowRight className="h-3.5 w-3.5" aria-hidden="true" />
        </Link>
      </div>
    </div>
  );
}
