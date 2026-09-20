'use client';

/**
 * SolutionStackSection — la « Pile Leopardo » en section autonome. (#7851)
 *
 * La pile 3D quittait le héro (remplacée par `LeoHeroVisual`) sans rien perdre
 * de son intérêt : elle rend lisible l'architecture de l'offre. Elle vit ici,
 * après la section Solution, avec un **montage différé à l'approche du
 * viewport** : tant que le visiteur n'a pas scrollé à proximité, ni le
 * composant ni le chunk three.js (déjà différé à l'idle par `SolutionStack`)
 * ne concurrencent le LCP du héro. Un conteneur à hauteur réservée évite tout
 * décalage de mise en page (CLS).
 */

import { useEffect, useRef, useState } from 'react';
import type { AppLocale } from '@/lib/i18n';
import { SolutionStack } from './SolutionStack';

export interface SolutionStackSectionProps {
  locale: AppLocale;
}

export function SolutionStackSection({ locale }: SolutionStackSectionProps) {
  const anchorRef = useRef<HTMLDivElement>(null);
  const [inView, setInView] = useState(false);

  useEffect(() => {
    const node = anchorRef.current;
    if (!node) return;

    // Pas d'IntersectionObserver (très vieux navigateurs) → montage direct.
    if (typeof IntersectionObserver === 'undefined') {
      setInView(true);
      return;
    }

    const observer = new IntersectionObserver(
      (entries) => {
        if (entries.some((entry) => entry.isIntersecting)) {
          setInView(true);
          observer.disconnect();
        }
      },
      // Pré-monte un écran avant l'arrivée : la pile est prête au scroll.
      { rootMargin: '100% 0px' },
    );

    observer.observe(node);
    return () => observer.disconnect();
  }, []);

  return (
    <section
      ref={anchorRef}
      className="bg-slate-50/60 px-4 py-16 sm:px-6 sm:py-20 dark:bg-slate-900/40"
    >
      <div className="mx-auto w-full max-w-6xl">
        {/* Hauteur réservée ≈ header + canvas mobile pour un CLS nul. */}
        {inView ? (
          <SolutionStack locale={locale} />
        ) : (
          <div aria-hidden="true" className="h-[480px] sm:h-[560px] lg:h-[600px]" />
        )}
      </div>
    </section>
  );
}

export default SolutionStackSection;
