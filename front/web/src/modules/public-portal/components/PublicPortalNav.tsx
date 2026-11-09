'use client';

import { useState } from 'react';
import Link from 'next/link';
import { usePathname, useSearchParams } from 'next/navigation';
import { Menu, X } from 'lucide-react';
import type { PublicPortalCopy } from '../data/copy';

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
 * Le portail suit la convention vitrine `?lang=` (aucune route préfixée) :
 * le sélecteur réécrit le paramètre sur l'URL courante, ce que le proxy
 * normalise en `x-vitrine-lang` pour le SSR.
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
    <header className="sticky top-0 z-40 border-b border-slate-200 bg-white/90 backdrop-blur">
      <div className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3 sm:px-6">
        <Link href="/decouvrir" className="flex items-baseline gap-2">
          <span className="text-lg font-black tracking-tight text-slate-950">{copy.brand}</span>
          <span className="hidden text-xs font-semibold uppercase tracking-wide text-emerald-700 sm:inline">
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
                  ? 'bg-emerald-50 text-emerald-800'
                  : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
              }`}
            >
              {entry.label}
            </Link>
          ))}
        </nav>

        <div className="flex items-center gap-2">
          <label className="sr-only" htmlFor="public-portal-lang">
            {copy.language}
          </label>
          <select
            id="public-portal-lang"
            defaultValue={locale}
            onChange={(event) => {
              window.location.assign(localeHref(event.target.value));
            }}
            className="rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-sm font-medium text-slate-700"
          >
            {LOCALE_VALUES.map((value) => (
              <option key={value} value={value}>
                {copy.languageNames[value]}
              </option>
            ))}
          </select>

          <button
            type="button"
            onClick={() => setOpen((value) => !value)}
            aria-expanded={open}
            aria-controls="public-portal-mobile-nav"
            aria-label={open ? copy.closeMenu : copy.openMenu}
            className="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-slate-300 text-slate-700 lg:hidden"
          >
            {open ? <X className="h-5 w-5" aria-hidden /> : <Menu className="h-5 w-5" aria-hidden />}
          </button>
        </div>
      </div>

      {open && (
        <nav
          id="public-portal-mobile-nav"
          aria-label={copy.menu}
          className="border-t border-slate-200 bg-white px-4 pb-4 pt-2 lg:hidden"
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
                      ? 'bg-emerald-50 text-emerald-800'
                      : 'text-slate-700 hover:bg-slate-100'
                  }`}
                >
                  {entry.label}
                </Link>
              </li>
            ))}
          </ul>
        </nav>
      )}
    </header>
  );
}
