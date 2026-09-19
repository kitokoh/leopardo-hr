'use client';

import Image from 'next/image';

/**
 * Leo — la mascotte pieuvre de Leopardo. 🐙
 *
 * Une pieuvre pour incarner la plateforme : un cerveau central (la plateforme)
 * et des tentacules qui orchestrent chaque module métier (RH, restaurant,
 * voyage, éducation…). Palette strictement produit : emerald `#10B981` +
 * cyan `#22d3ee` (cf. docs/REFERENTIEL_PRODUIT/COULEURS.md).
 *
 * Purement décorative : `aria-hidden` + `alt=""` systématiques, jamais
 * porteuse d'information. Les assets sont des WebP optimisés (< 50 KB,
 * budget images 500 KB par page respecté). L'animation de flottement est
 * définie dans `globals.css` derrière `prefers-reduced-motion`.
 */
export type LeoMascotVariant = 'base' | 'wave';

const SOURCES: Record<LeoMascotVariant, string> = {
  base: '/brand/leo-octo.webp',
  wave: '/brand/leo-octo-wave.webp',
};

type Props = {
  /** Pose : `base` (souriante) ou `wave` (salue, pour les écrans d'accueil). */
  variant?: LeoMascotVariant;
  /** Taille rendue en pixels (carré). */
  size?: number;
  /** Flottement doux (désactivé automatiquement si reduced-motion). */
  float?: boolean;
  className?: string;
};

export function LeoMascot({ variant = 'base', size = 96, float = false, className = '' }: Props) {
  return (
    <Image
      src={SOURCES[variant]}
      alt=""
      aria-hidden="true"
      width={size}
      height={size}
      priority={false}
      className={`pointer-events-none select-none ${float ? 'leo-mascot-float' : ''} ${className}`.trim()}
    />
  );
}
