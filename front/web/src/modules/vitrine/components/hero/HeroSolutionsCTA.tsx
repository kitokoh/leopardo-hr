'use client';

/**
 * HeroSolutionsCTA — l'UNIQUE appel à l'action placé SOUS la constellation.
 *
 * Retour utilisateur : « il y en a deux, n'en garder qu'UN SEUL, et le placer
 * un peu plus bas, sous la constellation ». Le second bouton (onboarding) et
 * le bloc de texte au-dessus ont donc disparu : un seul bouton, un seul
 * parcours, aucune concurrence avec la constellation. Les textes retirés
 * restent portés par la page /packs.
 *
 * Placement : le parent (HeroScene3DPanel) place ce bloc SOUS la scène ; la
 * marge haute le décolle franchement de la constellation (« un peu plus bas »,
 * sans le perdre en bas de page) et la marge basse le détache du bord.
 *
 * Libellé : `SOLUTION_CARD_COPY.ctaPrimary` (copie sous `data/`, chemin
 * exempté par la garde PA2-I18N-014). Destination : le hub /packs, parcours de
 * conversion existant ; `?lang=` conservé via `withLocaleHref`.
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
      className="pointer-events-auto flex flex-col items-center gap-3 pb-6 sm:pb-8"
    >
      <Link
        href={withLocaleHref('/packs', search)}
        className="group cta-glow-emerald relative inline-flex min-h-14 items-center gap-3 overflow-hidden rounded-2xl border border-emerald-400/25 bg-gradient-to-r from-emerald-600 to-teal-500 px-8 py-4 text-base font-bold text-white shadow-xl shadow-emerald-950/50 ring-1 ring-inset ring-white/10 transition-all duration-300 hover:scale-[1.02] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-300 active:scale-[0.98] sm:px-9"
      >
        <span className="relative z-10 flex items-center gap-2.5">
          {copy.ctaPrimary}
          <ArrowRight
            className="h-5 w-5 transition-transform duration-300 group-hover:translate-x-1"
            aria-hidden="true"
          />
        </span>
        <span className="absolute inset-0 bg-gradient-to-r from-emerald-400 to-cyan-400 opacity-0 transition-opacity duration-500 group-hover:opacity-100" />
      </Link>
    </div>
  );
}

export default HeroSolutionsCTA;
