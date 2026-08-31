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
  children: ReactNode;
};

export function ModulePageShell({
  title,
  subtitle,
  accentClassName = 'bg-white',
  icon: Icon,
  description,
  children,
}: ModulePageShellProps) {
  return (
    <div className="space-y-6">
      <motion.section
        initial={{ opacity: 0, y: -20 }}
        animate={{ opacity: 1, y: 0 }}
        className={`relative overflow-hidden rounded-3xl border border-white/20 bg-white/70 shadow-premium backdrop-blur-xl ${accentClassName}`}
      >
        <div className="absolute inset-0 bg-gradient-to-br from-brand-500/5 via-transparent to-cyan-500/5 pointer-events-none" />

        <div className="relative space-y-3 p-8">
          <div className="flex items-center gap-2">
            <div className="h-1 w-8 rounded-full bg-gradient-to-r from-emerald-500 to-cyan-500" />
            <p className="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">
              Système Leopardo
            </p>
          </div>

          <div className="flex items-center gap-3">
            {Icon ? (
              <span className="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-500 to-cyan-500 text-white shadow-lg">
                <Icon className="h-6 w-6" />
              </span>
            ) : null}
            <h1 className="text-4xl font-black tracking-tight text-slate-950">
              {title}
            </h1>
          </div>

          {description ?? subtitle ? (
            <p className="max-w-2xl text-base leading-relaxed text-slate-600">
              {description ?? subtitle}
            </p>
          ) : null}
        </div>

        {/* Decorative corner element */}
        <div className="absolute -right-12 -top-12 h-48 w-48 rounded-full bg-brand-500/10 blur-3xl" />
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
