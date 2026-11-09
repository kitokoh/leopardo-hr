import Link from 'next/link';
import { headers } from 'next/headers';
import { cache } from 'react';
import { normalizeLocale } from '@/lib/locale-core';
import { getPublicPortalCopy } from '../data/copy';
import { PublicPortalNav } from './PublicPortalNav';

/**
 * Chrome partagé du portail grand public (`/decouvrir`, `/boutiques`,
 * `/operateurs`, `/entreprises`, `/stay`, `/vitrine`, `/restaurants`).
 *
 * ## Pourquoi ce layout existe
 *
 * Avant, chaque surface publique rendait son propre `<main>` sans en-tête ni
 * pied de page : un visiteur arrivé sur `/boutiques/{slug}` depuis un moteur de
 * recherche n'avait AUCUN moyen de revenir au portail, de changer de langue ou
 * de découvrir les autres verticales. Les seuls liens de retour étaient des
 * boutons « Tous les professionnels » dispersés dans les fiches.
 *
 * ## Ce que ce layout NE fait pas
 *
 * - Il ne remplace pas le layout racine : `lang`/`dir`, la CSP, le bandeau de
 *   consentement et les JSON-LD d'organisation restent posés une seule fois
 *   par `src/app/layout.tsx`.
 * - Il ne pose pas de `<main>` : le layout racine en fournit déjà un
 *   (`<main id="main-content">`). Un second `<main>` imbriqué est invalide en
 *   HTML et casse la navigation par repères des lecteurs d'écran.
 * - Il n'ajoute aucun appel API : le chrome est purement statique.
 *
 * La locale est résolue côté serveur depuis `x-vitrine-lang` (posé par le
 * proxy pour `?lang=`) puis `Accept-Language` — même pattern que les layouts
 * `/library` et `/toolbox`, pour que le HTML servi soit déjà dans la bonne
 * langue (pas de FOUC ni de mismatch d'hydratation).
 */

const getSsrLocale = cache(async () => {
  const headerList = await headers();
  const urlLang = headerList.get('x-vitrine-lang');
  if (urlLang) return normalizeLocale(urlLang);
  const base = (headerList.get('accept-language') ?? '')
    .split(',')[0]
    .trim()
    .toLowerCase()
    .slice(0, 2);
  return normalizeLocale(base);
});

export default async function PublicPortalLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  const locale = await getSsrLocale();
  const copy = getPublicPortalCopy(locale);

  return (
    <div className="flex min-h-screen flex-col bg-slate-50">
      <PublicPortalNav locale={locale} copy={copy.chrome} />

      <div className="flex-1">{children}</div>

      <footer className="border-t border-slate-200 bg-white">
        <div className="mx-auto max-w-6xl px-4 py-10 sm:px-6">
          <div className="grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
            <div>
              <p className="text-base font-black tracking-tight text-slate-950">
                {copy.chrome.brand}
              </p>
              <p className="mt-2 max-w-xs text-sm text-slate-500">
                {copy.chrome.footerTagline}
              </p>
            </div>

            <nav aria-label={copy.chrome.footerDiscoverTitle}>
              <p className="text-xs font-bold uppercase tracking-wide text-slate-400">
                {copy.chrome.footerDiscoverTitle}
              </p>
              <ul className="mt-3 space-y-2 text-sm">
                <li>
                  <Link href="/decouvrir" className="text-slate-600 hover:text-emerald-700">
                    {copy.chrome.discover}
                  </Link>
                </li>
                <li>
                  <Link href="/restaurants" className="text-slate-600 hover:text-emerald-700">
                    {copy.chrome.restaurants}
                  </Link>
                </li>
                <li>
                  <Link href="/boutiques" className="text-slate-600 hover:text-emerald-700">
                    {copy.chrome.shops}
                  </Link>
                </li>
                <li>
                  <Link href="/operateurs" className="text-slate-600 hover:text-emerald-700">
                    {copy.chrome.operators}
                  </Link>
                </li>
                <li>
                  <Link href="/entreprises" className="text-slate-600 hover:text-emerald-700">
                    {copy.chrome.companies}
                  </Link>
                </li>
                <li>
                  <Link href="/stay" className="text-slate-600 hover:text-emerald-700">
                    {copy.chrome.stays}
                  </Link>
                </li>
              </ul>
            </nav>

            <nav aria-label={copy.chrome.footerBusinessTitle}>
              <p className="text-xs font-bold uppercase tracking-wide text-slate-400">
                {copy.chrome.footerBusinessTitle}
              </p>
              <ul className="mt-3 space-y-2 text-sm">
                <li>
                  <Link href="/" className="text-slate-600 hover:text-emerald-700">
                    {copy.chrome.footerAbout}
                  </Link>
                </li>
                <li>
                  <Link href="/pricing" className="text-slate-600 hover:text-emerald-700">
                    {copy.chrome.footerPricing}
                  </Link>
                </li>
                <li>
                  <Link href="/library" className="text-slate-600 hover:text-emerald-700">
                    {copy.chrome.library}
                  </Link>
                </li>
                <li>
                  <Link href="/toolbox" className="text-slate-600 hover:text-emerald-700">
                    {copy.chrome.toolbox}
                  </Link>
                </li>
                <li>
                  <Link href="/status" className="text-slate-600 hover:text-emerald-700">
                    {copy.chrome.footerStatus}
                  </Link>
                </li>
              </ul>
            </nav>

            <nav aria-label={copy.chrome.footerLegalTitle}>
              <p className="text-xs font-bold uppercase tracking-wide text-slate-400">
                {copy.chrome.footerLegalTitle}
              </p>
              <ul className="mt-3 space-y-2 text-sm">
                <li>
                  <Link href="/privacy" className="text-slate-600 hover:text-emerald-700">
                    {copy.chrome.footerPrivacy}
                  </Link>
                </li>
                <li>
                  <Link href="/terms" className="text-slate-600 hover:text-emerald-700">
                    {copy.chrome.footerTerms}
                  </Link>
                </li>
                <li>
                  <Link href="/mentions-legales" className="text-slate-600 hover:text-emerald-700">
                    {copy.chrome.footerLegalNotice}
                  </Link>
                </li>
                <li>
                  <Link href="/contact" className="text-slate-600 hover:text-emerald-700">
                    {copy.chrome.footerContact}
                  </Link>
                </li>
              </ul>
            </nav>
          </div>

          <div className="mt-10 flex flex-col gap-3 border-t border-slate-200 pt-6 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between">
            <p>
              © {new Date().getFullYear()} {copy.chrome.brand}. {copy.chrome.footerRights}
            </p>
            <p>
              {copy.chrome.footerProSpace}{' '}
              <Link href="/" className="font-semibold text-emerald-700 hover:underline">
                {copy.chrome.footerProSpaceCta}
              </Link>
            </p>
          </div>
        </div>
      </footer>
    </div>
  );
}
