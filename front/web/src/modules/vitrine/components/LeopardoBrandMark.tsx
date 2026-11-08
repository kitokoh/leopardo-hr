'use client';

import React from 'react';

export interface LeopardoBrandMarkProps {
  /**
   * Taille de l'emblème :
   * - sm : 32px (barres denses, navigation compacte)
   * - md : 40px (navbar standard, footer standard)
   * - lg : 52px (en-têtes d'onboarding, cards de présentation)
   * - xl : 68px (héro onboarding, splash screen)
   */
  size?: 'sm' | 'md' | 'lg' | 'xl';
  /** Variante d'affichage : 'full' (emblème + texte) ou 'icon' (emblème seul). */
  variant?: 'full' | 'icon';
  /** Ambiance de couleur : 'auto' (suit le thème dark/light), 'dark' (fond sombre garanti), 'light'. */
  tone?: 'auto' | 'dark' | 'light';
  /** Slogan sous le titre (défaut : "BUSINESS OS"). */
  subtitle?: string;
  /** Active l'aura lumineuse diffuse au survol. */
  withGlow?: boolean;
  className?: string;
}

const SIZE_CONFIG = {
  sm: {
    box: 'w-8 h-8 rounded-lg',
    svg: 32,
    radius: 7,
    title: 'text-base',
    sub: 'text-[8px] tracking-[0.2em]',
  },
  md: {
    box: 'w-10 h-10 rounded-xl',
    svg: 40,
    radius: 9,
    title: 'text-lg',
    sub: 'text-[9px] tracking-[0.22em]',
  },
  lg: {
    box: 'w-13 h-13 rounded-2xl',
    svg: 52,
    radius: 12,
    title: 'text-2xl',
    sub: 'text-[10px] tracking-[0.24em]',
  },
  xl: {
    box: 'w-16 h-16 rounded-2xl',
    svg: 64,
    radius: 15,
    title: 'text-3xl',
    sub: 'text-xs tracking-[0.26em]',
  },
} as const;

export function LeopardoBrandMark({
  size = 'md',
  variant = 'full',
  tone = 'auto',
  subtitle = 'BUSINESS OS',
  withGlow = true,
  className = '',
}: LeopardoBrandMarkProps) {
  const cfg = SIZE_CONFIG[size];

  // Gestion des classes de texte selon le tone
  const titleClass =
    tone === 'dark'
      ? 'text-white'
      : tone === 'light'
      ? 'text-slate-900'
      : 'text-slate-900 dark:text-white';

  const subClass =
    tone === 'dark'
      ? 'text-amber-400'
      : tone === 'light'
      ? 'text-amber-600'
      : 'text-amber-600 dark:text-amber-400';

  return (
    <div className={`inline-flex items-center gap-3 select-none group ${className}`}>
      {/* Emblème géométrique avec aura luminescente */}
      <div className="relative flex-shrink-0">
        {withGlow && (
          <div
            className="absolute -inset-1 rounded-2xl bg-gradient-to-r from-amber-400/30 via-emerald-400/30 to-cyan-500/30 blur-md opacity-40 group-hover:opacity-85 transition-opacity duration-500 pointer-events-none"
            aria-hidden="true"
          />
        )}

        <div
          className={`relative ${cfg.box} flex items-center justify-center bg-gradient-to-br from-slate-950 via-slate-900 to-slate-950 ring-1 ring-amber-400/30 shadow-md shadow-emerald-950/20 group-hover:ring-amber-400/60 transition-all duration-300 overflow-hidden`}
        >
          {/* Ligne d'accent lumineuse interne */}
          <div
            className="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-amber-300/60 to-transparent pointer-events-none"
            aria-hidden="true"
          />

          {/* SVG Haute Fidélité officiel Leopardo */}
          <svg
            width={cfg.svg}
            height={cfg.svg}
            viewBox="0 0 64 64"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
            className="w-full h-full p-1.5 transition-transform duration-300 group-hover:scale-105"
            aria-hidden="true"
          >
            <defs>
              <linearGradient id={`leo-grad-accent-${size}`} x1="0" y1="0" x2="1" y2="1">
                <stop offset="0%" stopColor="#fbbf24" />
                <stop offset="55%" stopColor="#34d399" />
                <stop offset="100%" stopColor="#059669" />
              </linearGradient>
              <linearGradient id={`leo-grad-white-${size}`} x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stopColor="#ffffff" />
                <stop offset="100%" stopColor="#ecfeff" />
              </linearGradient>
            </defs>

            {/* Branche L du monogramme en blanc pur avec lueur froide */}
            <path
              d="M15 44V17.5h8V37h14v7H15Z"
              fill={`url(#leo-grad-white-${size})`}
              filter="drop-shadow(0 2px 4px rgba(0,0,0,0.3))"
            />

            {/* Branche T du monogramme en or ambre & émeraude */}
            <path
              d="M38 17.5h14v7h-7V44h-7V17.5Z"
              fill={`url(#leo-grad-accent-${size})`}
              filter="drop-shadow(0 2px 4px rgba(5,150,105,0.4))"
            />

            {/* Micro-gemme centrale d'énergie or */}
            <circle cx="38" cy="24.5" r="1.5" fill="#fef08a" />
          </svg>
        </div>
      </div>

      {/* Logotype typographique */}
      {variant === 'full' && (
        <div className="flex flex-col">
          <span
            className={`font-black ${cfg.title} leading-none tracking-tight transition-colors duration-200 ${titleClass}`}
          >
            Leopardo
          </span>
          {subtitle && (
            <span className={`font-extrabold uppercase ${cfg.sub} mt-0.5 leading-none ${subClass}`}>
              {subtitle}
            </span>
          )}
        </div>
      )}
    </div>
  );
}
