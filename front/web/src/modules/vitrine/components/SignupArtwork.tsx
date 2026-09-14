'use client';

import { motion, useReducedMotion } from 'framer-motion';
import { CalendarClock, Users, Wallet } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

/**
 * SignupArtwork — visuel de la colonne gauche de /signup.
 *
 * Retour propriétaire (2026-09-14) : la colonne de gauche expliquait le
 * parcours (« Votre espace en 2 minutes », « Votre inscription en 3 temps »…)
 * alors que le formulaire dit déjà tout. Le texte est remplacé par une scène
 * 3D isométrique : trois plateaux empilés (équipe, pointage, paie) surmontés
 * de leur application.
 *
 * Choix techniques (aucune dépendance ajoutée) :
 *   • CSS 3D natif (`perspective` + `preserve-3d`), pas de WebGL : pas de chunk
 *     `three` sur une page d'acquisition, rendu identique serveur/client ;
 *   • AUCUN texte — donc rien à traduire, et rien de superflu à lire ;
 *   • animation d'oscillation lente, **désactivée** si `prefers-reduced-motion`
 *     (le dépôt respecte déjà ce réglage ailleurs) ;
 *   • lisible en mode clair et sombre.
 */

const PLATES = [
  { icon: Users, z: 0, tone: 'from-emerald-500/90 to-teal-600/90', chipX: '-26%', chipY: '58%' },
  { icon: CalendarClock, z: 78, tone: 'from-cyan-500/85 to-emerald-500/85', chipX: '62%', chipY: '62%' },
  { icon: Wallet, z: 156, tone: 'from-emerald-400/85 to-cyan-500/85', chipX: '16%', chipY: '26%' },
] as const;

const ISO_X = 58;
const ISO_Z = -38;

function Plate({
  icon: Icon,
  z,
  tone,
  chipX,
  chipY,
}: {
  icon: LucideIcon;
  z: number;
  tone: string;
  chipX: string;
  chipY: string;
}) {
  return (
    <div
      className="absolute left-1/2 top-1/2 h-[190px] w-[190px] -translate-x-1/2 -translate-y-1/2"
      style={{ transform: `translate(-50%, -50%) translateZ(${z}px)`, transformStyle: 'preserve-3d' }}
    >
      {/* Plateau */}
      <div
        className={`h-full w-full rounded-[26px] border border-white/40 bg-gradient-to-br ${tone} shadow-[0_18px_40px_-12px_rgba(6,78,59,0.55)] backdrop-blur-sm dark:border-white/10`}
      >
        <div className="signup-art-grid h-full w-full rounded-[26px] opacity-[0.22]" />
      </div>

      {/* Application posée sur le plateau : contre-rotation pour faire face
          à l'écran (annule rotateX puis rotateZ de la scène). */}
      <div
        className="absolute h-[62px] w-[62px]"
        style={{
          left: chipX,
          top: chipY,
          transform: `translateZ(34px) rotateZ(${-ISO_Z}deg) rotateX(${-ISO_X}deg)`,
          transformStyle: 'preserve-3d',
        }}
      >
        <div className="flex h-full w-full items-center justify-center rounded-2xl border border-slate-200/80 bg-white/95 shadow-xl shadow-emerald-900/20 dark:border-slate-700 dark:bg-slate-900/95">
          <Icon className="h-7 w-7 text-emerald-600 dark:text-emerald-400" aria-hidden="true" />
        </div>
      </div>
    </div>
  );
}

export function SignupArtwork({ className = '' }: { className?: string }) {
  const reduceMotion = useReducedMotion();

  return (
    <div
      className={`relative mx-auto flex aspect-square w-full max-w-[460px] items-center justify-center ${className}`}
      aria-hidden="true"
    >
      {/* Halo */}
      <div className="signup-art-halo pointer-events-none absolute inset-0 rounded-full blur-2xl" />

      {/* Sol / ombre portée */}
      <div className="pointer-events-none absolute bottom-[14%] h-[64px] w-[300px] rounded-[50%] bg-emerald-900/15 blur-2xl dark:bg-emerald-400/10" />

      <div className="[perspective:1400px]">
        {/* Rotation isométrique statique portée par un conteneur neutre :
            framer-motion réécrit la transform de la cible animée, la scène et
            l'animation restent donc sur deux niveaux distincts. */}
        <div
          className="preserve-3d-scene relative h-[190px] w-[190px]"
          style={{ transform: `rotateX(${ISO_X}deg) rotateZ(${ISO_Z}deg)` }}
        >
          <motion.div
            className="preserve-3d-scene absolute inset-0"
            animate={reduceMotion ? undefined : { rotateY: [0, 7, 0, -7, 0] }}
            transition={reduceMotion ? undefined : { duration: 22, repeat: Infinity, ease: 'easeInOut' }}
          >
            {PLATES.map((plate) => (
              <Plate key={plate.z} {...plate} />
            ))}
          </motion.div>
        </div>
      </div>
    </div>
  );
}

export default SignupArtwork;
