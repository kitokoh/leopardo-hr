'use client';

import { motion } from 'framer-motion';
import { CheckCircle2 } from 'lucide-react';

export interface SolutionFeature {
  title: string;
  description: string;
}

export interface SolutionSectionProps {
  title: string;
  subtitle: string;
  description: string;
  features: SolutionFeature[];
  badge?: {
    text: string;
    icon?: React.ReactNode;
  };
}

export function SolutionSection({
  title,
  subtitle,
  description,
  features,
  badge,
}: SolutionSectionProps) {
  return (
    <section className="relative py-32 overflow-hidden">
      <div className="absolute inset-0 bg-gradient-to-b from-emerald-50/30 via-white to-slate-50/50 dark:from-emerald-900/10 dark:via-slate-950 dark:to-slate-900/50" />

      <div className="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        {/* Header */}
        <motion.div
          initial={{ y: 20 }}
          whileInView={{ y: 0 }}
          viewport={{ once: true }}
          transition={{ duration: 0.6 }}
          className="text-center mb-20"
        >
          {badge && (
            <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-emerald-500/[0.08] border border-emerald-500/15 text-emerald-700 dark:text-emerald-400 text-sm font-semibold mb-6">
              {badge.icon && <span className="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse" />}
              {badge.text}
            </div>
          )}
          <h2 className="text-4xl sm:text-5xl lg:text-6xl font-black text-slate-900 dark:text-white mb-6 tracking-tight">
            {title}
            {subtitle && (
              <span className="block bg-gradient-to-r from-emerald-500 to-cyan-500 bg-clip-text text-transparent">
                {subtitle}
              </span>
            )}
          </h2>
          <p className="text-xl text-slate-600 dark:text-slate-400 max-w-2xl mx-auto">
            {description}
          </p>
        </motion.div>

        {/* Features Grid */}
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          {features.map((feature, index) => (
            <motion.div
              key={index}
              initial={{ y: 40 }}
              whileInView={{ y: 0 }}
              viewport={{ once: true, margin: '-80px' }}
              transition={{ duration: 0.6, delay: index * 0.08, ease: [0.22, 1, 0.36, 1] }}
              whileHover={{ y: -4, transition: { duration: 0.25 } }}
              className="group"
            >
              <div className="relative h-full rounded-3xl border border-slate-200/90 bg-white/90 p-8 shadow-sm backdrop-blur-xl transition-all duration-300 hover:border-emerald-300 hover:shadow-xl hover:shadow-emerald-500/5 dark:border-slate-800/90 dark:bg-slate-900/90 dark:hover:border-emerald-900/60 dark:hover:shadow-emerald-950/20">
                <div className="pointer-events-none absolute inset-x-8 top-0 h-px bg-gradient-to-r from-transparent via-emerald-500/30 to-transparent opacity-0 transition-opacity duration-300 group-hover:opacity-100" />
                {/* Icon */}
                <div className="mb-6 flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-400 shadow-md shadow-emerald-500/20 transition-transform duration-300 group-hover:scale-105">
                  <CheckCircle2 className="h-7 w-7 text-white" />
                </div>

                {/* Content */}
                <h3 className="mb-3 text-xl font-bold tracking-tight text-slate-900 dark:text-white">
                  {feature.title}
                </h3>
                <p className="text-[15px] leading-relaxed text-slate-600 dark:text-slate-300">
                  {feature.description}
                </p>
              </div>
            </motion.div>
          ))}
        </div>
      </div>
    </section>
  );
}
