'use client';

/**
 * LeoHeroVisual — Leo, la pieuvre mascotte, en visuel d'entrée du héro. 🐙
 *
 * Objectif produit (#7851) : le visiteur doit comprendre en deux secondes ce
 * que fait Leopardo. Leo multitâche porte la promesse : un cerveau central
 * (la plateforme) et huit tentacules qui opèrent chacun une fonction métier —
 * paie, pointage, statistiques, restaurant, agence de voyage, station-service,
 * école, mobile. La pile 3D `SolutionStack` (architecture de l'offre) n'a pas
 * disparu : elle vit désormais dans sa propre section plus bas sur la page
 * (`SolutionStackSection`), où son interactivité a le temps d'exister.
 *
 * Contrairement à `LeoMascot` (décorative, aria-hidden), ce visuel est
 * porteur d'information : l'`alt` est localisé dans les 4 locales (P03 §4).
 * L'image est le candidat LCP du héro → `priority`. Palette strictement
 * produit : emerald #10B981 + cyan #22d3ee (docs/REFERENTIEL_PRODUIT/COULEURS.md).
 * Visible sur tous les points de rupture — mobile compris (leçon de l'ancien
 * placement `hidden sm:block`).
 */

import Image from 'next/image';
import type { AppLocale } from '@/lib/i18n';

export interface LeoHeroVisualProps {
  locale: AppLocale;
}

/** Alt localisé — l'image porte la promesse produit, elle n'est pas décorative. */
const ALT_TEXT: Record<AppLocale, string> = {
  fr: 'Leo, la pieuvre Leopardo : chaque tentacule opère une fonction métier — paie, pointage, statistiques, restaurant, voyage, station-service, école et mobile.',
  en: 'Leo, the Leopardo octopus: each tentacle runs a business function — payroll, attendance, analytics, restaurant, travel, fuel station, school and mobile.',
  tr: 'Leopardo ahtapotu Leo: her bir kolu bir işletme işlevini yürütür — bordro, mesai takibi, analiz, restoran, seyahat, akaryakıt istasyonu, okul ve mobil.',
  ar: 'ليو، أخطبوط Leopardo: كل ذراع يدير وظيفة عمل — الرواتب، الحضور، التحليلات، المطاعم، السفر، محطة الوقود، المدرسة والجوال.',
};

export function LeoHeroVisual({ locale }: LeoHeroVisualProps) {
  const alt = ALT_TEXT[locale] ?? ALT_TEXT.fr;

  return (
    <div className="relative mx-auto flex w-full max-w-md items-center justify-center px-4 sm:max-w-lg lg:max-w-xl">
      {/* Halo de marque derrière Leo — tokens produit uniquement (P05). */}
      <div
        aria-hidden="true"
        className="absolute inset-0 -z-10 flex items-center justify-center"
      >
        <div className="h-3/4 w-3/4 rounded-full bg-gradient-to-br from-emerald-400/25 via-cyan-400/15 to-transparent blur-3xl dark:from-emerald-500/20 dark:via-cyan-500/10" />
      </div>

      <Image
        src="/brand/leo-hero-suite.webp"
        alt={alt}
        width={1024}
        height={1024}
        priority
        sizes="(max-width: 640px) 88vw, (max-width: 1024px) 60vw, 540px"
        className="leo-mascot-float pointer-events-none h-auto w-full select-none drop-shadow-[0_24px_48px_rgba(16,185,129,0.18)]"
      />
    </div>
  );
}

export default LeoHeroVisual;
