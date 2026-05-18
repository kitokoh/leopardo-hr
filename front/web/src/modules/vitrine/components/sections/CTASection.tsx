'use client';

import Link from 'next/link';
import { motion } from 'framer-motion';
import { ArrowRight } from 'lucide-react';

export interface CTASectionProps {
  headline?: string;
  title?: string;
  subheadline?: string;
  description?: string;
  ctaPrimary?: {
    text: string;
    href: string;
  };
  primaryCta?: {
    text: string;
    href: string;
  };
  ctaSecondary?: {
    text: string;
    href: string;
  };
  secondaryCta?: {
    text: string;
    href: string;
  };
  background?: 'gradient' | 'solid' | 'image';
  backgroundImage?: string;
  badge?: {
    text: string;
    icon?: React.ReactNode;
  };
}

export function CTASection({
  headline,
  title,
  subheadline,
  description,
  ctaPrimary,
  primaryCta,
  ctaSecondary,
  secondaryCta,
  background = 'gradient',
  backgroundImage,
  badge,
}: CTASectionProps) {
  const resolvedHeadline = headline ?? title ?? '';
  const resolvedSubheadline = subheadline ?? description;
  const resolvedPrimaryCta = ctaPrimary ?? primaryCta;
  const resolvedSecondaryCta = ctaSecondary ?? secondaryCta;
  const bgClasses = {
    gradient: 'bg-gradient-to-r from-emerald-500 via-emerald-600 to-cyan-600',
    solid: 'bg-slate-900 dark:bg-slate-950',
    image: `bg-cover bg-center`,
  }[background];

  return (
    <section className="relative py-32 overflow-hidden">
      {/* Background */}
      <div
        className={`absolute inset-0 ${bgClasses}`}
        style={
          background === 'image' && backgroundImage
            ? { backgroundImage: `url(${backgroundImage})` }
            : {}
        }
      />

      {/* Overlay */}
      {background === 'image' && (
        <div className="absolute inset-0 bg-gradient-to-r from-slate-900/80 to-slate-900/60" />
      )}

      {/* Gradient orbs */}
      <div className="absolute top-1/4 -left-32 w-[500px] h-[500px] bg-white/10 rounded-full blur-[120px]" />
      <div className="absolute bottom-1/4 -right-32 w-[500px] h-[500px] bg-white/10 rounded-full blur-[120px]" />

      <div className="relative max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        {/* Badge */}
        {badge && (
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            transition={{ duration: 0.6 }}
            className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-white/10 border border-white/20 text-white text-sm font-semibold mb-6 backdrop-blur-sm"
          >
            {badge.icon && <span className="animate-pulse">{badge.icon}</span>}
            {badge.text}
          </motion.div>
        )}

        {/* Headline */}
        <motion.h2
          initial={{ opacity: 0, y: 30 }}
          whileInView={{ opacity: 1, y: 0 }}
          viewport={{ once: true }}
          transition={{ duration: 0.8, delay: 0.1 }}
          className="text-4xl sm:text-5xl lg:text-6xl font-black text-white mb-6 tracking-tight leading-[1.1]"
        >
          {resolvedHeadline}
        </motion.h2>

        {/* Subheadline */}
        {resolvedSubheadline && (
          <motion.p
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            transition={{ duration: 0.8, delay: 0.2 }}
            className="text-xl text-white/80 mb-12 max-w-2xl mx-auto leading-relaxed font-light"
          >
            {resolvedSubheadline}
          </motion.p>
        )}

        {/* CTAs */}
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          whileInView={{ opacity: 1, y: 0 }}
          viewport={{ once: true }}
          transition={{ duration: 0.8, delay: 0.3 }}
          className="flex flex-col sm:flex-row items-center justify-center gap-4"
        >
          {resolvedPrimaryCta && (
            <Link
              href={resolvedPrimaryCta.href}
              className="group relative px-8 py-4 bg-white text-emerald-600 font-bold rounded-2xl overflow-hidden transition-all duration-300 hover:shadow-[0_20px_60px_-15px_rgba(255,255,255,0.3)] hover:scale-[1.03] active:scale-[0.98]"
            >
              <span className="relative z-10 flex items-center gap-2.5 text-base">
                {resolvedPrimaryCta.text}
                <ArrowRight className="w-5 h-5 group-hover:translate-x-1 transition-transform duration-300" />
              </span>
            </Link>
          )}

          {resolvedSecondaryCta && (
            <Link
              href={resolvedSecondaryCta.href}
              className="group flex items-center gap-2.5 px-8 py-4 bg-white/10 text-white font-semibold rounded-2xl border border-white/20 hover:bg-white/20 transition-all duration-300 backdrop-blur-sm"
            >
              {resolvedSecondaryCta.text}
              <ArrowRight className="w-5 h-5 group-hover:translate-x-1 transition-transform duration-300" />
            </Link>
          )}
        </motion.div>
      </div>
    </section>
  );
}
