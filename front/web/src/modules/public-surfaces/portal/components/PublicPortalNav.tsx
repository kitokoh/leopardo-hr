'use client';

import { useState } from 'react';
import Link from 'next/link';
import { usePathname, useSearchParams } from 'next/navigation';
import { ArrowUpRight, Menu, X } from 'lucide-react';
import type { PublicPortalCopy } from '../data/copy';
import { getPublicHomeCopy } from '../data/home-copy';
import { ThemeToggle } from './ThemeToggle';

/**
 * Barre de navigation du portail grand public.
 *
 * ## Accessibilité
 *
 * - Le bouton de menu porte `aria-expanded` / `aria-controls` et un libellé
 *   explicite (ouvrir/fermer) : un lecteur d'écran annonce l'état réel.
 * - `aria-current="page"` marque l'entrée active (repère de navigation).
 * - Le menu mobile est un `<nav>` distinct, rendu seulement quand il est
 *   ouvert : pas de liens dupliqués dans l'ordre de tabulation.
 * - Chaque lien est une cible d'au moins 44 px de haut sur mobile.
 *
 * ## Langue
 *
 * Le portail suit la convention vitrine `?lang=` (aucune route préfixée) : le
 * sélecteur réécrit le paramètre sur l'URL courante, ce que le proxy
 * normalise en `x-vitrine-lang` pour le SSR.
 *
 * ## « Leopardo Business » (#9357)
 *
 * La vitrine commerciale vit sous `/business` depuis #9358. Le bouton est
 * présent dans l'en-tête ET dans le hero de la page d'accueil : c'est le seul
 * chemin vers l'offre professionnelle, et il doit être trouvable sans faire
 * défiler la page.
 */

interface PublicPortalNavProps {
  locale: string;
  copy: PublicPortalCopy['chrome'];
}

const LOCALE_VALUES = ['fr', 'en', 'tr', 'ar'] as const;

export function PublicPortalNav({ locale, copy }: PublicPortalNavProps) {
  const pathname = usePathname();
  const searchParams = useSearchParams();
  const [open, setOpen] = useState(false);
  const homeCopy = getPublicHomeCopy(locale);

  const entries = [
    { href: '/decouvrir', label: copy.discover },
    { href: '/restaurants', label: copy.restaurants },
    { href: '/boutiques', label: copy.shops },
    { href: '/operateurs', label: copy.operators },
    { href: '/entreprises', label: copy.companies },
    { href: '/stay', label: copy.stays },
  ];

  /** Conserve les filtres courants et remplace seulement `lang`. */
  const localeHref = (value: string): string => {
    const params = new URLSearchParams(searchParams.toString());
    if (value === 'fr') {
      params.delete('lang');
    } else {
      params.set('lang', value);
    }
    const qs = params.toString();
    return qs ? `${pathname}?${qs}` : pathname;
  };

  const isActive = (href: string): boolean =>
    pathname === href || pathname.startsWith(`${href}/`);

  return (
    <header className="sticky top-0 z-40 border-b border-slate-200 bg-white/85 backdrop-blur-xl dark:border-white/10 dark:bg-slate-950/80">
      <div className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3 sm:px-6">
        <Link href="/" className="flex items-baseline gap-2">
          <span className="text-lg font-black tracking-tight text-slate-950 dark:text-white">
            {copy.brand}
          </span>
          <span className="hidden text-xs font-semibold uppercase tracking-wide text-emerald-700 sm:inline dark:text-emerald-400">
            {copy.brandTagline}
          </span>
        </Link>

        <nav aria-label={copy.menu} className="hidden items-center gap-1 lg:flex">
          {entries.map((entry) => (
            <Link
              key={entry.href}
              href={entry.href}
              aria-current={isActive(entry.href) ? 'page' : undefined}
              className={`rounded-lg px-3 py-2 text-sm font-semibold transition-colors ${
                isActive(entry.href)
                  ? 'bg-emerald-50 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300'
                  : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-white/10 dark:hover:text-white'
              }`}
            >
              {entry.label}
            </Link>
          ))}
        </nav>

        <div className="flex items-center gap-2">
          <div className="hidden sm:block">
            <ThemeToggle copy={homeCopy.theme} />
          </div>

          <label className="sr-only" htmlFor="public-portal-lang">
            {copy.language}
          </label>
          <select
            id="public-portal-lang"
            defaultValue={locale}
            onChange={(event) => {
              window.location.assign(localeHref(event.target.value));
            }}
            className="rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-sm font-medium text-slate-700 dark:border-white/15 dark:bg-white/5 dark:text-slate-200"
          >
            {LOCALE_VALUES.map((value) => (
              <option key={value} value={value}>
                {copy.languageNames[value]}
              </option>
            ))}
          </select>

          {/* Entrée vers la vitrine business — toujours visible, y compris
              sur mobile : c'est le seul chemin vers l'offre professionnelle. */}
          <Link
            href="/business"
            className="hidden items-center gap-1 rounded-lg bg-slate-900 px-3 py-2 text-sm font-bold text-white transition-colors hover:bg-slate-800 sm:inline-flex dark:bg-white dark:text-slate-900 dark:hover:bg-slate-200"
          >
            {homeCopy.hero.businessCta}
            <ArrowUpRight className="h-3.5 w-3.5" aria-hidden />
          </Link>

          <button
            type="button"
            onClick={() => setOpen((value) => !value)}
            aria-expanded={open}
            aria-controls="public-portal-mobile-nav"
            aria-label={open ? copy.closeMenu : copy.openMenu}
            className="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-slate-300 text-slate-700 lg:hidden dark:border-white/15 dark:text-slate-200"
          >
            {open ? <X className="h-5 w-5" aria-hidden /> : <Menu className="h-5 w-5" aria-hidden />}
          </button>
        </div>
      </div>

      {open && (
        <nav
          id="public-portal-mobile-nav"
          aria-label={copy.menu}
          className="border-t border-slate-200 bg-white px-4 pb-4 pt-2 lg:hidden dark:border-white/10 dark:bg-slate-950"
        >
          <ul className="flex flex-col">
            {entries.map((entry) => (
              <li key={entry.href}>
                <Link
                  href={entry.href}
                  onClick={() => setOpen(false)}
                  aria-current={isActive(entry.href) ? 'page' : undefined}
                  className={`flex min-h-[44px] items-center rounded-lg px-3 text-sm font-semibold ${
                    isActive(entry.href)
                      ? 'bg-emerald-50 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300'
                      : 'text-slate-700 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-white/10'
                  }`}
                >
                  {entry.label}
                </Link>
              </li>
            ))}
            <li className="mt-2 border-t border-slate-200 pt-2 dark:border-white/10">
              <Link
                href="/business"
                onClick={() => setOpen(false)}
                className="flex min-h-[44px] items-center justify-between rounded-lg bg-slate-900 px-3 text-sm font-bold text-white dark:bg-white dark:text-slate-900"
              >
                {homeCopy.hero.businessCta}
                <ArrowUpRight className="h-4 w-4" aria-hidden />
              </Link>
            </li>
            <li className="mt-3 flex items-center justify-between px-3">
              <span className="text-xs font-semibold uppercase tracking-wide text-slate-400">
                {homeCopy.theme.label}
              </span>
              <ThemeToggle copy={homeCopy.theme} />
            </li>
          </ul>
        </nav>
      )}
    </header>
  );
}
