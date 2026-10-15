'use client';

/**
 * #8497 (reste de I-10) — loader de marque Leopardo.
 *
 * Exigences de la Partie 9 : chargement sobre, rapide, animation de logo
 * LEGERE, ACCESSIBLE, respectant `prefers-reduced-motion`, non bloquante.
 *
 * Le SVG anime (`logo-loader.svg`) utilise SMIL, qui ne lit PAS la media
 * query : on choisit donc l'asset cote React — version animee par defaut,
 * version STATIQUE (`favicon-premium.svg`) quand l'utilisateur demande moins
 * de mouvement. Aucun spinner generique en plus (BRANDING_NOTES.md).
 *
 * Le libelle eventuel vient du catalogue i18n (jamais en dur, PA2-I18N-014).
 */

import { useEffect, useState } from 'react';

const ANIMATED_SRC = '/brand/logo-loader.svg';
const STATIC_SRC = '/brand/favicon-premium.svg';

export interface BrandLoaderProps {
  /** Texte d'attente (deja localise par l'appelant). */
  label?: string;
  /** Taille en pixels (carre). */
  size?: number;
}

export function BrandLoader({ label, size = 64 }: BrandLoaderProps) {
  const [reducedMotion, setReducedMotion] = useState(false);

  useEffect(() => {
    if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') {
      return;
    }
    const query = window.matchMedia('(prefers-reduced-motion: reduce)');
    setReducedMotion(query.matches);
    const onChange = (event: MediaQueryListEvent) => setReducedMotion(event.matches);
    query.addEventListener('change', onChange);
    return () => query.removeEventListener('change', onChange);
  }, []);

  return (
    <div
      role="status"
      aria-live="polite"
      className="flex flex-col items-center justify-center gap-3"
    >
      {/* eslint-disable-next-line @next/next/no-img-element -- SVG de marque, pas d'optimisation Next requise */}
      <img
        src={reducedMotion ? STATIC_SRC : ANIMATED_SRC}
        alt=""
        width={size}
        height={size}
        data-testid="brand-loader-image"
        data-reduced-motion={reducedMotion ? 'true' : 'false'}
      />
      {label ? (
        <p className="text-sm text-slate-600 dark:text-slate-400">{label}</p>
      ) : null}
    </div>
  );
}

export default BrandLoader;
