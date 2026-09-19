import Link from 'next/link';
import type { Metadata } from 'next';
import { headers } from 'next/headers';
import { normalizeLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';

/**
 * La 404 reprenait le titre de la page d'accueil (« Leopardo RH - SaaS RH
 * multilingue… ») : vérifié sur le HTML servi le 2026-09-16. Un onglet qui
 * annonce l'accueil quand la page n'existe pas, et une 404 indexable.
 *
 * Le libellé vient du catalogue i18n existant (`showcase.notFoundTitle`, ×4 (libellé accentué dans les 4 langues)), pas
 * d'un littéral : la garde PA2-I18N-014 a raison de refuser une chaîne en dur
 * dans du texte visible.
 */
export async function generateMetadata(): Promise<Metadata> {
  const headerList = await headers();
  const locale = normalizeLocale(headerList.get('x-vitrine-lang') ?? '');

  return {
    title: t(locale, 'showcase.notFoundTitle'),
    robots: { index: false, follow: true },
  };
}

/**
 * 404 global de l'application.
 *
 * Avant ce fichier, tout `notFound()` (ou toute URL inconnue) tombait sur la
 * page 404 par défaut de Next.js : aucune identité visuelle, aucun chemin de
 * retour vers le produit. Ce composant rend un 404 cohérent avec la marque et
 * propose des sorties utiles.
 *
 * #7664 — le corps était resté 100 % français en dur alors que le titre
 * (metadata) était déjà localisé : un visiteur en/tr/ar tombait sur une page
 * d'erreur illisible. Tout le texte visible vient désormais du catalogue
 * (`vitrine.notFound.*`, ×4 locales), locale résolue via `x-vitrine-lang`
 * (même mécanique que `generateMetadata`, #4004).
 */
export default async function NotFound() {
  const headerList = await headers();
  const locale = normalizeLocale(headerList.get('x-vitrine-lang') ?? '');

  return (
    <main className="flex min-h-screen items-center justify-center bg-slate-50 px-6 py-16 dark:bg-slate-950">
      <div className="w-full max-w-lg text-center">
        <p className="text-sm font-semibold uppercase tracking-[0.2em] text-brand-600 dark:text-brand-400">
          {t(locale, 'vitrine.notFound.kicker', 'Erreur 404')}
        </p>
        <h1 className="mt-4 text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl dark:text-white">
          {t(locale, 'vitrine.notFound.title', 'Page introuvable')}
        </h1>
        <p className="mt-4 text-base leading-relaxed text-slate-600 dark:text-slate-400">
          {t(
            locale,
            'vitrine.notFound.body',
            "Cette page n'existe pas ou a été déplacée. Vérifiez l'adresse, ou reprenez depuis l'accueil.",
          )}
        </p>
        <div className="mt-8 flex flex-wrap items-center justify-center gap-3">
          <Link
            href="/"
            className="inline-flex items-center rounded-xl bg-brand-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2"
          >
            {t(locale, 'vitrine.notFound.ctaHome', "Retour à l'accueil")}
          </Link>
          <Link
            href="/pricing"
            className="inline-flex items-center rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-900"
          >
            {t(locale, 'vitrine.notFound.ctaPricing', 'Voir les tarifs')}
          </Link>
          <Link
            href="/contact"
            className="inline-flex items-center rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-900"
          >
            {t(locale, 'vitrine.notFound.ctaContact', 'Contacter le support')}
          </Link>
        </div>
      </div>
    </main>
  );
}
