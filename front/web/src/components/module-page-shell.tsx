'use client';

import type { ReactNode } from 'react';

type ModulePageShellProps = {
  title: string;
  subtitle: string;
  accentClassName: string;
  children: ReactNode;
};

import { motion } from 'framer-motion';

export function ModulePageShell({
  title,
  subtitle,
  accentClassName,
  children,
}: ModulePageShellProps) {
  return (
    <div className="space-y-6">
      <motion.section
        initial={{ opacity: 0, y: -20 }}
        animate={{ opacity: 1, y: 0 }}
        className={`relative overflow-hidden rounded-3xl border border-white/20 bg-white/70 shadow-premium backdrop-blur-xl dark:border-slate-800/50 dark:bg-slate-900/70 ${accentClassName}`}
      >
        <div className="absolute inset-0 bg-gradient-to-br from-brand-500/5 via-transparent to-cyan-500/5 pointer-events-none" />

        <div className="relative space-y-3 p-8">
          <div className="flex items-center gap-2">
            <div className="h-1 w-8 rounded-full bg-gradient-to-r from-emerald-500 to-cyan-500" />
            <p className="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 dark:text-slate-500">
              Système Leopardo
            </p>
          </div>

          <h1 className="text-4xl font-black tracking-tight text-slate-950 dark:text-white">
            {title}
          </h1>

          <p className="max-w-2xl text-base leading-relaxed text-slate-600 dark:text-slate-400">
            {subtitle}
          </p>
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
