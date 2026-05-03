'use client';

import Link from 'next/link';
import { motion, useScroll, useTransform, useSpring } from 'framer-motion';
import { ArrowRight, Play, Sparkles } from 'lucide-react';
import { useRef } from 'react';
import { ParticleField } from '../ParticleField';

export interface HeroSectionProps {
  headline: string;
  subheadline: string;
  badge?: {
    icon?: React.ReactNode;
    text: string;
    label?: string;
  };
  ctaPrimary: {
    text: string;
    href: string;
  };
  ctaSecondary?: {
    text: string;
    href: string;
    icon?: React.ReactNode;
  };
  visual?: React.ReactNode;
  stats?: Array<{
    value: number;
    suffix: string;
    label: string;
    icon: React.ReactNode;
  }>;
  animated?: boolean;
}

export function HeroSection({
  headline,
  subheadline,
  badge,
  ctaPrimary,
  ctaSecondary,
  visual,
  stats,
  animated = true,
}: HeroSectionProps) {
  const ref = useRef<HTMLElement>(null);
  const { scrollYProgress } = useScroll({ target: ref, offset: ['start start', 'end start'] });
  const y = useSpring(useTransform(scrollYProgress, [0, 1], [0, -200]), { stiffness: 80, damping: 30 });
  const opacity = useTransform(scrollYProgress, [0, 0.6], [1, 0]);
  const scale = useTransform(scrollYProgress, [0, 0.6], [1, 0.92]);

  return (
    <section ref={ref} className="relative min-h-[100dvh] flex items-center justify-center overflow-hidden">
      {/* Background layers */}
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_80%_50%_at_50%_-20%,rgba(16,185,129,0.12),transparent)] dark:bg-[radial-gradient(ellipse_80%_50%_at_50%_-20%,rgba(16,185,129,0.08),transparent)]" />
      <div className="absolute inset-0 bg-gradient-to-b from-white via-white to-slate-50/80 dark:from-slate-950 dark:via-slate-950 dark:to-slate-900/80" />

      {/* Grid pattern */}
      <div
        className="absolute inset-0 opacity-[0.03] dark:opacity-[0.05]"
        style={{
          backgroundImage: 'linear-gradient(rgba(0,0,0,0.1) 1px, transparent 1px), linear-gradient(90deg, rgba(0,0,0,0.1) 1px, transparent 1px)',
          backgroundSize: '60px 60px',
        }}
      />

      {animated && <ParticleField />}

      {/* Gradient orbs */}
      <div className="absolute top-1/4 -left-32 w-[500px] h-[500px] bg-emerald-400/15 rounded-full blur-[120px] animate-pulse" />
      <div className="absolute bottom-1/4 -right-32 w-[500px] h-[500px] bg-cyan-400/15 rounded-full blur-[120px] animate-pulse [animation-delay:2s]" />
      <div className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[900px] h-[900px] bg-gradient-to-r from-emerald-500/5 to-cyan-500/5 rounded-full blur-[100px]" />

      <motion.div style={animated ? { y, opacity, scale } : {}} className="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-32 pb-24">
        <div className="text-center max-w-5xl mx-auto">
          {/* Badge */}
          {badge && (
            <motion.div
              initial={animated ? { opacity: 0, y: 20, filter: 'blur(10px)' } : {}}
              animate={animated ? { opacity: 1, y: 0, filter: 'blur(0px)' } : {}}
              transition={{ duration: 0.8 }}
              className="inline-flex items-center gap-2.5 px-4 py-2 rounded-full bg-emerald-500/[0.08] border border-emerald-500/20 text-emerald-700 dark:text-emerald-400 text-sm font-medium mb-10 backdrop-blur-sm"
            >
              {badge.icon && <span className="animate-pulse">{badge.icon}</span>}
              <span>{badge.text}</span>
              {badge.label && (
                <span className="px-2 py-0.5 bg-emerald-500 text-white text-[10px] font-black uppercase tracking-wider rounded-full">
                  {badge.label}
                </span>
              )}
            </motion.div>
          )}

          {/* Heading */}
          <motion.h1
            initial={animated ? { opacity: 0, y: 30 } : {}}
            animate={animated ? { opacity: 1, y: 0 } : {}}
            transition={{ duration: 1, delay: 0.15, ease: [0.22, 1, 0.36, 1] }}
            className="text-5xl sm:text-6xl lg:text-[5.5rem] font-black tracking-tight leading-[0.95] mb-8"
          >
            <span className="block bg-gradient-to-b from-slate-900 via-slate-800 to-slate-600 dark:from-white dark:via-slate-200 dark:to-slate-400 bg-clip-text text-transparent">
              {headline}
            </span>
          </motion.h1>

          {/* Subtitle */}
          <motion.p
            initial={animated ? { opacity: 0, y: 20 } : {}}
            animate={animated ? { opacity: 1, y: 0 } : {}}
            transition={{ duration: 0.8, delay: 0.35 }}
            className="text-lg sm:text-xl lg:text-2xl text-slate-500 dark:text-slate-400 mb-14 max-w-3xl mx-auto leading-relaxed font-light"
          >
            {subheadline}
          </motion.p>

          {/* CTAs */}
          <motion.div
            initial={animated ? { opacity: 0, y: 20 } : {}}
            animate={animated ? { opacity: 1, y: 0 } : {}}
            transition={{ duration: 0.8, delay: 0.5 }}
            className="flex flex-col sm:flex-row items-center justify-center gap-4"
          >
            <Link
              href={ctaPrimary.href}
              className="group relative px-8 py-4 bg-gradient-to-r from-emerald-500 to-emerald-600 text-white font-bold rounded-2xl overflow-hidden transition-all duration-300 hover:shadow-[0_20px_60px_-15px_rgba(16,185,129,0.4)] hover:scale-[1.03] active:scale-[0.98]"
            >
              <span className="relative z-10 flex items-center gap-2.5 text-base">
                {ctaPrimary.text}
                <ArrowRight className="w-5 h-5 group-hover:translate-x-1 transition-transform duration-300" />
              </span>
              <div className="absolute inset-0 bg-gradient-to-r from-emerald-600 to-cyan-600 opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
            </Link>

            {ctaSecondary && (
              <Link
                href={ctaSecondary.href}
                className="group flex items-center gap-3.5 px-8 py-4 bg-white dark:bg-slate-900 text-slate-900 dark:text-white font-semibold rounded-2xl border border-slate-200 dark:border-slate-800 hover:border-emerald-300 dark:hover:border-emerald-800 transition-all duration-300 hover:shadow-xl"
              >
                {ctaSecondary.icon ? (
                  <div className="w-10 h-10 rounded-full bg-gradient-to-br from-emerald-100 to-emerald-50 dark:from-emerald-900/40 dark:to-emerald-900/20 flex items-center justify-center group-hover:scale-110 transition-transform duration-300">
                    {ctaSecondary.icon}
                  </div>
                ) : (
                  <div className="w-10 h-10 rounded-full bg-gradient-to-br from-emerald-100 to-emerald-50 dark:from-emerald-900/40 dark:to-emerald-900/20 flex items-center justify-center group-hover:scale-110 transition-transform duration-300">
                    <Play className="w-4 h-4 text-emerald-600 dark:text-emerald-400 ml-0.5" />
                  </div>
                )}
                {ctaSecondary.text}
              </Link>
            )}
          </motion.div>

          {/* Stats */}
          {stats && stats.length > 0 && (
            <motion.div
              initial={animated ? { opacity: 0, y: 40 } : {}}
              animate={animated ? { opacity: 1, y: 0 } : {}}
              transition={{ duration: 1, delay: 0.7 }}
              className="mt-24 grid grid-cols-2 md:grid-cols-4 gap-8 max-w-4xl mx-auto"
            >
              {stats.map((stat, i) => (
                <div key={i} className="text-center group">
                  <div className="inline-flex items-center justify-center w-12 h-12 rounded-2xl bg-gradient-to-br from-emerald-500/10 to-cyan-500/10 mb-4 group-hover:scale-110 transition-transform duration-300">
                    {stat.icon}
                  </div>
                  <div className="text-3xl sm:text-4xl font-black bg-gradient-to-b from-slate-900 to-slate-600 dark:from-white dark:to-slate-400 bg-clip-text text-transparent">
                    {stat.value}
                    {stat.suffix}
                  </div>
                  <div className="text-sm text-slate-500 dark:text-slate-500 mt-1.5 font-medium">{stat.label}</div>
                </div>
              ))}
            </motion.div>
          )}
        </div>
      </motion.div>

      {/* Scroll indicator */}
      {animated && (
        <motion.div
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          transition={{ delay: 1.5 }}
          className="absolute bottom-8 left-1/2 -translate-x-1/2"
        >
          <motion.div
            animate={{ y: [0, 8, 0] }}
            transition={{ duration: 2.5, repeat: Infinity, ease: 'easeInOut' }}
            className="w-6 h-10 rounded-full border-2 border-slate-300 dark:border-slate-700 flex items-start justify-center p-1.5"
          >
            <motion.div
              animate={{ opacity: [1, 0.3, 1], y: [0, 12, 0] }}
              transition={{ duration: 2.5, repeat: Infinity, ease: 'easeInOut' }}
              className="w-1.5 h-1.5 rounded-full bg-emerald-500"
            />
          </motion.div>
        </motion.div>
      )}
    </section>
  );
}
