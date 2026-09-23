'use client';

import { useEffect } from 'react';
import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

gsap.registerPlugin(ScrollTrigger);

/**
 * Reveal au scroll — version « progressive enhancement » (#8063).
 *
 * Règles (l'animation est une bonification, JAMAIS une condition
 * d'affichage) :
 *  - `prefers-reduced-motion: reduce` → aucun état caché, aucun tween ;
 *  - un élément révélé ne se re-cache jamais (pas de reverse) ;
 *  - les positions des triggers sont recalculées quand le layout bouge
 *    (dynamic imports, images, fonts) via un ResizeObserver sur <body> ;
 *  - filet de sécurité : si un élément est dans le viewport et toujours
 *    masqué après `FALLBACK_MS` (observer jamais déclenché, hydratation
 *    lente, scroll très rapide), il est révélé d'office.
 */
const FALLBACK_MS = 2000;

export function useScrollReveal() {
  useEffect(() => {
    // Animations réduites : le contenu reste tel quel, visible.
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      return undefined;
    }

    const tweens: gsap.core.Tween[] = [];

    const ctx = gsap.context(() => {
      gsap.utils.toArray<HTMLElement>('.gsap-reveal').forEach((elem) => {
        tweens.push(
          gsap.fromTo(
            elem,
            { opacity: 0, y: 80, scale: 0.97 },
            {
              opacity: 1,
              y: 0,
              scale: 1,
              duration: 1.2,
              ease: 'power4.out',
              scrollTrigger: {
                trigger: elem,
                start: 'top 88%',
                // Une fois révélé, on ne re-cache jamais (#8063).
                toggleActions: 'play none none none',
                once: true,
              },
            }
          )
        );
      });

      gsap.utils.toArray<HTMLElement>('.gsap-stagger').forEach((container) => {
        const children = container.querySelectorAll('.gsap-stagger-item');
        tweens.push(
          gsap.fromTo(
            children,
            { opacity: 0, y: 40 },
            {
              opacity: 1,
              y: 0,
              duration: 0.8,
              stagger: 0.12,
              ease: 'power3.out',
              scrollTrigger: {
                trigger: container,
                start: 'top 85%',
                toggleActions: 'play none none none',
                once: true,
              },
            }
          )
        );
      });
    });

    // Le layout de la home bouge après le mount (dynamic imports ssr:false,
    // images, fonts) : sans refresh, les positions de départ des triggers
    // sont fausses et des sections restent masquées (#8063).
    let refreshTimer: ReturnType<typeof setTimeout> | undefined;
    const resizeObserver =
      typeof ResizeObserver !== 'undefined'
        ? new ResizeObserver(() => {
            clearTimeout(refreshTimer);
            refreshTimer = setTimeout(() => ScrollTrigger.refresh(), 200);
          })
        : undefined;
    resizeObserver?.observe(document.body);

    // Filet de sécurité : tout élément encore masqué alors qu'il est dans le
    // viewport après FALLBACK_MS est révélé d'office (l'animation n'est pas
    // une condition d'affichage).
    const forceReveal = () => {
      tweens.forEach((tween) => {
        const st = tween.scrollTrigger;
        if (!st || tween.progress() > 0) return;
        const rect = (st.trigger as HTMLElement | null)?.getBoundingClientRect();
        if (!rect) return;
        const inViewport = rect.top < window.innerHeight * 0.95 && rect.bottom > 0;
        if (inViewport) tween.play();
      });
    };
    const fallbackTimer = window.setInterval(forceReveal, FALLBACK_MS);

    return () => {
      window.clearInterval(fallbackTimer);
      clearTimeout(refreshTimer);
      resizeObserver?.disconnect();
      ctx.revert();
    };
  }, []);
}
