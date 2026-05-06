'use client';

import Link from 'next/link';
import { motion } from 'framer-motion';
import { CheckCircle2, ArrowRight } from 'lucide-react';

export interface PricingCardProps {
  name: string;
  price: number | null;
  currency?: string;
  period?: string;
  description: string;
  features: string[];
  cta: {
    text: string;
    href: string;
  };
  highlighted?: boolean;
  badge?: string;
  index?: number;
}

export function PricingCard({
  name,
  price,
  currency = 'EUR',
  period = '/mois',
  description,
  features,
  cta,
  highlighted = false,
  badge,
  index = 0,
}: PricingCardProps) {
  return (
    <motion.div
      initial={{ opacity: 0, y: 40 }}
      whileInView={{ opacity: 1, y: 0 }}
      viewport={{ once: true, margin: '-80px' }}
      transition={{ duration: 0.6, delay: index * 0.1, ease: [0.22, 1, 0.36, 1] }}
      whileHover={highlighted ? { y: -12, transition: { duration: 0.25 } } : { y: -4, transition: { duration: 0.25 } }}
      className={`group relative ${highlighted ? 'lg:scale-105' : ''}`}
    >
      {/* Glow effect for highlighted */}
      {highlighted && (
        <div className="absolute -inset-px rounded-3xl bg-gradient-to-r from-emerald-500 to-cyan-500 opacity-0 group-hover:opacity-20 blur-xl transition-opacity duration-500" />
      )}

      <div
        className={`relative h-full rounded-3xl border transition-all duration-300 p-8 flex flex-col ${
          highlighted
            ? 'bg-gradient-to-br from-emerald-50 to-cyan-50 dark:from-emerald-900/20 dark:to-cyan-900/20 border-emerald-200/50 dark:border-emerald-800/50 shadow-xl'
            : 'bg-white dark:bg-slate-900/80 border-slate-200/80 dark:border-slate-800/80 group-hover:border-emerald-200/50 dark:group-hover:border-emerald-800/50 group-hover:shadow-xl'
        }`}
      >
        {/* Badge */}
        {badge && (
          <div className="inline-flex items-center justify-center w-fit px-3 py-1 rounded-full bg-emerald-500 text-white text-xs font-bold uppercase tracking-wider mb-4">
            {badge}
          </div>
        )}

        {/* Plan name */}
        <h3 className="text-2xl font-bold text-slate-900 dark:text-white mb-2">{name}</h3>
        <p className="text-slate-500 dark:text-slate-400 text-sm mb-6">{description}</p>

        {/* Price */}
        <div className="mb-8">
          <div className="flex items-baseline gap-1">
            <span className="text-5xl font-black text-slate-900 dark:text-white">{price ?? 'Sur devis'}</span>
            <span className="text-slate-500 dark:text-slate-400 font-medium">{currency}</span>
          </div>
          <p className="text-slate-500 dark:text-slate-400 text-sm mt-2">{period}</p>
        </div>

        {/* CTA */}
        <Link
          href={cta.href}
          className={`w-full py-3 px-4 rounded-xl font-semibold transition-all duration-300 flex items-center justify-center gap-2 mb-8 ${
            highlighted
              ? 'bg-gradient-to-r from-emerald-500 to-emerald-600 text-white hover:shadow-[0_20px_60px_-15px_rgba(16,185,129,0.4)] hover:scale-[1.02]'
              : 'bg-slate-100 dark:bg-slate-800 text-slate-900 dark:text-white hover:bg-slate-200 dark:hover:bg-slate-700'
          }`}
        >
          {cta.text}
          <ArrowRight className="w-4 h-4" />
        </Link>

        {/* Features */}
        <div className="space-y-3 flex-1">
          {features.map((feature, i) => (
            <div key={i} className="flex items-start gap-3">
              <CheckCircle2 className="w-5 h-5 text-emerald-500 flex-shrink-0 mt-0.5" />
              <span className="text-slate-600 dark:text-slate-400 text-sm">{feature}</span>
            </div>
          ))}
        </div>
      </div>
    </motion.div>
  );
}
