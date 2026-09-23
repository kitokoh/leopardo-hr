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
 * - `prefers-reduced-motion: reduce` → aucun tween n'est créé ;
 * - triggers re-mesurés au chargement complet (images/polices) : mesurés trop
 *   tôt, ils pouvaient ne jamais se déclencher (layout shift).
 *
 * Filet de sécurité (complément #8063) : framer-motion rend son état
 * `initial={{ opacity: 0 }}` en style inline dans le HTML SSR (~62 éléments
 * sur la home) — si l'hydratation est lente ou cassée, ces contenus restent
 * invisibles. Un sondage initial révèle de force tout élément encore masqué
 * alors qu'il est dans le viewport. Si framer-motion est vivant, il reprend
 * la main au prochain rendu ; sinon le contenu reste visible.
 */

/** Fréquence du sondage de sécurité et fenêtre totale de surveillance. */
const SAFETY_POLL_MS = 500;
const SAFETY_WINDOW_MS = 30_000;

function isInViewport(el: HTMLElement): boolean {
  const rect = el.getBoundingClientRect();
  return rect.height > 0 && rect.bottom > 0 && rect.top < window.innerHeight;
}

/**
 * Révèle le contenu SSR encore masqué (`opacity:0` + `transform`/`filter`
 * inline, signature d'un reveal JS) qui n'a jamais été animé alors qu'il est
 * visible à l'écran.
 */
function revealStuckSsrContent(): void {
  const candidates = document.querySelectorAll<HTMLElement>(
    'main [style*="opacity:0"], main [style*="opacity: 0"]',
  );
  candidates.forEach((el) => {
    const inline = el.getAttribute('style') ?? '';
    // Signature d'un reveal JS (pas un masquage métier type dropdown fermé :
    // ceux-ci ne cumulent pas opacity:0 + transform/filter + présence à
    // l'écran en continu).
    if (!/(transform|filter)/.test(inline)) return;
    if (getComputedStyle(el).opacity !== '0') return;
    if (!isInViewport(el)) return;
    el.style.setProperty('opacity', '1', 'important');
    el.style.removeProperty('transform');
    el.style.removeProperty('filter');
  });
}

export function useScrollReveal() {
  useEffect(() => {
    const reducedMotion =
      typeof window.matchMedia === 'function' &&
      window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    let ctx: gsap.Context | null = null;
    const handleLoad = (): void => ScrollTrigger.refresh();

    if (!reducedMotion) {
      ctx = gsap.context(() => {
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

      // Positions mesurées avant le chargement des images/polices = fausses
      // (layout shift) → triggers jamais tirés. On re-mesure au load complet
      // et une fois les polices prêtes.
      window.addEventListener('load', handleLoad);
      document.fonts?.ready.then(() => ScrollTrigger.refresh()).catch(() => undefined);
    }

    // Filet de sécurité : actif même en reduced-motion (framer-motion anime
    // l'opacité quoi qu'il arrive — le filet garantit l'affichage final).
    const startedAt = Date.now();
    const poll = window.setInterval(() => {
      revealStuckSsrContent();
      if (Date.now() - startedAt > SAFETY_WINDOW_MS) {
        window.clearInterval(poll);
      }
    }, SAFETY_POLL_MS);

    return () => {
      window.clearInterval(poll);
      window.removeEventListener('load', handleLoad);
      ctx?.revert();
    };
  }, []);
}
