'use client';

import type { ComponentType, ReactNode } from 'react';

import { motion } from 'framer-motion';

type ModulePageShellProps = {
  title: string;
  subtitle?: string;
  accentClassName?: string;
  /** Icône Lucide optionnelle (API étendue — pages restaurant). */
  icon?: ComponentType<{ className?: string }>;
  /** Description optionnelle (alias API étendue de `subtitle`). */
  description?: string;
  /** #7860 — emplacement d'actions aligné à droite du bandeau compact. */
  actions?: ReactNode;
  children: ReactNode;
};

/**
 * #7860 — bandeau de page COMPACT : l'ancien hero (~200 px — eyebrow
 * « Système Leopardo », h1 text-4xl, p-8) consommait ~10 % de l'écran avant
 * la première donnée utile. Tout tient désormais sur une seule rangée
 * (icône h-9 + titre + sous-titre), le contenu commence beaucoup plus haut.
 * Le dégradé de l'icône suit le branding du tenant (#7713) avec repli emerald.
 */
export function ModulePageShell({
  title,
  subtitle,
  accentClassName = 'bg-white',
  icon: Icon,
  description,
  actions,
  children,
}: ModulePageShellProps) {
  return (
    <div className="space-y-5">
      <motion.section
        initial={{ opacity: 0, y: -12 }}
        animate={{ opacity: 1, y: 0 }}
        className={`relative overflow-hidden rounded-2xl border border-white/20 bg-white/70 shadow-premium backdrop-blur-xl ${accentClassName}`}
      >
        <div className="absolute inset-0 bg-gradient-to-br from-brand-500/5 via-transparent to-cyan-500/5 pointer-events-none" />

        <div className="relative flex items-center gap-3 p-4 md:px-5">
          {Icon ? (
            <span className="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-[var(--tenant-primary,#047857)] to-[var(--tenant-accent,#0e7490)] text-white shadow-md">
              <Icon className="h-5 w-5" />
            </span>
          ) : null}
          <div className="flex min-w-0 flex-1 flex-wrap items-baseline gap-x-3 gap-y-0.5">
            <h1 className="text-xl font-bold tracking-tight text-slate-950 md:text-2xl">
              {title}
            </h1>
            {description ?? subtitle ? (
              <p className="min-w-0 truncate text-sm text-slate-600">
                {description ?? subtitle}
              </p>
            ) : null}
          </div>
          {actions ? <div className="flex shrink-0 items-center gap-2">{actions}</div> : null}
        </div>
      </motion.section>

      <motion.div
        initial={{ opacity: 0 }}
        animate={{ opacity: 1 }}
        transition={{ delay: 0.2 }}
      >
        {children}
      </motion.div>
    </div>
  );
}
