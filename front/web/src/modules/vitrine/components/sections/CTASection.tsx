'use client';

import Link from 'next/link';
import { motion } from 'framer-motion';
import { ArrowRight } from 'lucide-react';
import { useSearchParams } from 'next/navigation';
import { withLocaleHref } from '../../lib/locale-href';

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
  const searchParams = useSearchParams();
  const search = searchParams.toString();
  const resolvedHeadline = headline ?? title ?? '';
  const resolvedSubheadline = subheadline ?? description;
  const resolvedPrimaryCta = ctaPrimary ?? primaryCta;
  const resolvedSecondaryCta = ctaSecondary ?? secondaryCta;
  const bgClasses = {
    gradient: 'bg-gradient-to-r from-emerald-700 via-emerald-600 to-amber-700/80',
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
      <div className="absolute bottom-1/4 -right-32 w-[500px] h-[500px] bg-amber-400/15 rounded-full blur-[120px]" />

      <div className="relative max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        {/* Badge */}
        {badge && (
          <motion.div
            initial={{ y: 20 }}
            whileInView={{ y: 0 }}
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
          initial={{ y: 30 }}
          whileInView={{ y: 0 }}
          viewport={{ once: true }}
          transition={{ duration: 0.8, delay: 0.1 }}
          className="text-4xl sm:text-5xl lg:text-6xl font-black text-white mb-6 tracking-tight leading-[1.1]"
        >
          {resolvedHeadline}
        </motion.h2>

        {/* Subheadline */}
        {resolvedSubheadline && (
          <motion.p
            initial={{ y: 20 }}
            whileInView={{ y: 0 }}
            viewport={{ once: true }}
            transition={{ duration: 0.8, delay: 0.2 }}
            className="text-xl text-white/80 mb-12 max-w-2xl mx-auto leading-relaxed font-light"
          >
            {resolvedSubheadline}
          </motion.p>
        )}

        {/* CTAs */}
        <motion.div
          initial={{ y: 20 }}
          whileInView={{ y: 0 }}
          viewport={{ once: true }}
          transition={{ duration: 0.8, delay: 0.3 }}
          className="flex flex-col sm:flex-row items-center justify-center gap-5"
        >
          {resolvedPrimaryCta && (
            <Link
              href={withLocaleHref(resolvedPrimaryCta.href, search)}
              {...(/^https?:\/\//i.test(resolvedPrimaryCta.href) ? { target: '_blank', rel: 'noopener noreferrer' } : {})}
              className="group relative px-9 py-4 bg-white text-emerald-800 font-extrabold rounded-2xl overflow-hidden transition-all duration-300 shadow-2xl hover:scale-[1.03] hover:shadow-amber-500/20 active:scale-[0.98] ring-2 ring-white/40"
            >
              <span className="relative z-10 flex items-center gap-2.5 text-base">
                {resolvedPrimaryCta.text}
                <ArrowRight className="w-5 h-5 group-hover:translate-x-1.5 transition-transform duration-300" />
              </span>
              <span className="absolute inset-0 bg-gradient-to-r from-emerald-50 via-white to-amber-50/70 opacity-0 transition-opacity duration-300 group-hover:opacity-100" />
            </Link>
          )}

          {resolvedSecondaryCta && (
            <Link
              href={withLocaleHref(resolvedSecondaryCta.href, search)}
              {...(/^https?:\/\//i.test(resolvedSecondaryCta.href) ? { target: '_blank', rel: 'noopener noreferrer' } : {})}
              className="group flex items-center gap-2.5 px-9 py-4 bg-white/10 text-white font-bold rounded-2xl border border-white/30 hover:bg-white/20 hover:border-white/50 transition-all duration-300 backdrop-blur-md shadow-lg"
            >
              {resolvedSecondaryCta.text}
              <ArrowRight className="w-5 h-5 group-hover:translate-x-1.5 transition-transform duration-300" />
            </Link>
          )}
        </motion.div>
      </div>
    </section>
  );
}
