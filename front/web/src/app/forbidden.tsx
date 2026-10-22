import Link from 'next/link';
import type { Metadata } from 'next';
import { headers } from 'next/headers';
import { normalizeLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';

/**
 * BOS-B-02 — page 403 dédiée (App Router).
 *
 * Avant ce fichier, un rôle insuffisant n'avait AUCUNE page dédiée : l'API
 * renvoyait un message brut, ou `forbidden()` (activé par
 * `experimental.authInterrupts`) tombait sur la frontière d'erreur 500 — un
 * utilisateur légitime mais non autorisé voyait « quelque chose s'est mal
 * passé » au lieu de « votre rôle ne permet pas cette action ».
 *
 * Le texte visible vient du catalogue partagé (`vitrine.forbidden.*`, ×4
 * locales, propagé par `shared/i18n/sync`), résolu via `x-vitrine-lang` —
 * même mécanique que `not-found.tsx` (#7664) et `error.tsx` (#8497). Aucune
 * chaîne en dur : la garde PA2-I18N-014 (`check-i18n-diff.js`) la refuserait.
 *
 * Aucune fuite technique : ni identifiant de permission, ni nom de route, ni
 * trace — seulement le constat et une sortie utile (accueil, demande d'accès).
 */
export async function generateMetadata(): Promise<Metadata> {
  const headerList = await headers();
  const locale = normalizeLocale(headerList.get('x-vitrine-lang') ?? '');

  return {
    title: t(locale, 'vitrine.forbidden.title'),
    robots: { index: false, follow: false },
  };
}

export default async function Forbidden() {
  const headerList = await headers();
  const locale = normalizeLocale(headerList.get('x-vitrine-lang') ?? '');

  return (
    <main className="flex min-h-screen items-center justify-center bg-slate-50 px-6 py-16 dark:bg-slate-950">
      <div className="w-full max-w-lg text-center">
        {/* Mascotte de marque sur les états hors parcours ; `alt` localisé
            (vitrine.forbidden.mascotAlt, ×4) — jamais de chaîne en dur. */}
        {/* eslint-disable-next-line @next/next/no-img-element -- illustration de marque statique */}
        <img
          src="/brand/leopard-404.png"
          alt={t(locale, 'vitrine.forbidden.mascotAlt')}
          width={200}
          height={200}
          className="mx-auto mb-6 h-auto w-40 sm:w-48"
        />
        <p className="text-sm font-semibold uppercase tracking-[0.2em] text-amber-600 dark:text-amber-400">
          {t(locale, 'vitrine.forbidden.kicker')}
        </p>
        <h1 className="mt-4 text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl dark:text-white">
          {t(locale, 'vitrine.forbidden.title')}
        </h1>
        <p className="mt-4 text-base leading-relaxed text-slate-600 dark:text-slate-400">
          {t(locale, 'vitrine.forbidden.body')}
        </p>
        <div className="mt-8 flex flex-wrap items-center justify-center gap-3">
          <Link
            href="/"
            className="inline-flex items-center rounded-xl bg-brand-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2"
          >
            {t(locale, 'vitrine.forbidden.ctaHome')}
          </Link>
          <Link
            href="/contact"
            className="inline-flex items-center rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-900"
          >
            {t(locale, 'vitrine.forbidden.ctaSupport')}
          </Link>
        </div>
      </div>
    </main>
  );
}
