'use client';

import { useState } from 'react';
import { motion } from 'framer-motion';
import { PricingCard, type PricingCardProps } from './PricingCard';

export interface PricingSectionProps {
  title: string;
  subtitle: string;
  badge?: {
    text: string;
    icon?: React.ReactNode;
  };
  plans: PricingCardProps[];
  showToggle?: boolean;
  toggleLabel?: {
    monthly: string;
    annual: string;
  };
}

export function PricingSection({
  title,
  subtitle,
  badge,
  plans,
  showToggle = false,
  toggleLabel = { monthly: 'Mensuel', annual: 'Annuel' },
}: PricingSectionProps) {
  const [isAnnual, setIsAnnual] = useState(false);

  return (
    <section className="relative py-32 overflow-hidden">
      <div className="absolute inset-0 bg-gradient-to-b from-white via-slate-50/50 to-white dark:from-slate-950 dark:via-slate-900/50 dark:to-slate-950" />

      <div className="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        {/* Header */}
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          whileInView={{ opacity: 1, y: 0 }}
          viewport={{ once: true }}
          transition={{ duration: 0.6 }}
          className="text-center mb-16"
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
        </motion.div>

        {/* Toggle */}
        {showToggle && (
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            transition={{ duration: 0.6, delay: 0.1 }}
            className="flex items-center justify-center gap-4 mb-16"
          >
            <span className={`text-sm font-medium ${!isAnnual ? 'text-slate-900 dark:text-white' : 'text-slate-500 dark:text-slate-400'}`}>
              {toggleLabel.monthly}
            </span>
            <button
              onClick={() => setIsAnnual(!isAnnual)}
              className="relative inline-flex h-8 w-14 items-center rounded-full bg-slate-200 dark:bg-slate-800 transition-colors duration-300"
              style={{
                backgroundColor: isAnnual ? 'rgb(16, 185, 129)' : undefined,
              }}
            >
              <motion.div
                layout
                className="inline-block h-6 w-6 transform rounded-full bg-white shadow-lg"
                animate={{ x: isAnnual ? 28 : 4 }}
                transition={{ type: 'spring', stiffness: 500, damping: 30 }}
              />
            </button>
            <span className={`text-sm font-medium ${isAnnual ? 'text-slate-900 dark:text-white' : 'text-slate-500 dark:text-slate-400'}`}>
              {toggleLabel.annual}
            </span>
            {isAnnual && (
              <motion.span
                initial={{ opacity: 0, scale: 0.8 }}
                animate={{ opacity: 1, scale: 1 }}
                className="ml-2 px-3 py-1 rounded-full bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 text-xs font-bold"
              >
                -20%
              </motion.span>
            )}
          </motion.div>
        )}

        {/* Pricing Cards */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-6xl mx-auto">
          {plans.map((plan, index) => (
            <PricingCard
              key={index}
              {...plan}
              index={index}
              price={isAnnual && plan.price ? Math.round(plan.price * 12 * 0.8) : plan.price}
              period={isAnnual ? '/an' : '/mois'}
            />
          ))}
        </div>
      </div>
    </section>
  );
}
