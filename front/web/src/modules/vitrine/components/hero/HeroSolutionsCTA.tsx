'use client';

/**
 * HeroSolutionsCTA — l'appel à l'action principal placé SOUS la constellation.
 *
 * Mission Leopardo — objectif : convertir sans détourner l'attention de la
 * constellation. Le CTA vit dans le flux du héros, sous le canvas (z-20),
 * centré : hiérarchie visuelle claire, espacement maîtrisé (le bloc parent
 * porte `pb-24`, ni collé au globe ni perdu en bas de page).
 *
 * Libellés : `SOLUTION_CARD_COPY` (copie nouvelle, sous `data/` — chemin
 * exempté par la garde PA2-I18N-014). Destination : le hub /packs (parcours de
 * conversion existant) et /signup ; `?lang=` conservé via `withLocaleHref`,
 * comme partout sur la vitrine.
 */

import Link from 'next/link';
import { useSearchParams } from 'next/navigation';
import { ArrowRight } from 'lucide-react';
import { withLocaleHref } from '../../lib/locale-href';
import { useVitrineLocale } from '../../lib/vitrine-locale';
import { SOLUTION_CARD_COPY } from '../../data/solution-catalog';

export function HeroSolutionsCTA() {
  const { locale } = useVitrineLocale();
  const copy = SOLUTION_CARD_COPY[locale] ?? SOLUTION_CARD_COPY.fr;
  const search = useSearchParams().toString();

  return (
    <div
      data-testid="hero-solutions-cta"
      className="pointer-events-auto flex w-full max-w-3xl flex-col items-center gap-3"
    >
      <p className="text-balance text-center text-lg font-bold text-white sm:text-xl">
        {copy.ctaTitle}
      </p>
      <p className="max-w-xl text-balance text-center text-sm text-slate-300">{copy.ctaSubtitle}</p>

      <div className="mt-2 flex flex-col items-center gap-3 sm:flex-row">
        <Link
          href={withLocaleHref('/packs', search)}
          className="group relative inline-flex items-center gap-2.5 overflow-hidden rounded-2xl bg-gradient-to-r from-emerald-700 to-emerald-800 px-8 py-4 text-base font-bold text-white transition-all duration-300 hover:scale-[1.03] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-400 active:scale-[0.98]"
        >
          <span className="relative z-10 flex items-center gap-2.5">
            {copy.ctaPrimary}
            <ArrowRight
              className="h-5 w-5 transition-transform duration-300 group-hover:translate-x-1"
              aria-hidden="true"
            />
          </span>
          <span className="absolute inset-0 bg-gradient-to-r from-emerald-600 to-cyan-600 opacity-0 transition-opacity duration-500 group-hover:opacity-100" />
        </Link>

        <Link
          href={withLocaleHref('/signup', search)}
          prefetch={false}
          className="inline-flex items-center gap-2 rounded-2xl border border-slate-600/60 bg-white/5 px-8 py-4 text-base font-semibold text-white backdrop-blur transition-colors hover:border-emerald-500/60 hover:bg-white/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-400"
        >
          {copy.ctaSecondary}
        </Link>
      </div>

      <p className="text-xs font-medium text-slate-400">{copy.ctaReassurance}</p>
    </div>
  );
}

export default HeroSolutionsCTA;
