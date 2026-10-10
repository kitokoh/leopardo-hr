'use client';

/**
 * HeroScene3D — fond animé du héro de la vitrine.
 *
 * Sert la « constellation Business OS » (`HeroBusinessOS3D`, WebGL) quand le
 * navigateur peut l'afficher, et le champ de particules 2D (`ParticleField`)
 * sinon — même rôle décoratif, deux niveaux d'exigence.
 *
 * Le chunk three.js (~130 Ko gzip) n'est chargé qu'après hydratation, au
 * premier temps mort, si WebGL est réellement disponible, si l'utilisateur
 * n'a pas demandé à réduire les animations et n'est pas en économie de
 * données : il ne concurrence ni le LCP ni l'hydratation.
 */

import dynamic from 'next/dynamic';
import { useEffect, useState } from 'react';
import { ParticleField } from '../ParticleField';

const HeroBusinessOS3D = dynamic(
  () => import('./HeroBusinessOS3D').then((module) => module.HeroBusinessOS3D),
  { ssr: false },
);

const REDUCED_MOTION_QUERY = '(prefers-reduced-motion: reduce)';

/** WebGL réellement utilisable ? (testé une seule fois, puis mémorisé.) */
let webglSupport: boolean | null = null;
function hasWebGL(): boolean {
  if (webglSupport !== null) return webglSupport;
  try {
    const canvas = document.createElement('canvas');
    webglSupport = Boolean(
      canvas.getContext('webgl2') ??
        canvas.getContext('webgl') ??
        canvas.getContext('experimental-webgl'),
    );
  } catch {
    webglSupport = false;
  }
  return webglSupport;
}

export function HeroScene3D() {
  const [enhanced, setEnhanced] = useState(false);
  const [reducedMotion, setReducedMotion] = useState(false);

  useEffect(() => {
    // jsdom (tests) et vieux navigateurs n'exposent pas matchMedia : dans ce
    // cas on reste sur le repli 2D, sans erreur.
    if (typeof window.matchMedia !== 'function') {
      return undefined;
    }
    const motionQuery = window.matchMedia(REDUCED_MOTION_QUERY);
    setReducedMotion(motionQuery.matches);
    const onChange = (event: MediaQueryListEvent): void => setReducedMotion(event.matches);
    motionQuery.addEventListener('change', onChange);

    const connection = (navigator as Navigator & { connection?: { saveData?: boolean } }).connection;
    if (motionQuery.matches || connection?.saveData || !hasWebGL()) {
      return () => motionQuery.removeEventListener('change', onChange);
    }

    const windowWithIdle = window as Window & {
      requestIdleCallback?: (callback: () => void, options?: { timeout: number }) => number;
      cancelIdleCallback?: (handle: number) => void;
    };
    if (windowWithIdle.requestIdleCallback) {
      const handle = windowWithIdle.requestIdleCallback(() => setEnhanced(true), { timeout: 1400 });
      return () => {
        windowWithIdle.cancelIdleCallback?.(handle);
        motionQuery.removeEventListener('change', onChange);
      };
    }
    const handle = window.setTimeout(() => setEnhanced(true), 500);
    return () => {
      window.clearTimeout(handle);
      motionQuery.removeEventListener('change', onChange);
    };
  }, []);

  if (enhanced) {
    return <HeroBusinessOS3D reducedMotion={reducedMotion} />;
  }
  return <ParticleField />;
}

export default HeroScene3D;
