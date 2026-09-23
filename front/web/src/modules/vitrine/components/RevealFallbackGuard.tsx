'use client';

import { useEffect } from 'react';

/**
 * RevealFallbackGuard — filet de sécurité pour les sections animées en
 * `initial={{ opacity: 0 }}` + `whileInView` (framer-motion) et consorts
 * (#8063).
 *
 * Constat (audit du 22/09/2026, prod) : des sections entières de la home
 * restaient invisibles au scroll — observer jamais déclenché, hydratation
 * interrompue, scroll rapide. L'animation d'apparition doit être une
 * bonification, jamais une condition d'affichage.
 *
 * Ce garde ne remplace pas les animations : il ne touche que les éléments
 * qui sont DANS le viewport depuis plus de `FALLBACK_MS` et pourtant
 * toujours quasi invisibles (opacité calculée < 0.5 via style inline posé
 * par la lib d'animation). Ceux-là sont révélés d'office. Si l'animation
 * fonctionne, le garde ne fait rien : l'élément est déjà visible quand le
 * timer passe.
 *
 * Ciblage volontairement étroit : uniquement les éléments sous `main` dont
 * le style INLINE contient `opacity` (signature des libs d'animation) —
 * les décors masqués par classes CSS (`hover:opacity-*`, gradients…) ne
 * sont jamais touchés.
 */
const FALLBACK_MS = 2000;
const CHECK_EVERY_MS = 1000;

function revealStuckElements(root: ParentNode) {
  const candidates = root.querySelectorAll<HTMLElement>('main [style*="opacity"]');
  candidates.forEach((el) => {
    if (el.dataset.revealGuardApplied) return;
    const computed = window.getComputedStyle(el);
    // < 0.3 : état « caché par une animation jamais déclenchée » (initial 0).
    // Les semi-transparences de design assumées (ex. briques non-focus à 0.4
    // dans SolutionStack) ne doivent JAMAIS être forcées.
    if (parseFloat(computed.opacity) >= 0.3) return;
    const rect = el.getBoundingClientRect();
    const inViewport =
      rect.top < window.innerHeight * 0.9 && rect.bottom > 0 && rect.width > 0;
    if (!inViewport) return;
    // Marquage temporel : on ne force qu'un élément resté masqué dans le
    // viewport sur DEUX passes consécutives (≥ FALLBACK_MS cumulés).
    if (!el.dataset.revealGuardSeen) {
      el.dataset.revealGuardSeen = String(Date.now());
      return;
    }
    if (Date.now() - Number(el.dataset.revealGuardSeen) < FALLBACK_MS - CHECK_EVERY_MS) {
      return;
    }
    el.dataset.revealGuardApplied = 'true';
    el.style.opacity = '1';
    el.style.transform = 'none';
  });
}

export function RevealFallbackGuard() {
  useEffect(() => {
    const tick = () => revealStuckElements(document);
    const interval = window.setInterval(tick, CHECK_EVERY_MS);
    // Première passe rapide pour amorcer le marquage temporel.
    const kickoff = window.setTimeout(tick, CHECK_EVERY_MS / 2);
    return () => {
      window.clearInterval(interval);
      window.clearTimeout(kickoff);
    };
  }, []);

  return (
    // Sans JavaScript, les styles inline `opacity:0` posés au SSR par la lib
    // d'animation ne seraient jamais levés : on neutralise au CSS.
    <noscript>
      <style>{`
        main [style*="opacity:0"], main [style*="opacity: 0"] {
          opacity: 1 !important;
          transform: none !important;
        }
      `}</style>
    </noscript>
  );
}
