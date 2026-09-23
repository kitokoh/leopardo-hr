'use client';

import { useEffect } from 'react';
import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

gsap.registerPlugin(ScrollTrigger);

/**
 * Révélation au scroll des éléments `.gsap-reveal` / `.gsap-stagger`.
 *
 * #8063 : l'animation est une **bonification**, jamais une condition
 * d'affichage — le contenu est visible par défaut (l'HTML/CSS ne porte aucun
 * `opacity: 0`) et seul le **transform** est animé. Si ScrollTrigger ne se
 * déclenche pas (JS lent, scroll rapide, observer capricieux), l'élément
 * reste au pire légèrement décalé mais toujours lisible.
 *
 * - pas d'animation d'opacité (aucun état invisible possible) ;
 * - `once: true` et pas de `reverse` : on ne re-cache jamais un contenu déjà
 *   révélé (l'ancien `toggleActions: 'play none none reverse'` re-masquait
 *   les sections au scroll remontant) ;
 * - `prefers-reduced-motion: reduce` → aucun tween n'est créé.
 */
export function useScrollReveal() {
  useEffect(() => {
    if (
      typeof window.matchMedia === 'function' &&
      window.matchMedia('(prefers-reduced-motion: reduce)').matches
    ) {
      return;
    }

    const ctx = gsap.context(() => {
      gsap.utils.toArray<HTMLElement>('.gsap-reveal').forEach((elem) => {
        gsap.from(elem, {
          y: 80,
          scale: 0.97,
          duration: 1.2,
          ease: 'power4.out',
          scrollTrigger: {
            trigger: elem,
            start: 'top 88%',
            once: true,
          },
        });
      });

      gsap.utils.toArray<HTMLElement>('.gsap-stagger').forEach((container) => {
        const children = container.querySelectorAll('.gsap-stagger-item');
        gsap.from(children, {
          y: 40,
          duration: 0.8,
          stagger: 0.12,
          ease: 'power3.out',
          scrollTrigger: {
            trigger: container,
            start: 'top 85%',
            once: true,
          },
        });
      });
    });

    return () => ctx.revert();
  }, []);
}
