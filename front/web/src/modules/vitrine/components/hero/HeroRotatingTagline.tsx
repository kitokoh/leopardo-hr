'use client';

/**
 * HeroRotatingTagline — le petit texte secondaire du héros, ROTATIF
 * (passe Fondateur, #8703).
 *
 * Demande du propriétaire : « à côté du texte principal, un petit texte
 * secondaire qui fait apparaître plusieurs textes secondaires dans le même
 * coin — en supprimant et mettant un autre texte, puis un autre ».
 *
 * Fonctionnement :
 *   • les phrases se succèdent AU MÊME ENDROIT — toutes occupent la même
 *     cellule de grille (`col-start-1 row-start-1`), donc la hauteur est
 *     celle de la plus longue : AUCUN saut de mise en page à la rotation ;
 *   • la transition est un fondu doux (opacité + glissement de quelques
 *     pixels à l'entrée), tempo lente : la phrase s'efface, la suivante
 *     prend sa place — jamais de clignotement ;
 *   • la rotation est portée par UN `setInterval` (une écriture de classe
 *     par cycle) — aucun recalcul par image, le thread principal reste libre.
 *
 * Accessibilité :
 *   • `prefers-reduced-motion` : aucune rotation, la première phrase reste
 *     affichée, statique — le contenu reste entièrement disponible ;
 *   • les phrases en rotation sont `aria-hidden` (décoratif rythmé) : les
 *     lecteurs d'écran reçoivent la première phrase, une seule fois, via un
 *     `sr-only` — pas d'annonces répétées toutes les 4 secondes.
 *
 * Les textes arrivent par `phrases` (i18n — `vitrine-locale.ts`) : AUCUN
 * littéral utilisateur dans ce fichier (garde i18n).
 */

import { useEffect, useState } from 'react';

export interface HeroRotatingTaglineProps {
  /** Phrases à faire tourner, dans l'ordre (≥ 2 pour que la rotation parte). */
  phrases: string[];
  /** Tempo de rotation (ms) — lente par défaut : élégant, jamais agressif. */
  intervalMs?: number;
}

const REDUCED_MOTION_QUERY = '(prefers-reduced-motion: reduce)';

export function HeroRotatingTagline({ phrases, intervalMs = 3800 }: HeroRotatingTaglineProps) {
  const [active, setActive] = useState(0);

  useEffect(() => {
    if (phrases.length < 2) return undefined;
    // Mouvement réduit : on ne branche même pas la rotation — la première
    // phrase reste affichée, statique (jsdom sans matchMedia : même repli).
    if (typeof window.matchMedia !== 'function') return undefined;
    if (window.matchMedia(REDUCED_MOTION_QUERY).matches) return undefined;
    const timer = window.setInterval(() => {
      setActive((current) => (current + 1) % phrases.length);
    }, intervalMs);
    return () => window.clearInterval(timer);
  }, [phrases.length, intervalMs]);

  if (phrases.length === 0) return null;

  return (
    <div
      data-testid="hero-rotating-tagline"
      className="relative mt-3 sm:mt-4 flex justify-center"
    >
      <div className="relative inline-grid place-items-center rounded-full border border-amber-400/25 bg-slate-900/70 px-5 py-2 text-center shadow-lg shadow-amber-950/30 backdrop-blur-xl ring-1 ring-white/10">
        {/* Version lecteur d'écran : la première phrase, une fois. La rotation
            visuelle est marquée aria-hidden ci-dessous. */}
        <span className="sr-only">{phrases[0]}</span>
        {phrases.map((phrase, index) => (
          <span
            key={phrase}
            aria-hidden="true"
            className={`col-start-1 row-start-1 flex items-center justify-center gap-2.5 text-xs font-semibold tracking-wide text-amber-200/90 transition-all duration-700 ease-out sm:text-sm ${
              index === active
                ? 'translate-y-0 opacity-100'
                : 'pointer-events-none translate-y-1.5 opacity-0'
            }`}
          >
            {/* Point-étoile : le texte secondaire appartient à la même nuit que
                la constellation — une pulsation douce, comme une étoile de plus. */}
            <span className="inline-block h-2 w-2 shrink-0 animate-pulse rounded-full bg-amber-400 shadow-[0_0_10px_rgba(251,191,36,0.85)]" />
            <span>{phrase}</span>
          </span>
        ))}
      </div>
    </div>
  );
}

export default HeroRotatingTagline;
