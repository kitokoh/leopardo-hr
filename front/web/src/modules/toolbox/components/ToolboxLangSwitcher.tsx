'use client';

import React from 'react';
import { usePathname, useSearchParams } from 'next/navigation';
import { Languages } from 'lucide-react';
import { TOOLBOX_LOCALES, type ToolboxLocale } from '../data/copy';
import { localeSwitchHref } from '../lib/locale-href';

interface ToolboxLangSwitcherProps {
  /** Locale réellement servie par le SSR (résolue depuis `x-vitrine-lang`). */
  locale: ToolboxLocale;
  /** Libellé accessible du contrôle (« Langue », « Language », …). */
  label: string;
  /** Noms natifs des langues, déjà localisés dans `data/copy.ts`. */
  names: Record<ToolboxLocale, string>;
  /**
   * Navigation — injectable pour les tests.
   *
   * Par défaut `window.location.assign` : la bascule doit provoquer un rendu
   * SERVEUR dans la nouvelle langue, donc une navigation complète (un
   * `router.push` ne rejouerait pas le proxy qui pose `x-vitrine-lang`).
   * jsdom n'expose pas de `location` redéfinissable, d'où cette couture.
   */
  navigate?: (href: string) => void;
}

/**
 * Sélecteur de langue de la Toolbox.
 *
 * ## Pourquoi il existe
 *
 * Le module sert 4 locales (fr/en/tr/ar, arabe en RTL) et le SSR les rend
 * correctement depuis `?lang=` / `Accept-Language`. Mais **aucun contrôle** ne
 * permettait de changer de langue : un visiteur dont le navigateur est en
 * français ne pouvait jamais lire la Toolbox en turc, alors que la traduction
 * existait déjà. Le portail public (`PublicPortalNav`) et la vitrine ont ce
 * contrôle ; la Toolbox, non.
 *
 * ## Comportement
 *
 * - `<select>` natif : clavier, lecteur d'écran et menu système gratuits.
 * - La bascule **conserve** le reste de l'URL (filtres du catalogue, `s=` d'un
 *   calcul partagé) et ne remplace que `lang` — voir `localeSwitchHref`.
 * - `defaultValue` (et non `value`) : la valeur affichée suit le SSR, et le
 *   changement déclenche une navigation complète, donc un nouveau rendu serveur
 *   dans la bonne langue. Aucun état client à resynchroniser.
 */
export function ToolboxLangSwitcher({
  locale,
  label,
  names,
  navigate,
}: ToolboxLangSwitcherProps) {
  const pathname = usePathname();
  const searchParams = useSearchParams();

  const go = navigate ?? ((href: string) => window.location.assign(href));

  return (
    <div className="inline-flex items-center gap-1.5">
      <Languages className="h-4 w-4 text-slate-400" aria-hidden />
      <label className="sr-only" htmlFor="toolbox-lang">
        {label}
      </label>
      <select
        id="toolbox-lang"
        defaultValue={locale}
        onChange={(event) => {
          const next = event.target.value as ToolboxLocale;
          go(localeSwitchHref(pathname, searchParams.toString(), next));
        }}
        className="rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-sm font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
      >
        {TOOLBOX_LOCALES.map((value) => (
          <option key={value} value={value}>
            {names[value]}
          </option>
        ))}
      </select>
    </div>
  );
}
