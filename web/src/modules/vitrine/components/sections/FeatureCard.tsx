'use client';

import { motion } from 'framer-motion';
import { CheckCircle2 } from 'lucide-react';
import Image from 'next/image';

export interface FeatureCardProps {
  icon: React.ReactNode;
  title: string;
  description: string;
  details?: string[];
  image?: string;
  gradient: string;
  stats?: {
    value: string;
    label: string;
  };
  variant?: 'default' | 'highlighted';
  index?: number;
}

export function FeatureCard({
  icon,
  title,
  description,
  details = [],
  image,
  gradient,
  stats,
  variant = 'default',
  index = 0,
}: FeatureCardProps) {
  const isHighlighted = variant === 'highlighted';

  return (
    <motion.div
      initial={{ opacity: 0, y: 40 }}
      whileInView={{ opacity: 1, y: 0 }}
      viewport={{ once: true, margin: '-80px' }}
      transition={{ duration: 0.6, delay: index * 0.08, ease: [0.22, 1, 0.36, 1] }}
      whileHover={{ y: -8, transition: { duration: 0.25 } }}
      className={`group relative ${isHighlighted ? 'lg:col-span-1 lg:row-span-2' : ''}`}
    >
      {/* Glow effect */}
      <div className={`absolute -inset-px rounded-3xl bg-gradient-to-r ${gradient} opacity-0 group-hover:opacity-20 blur-xl transition-opacity duration-500`} />

      <div
        className={`relative h-full bg-white dark:bg-slate-900/80 backdrop-blur-sm rounded-3xl border border-slate-200/80 dark:border-slate-800/80 p-8 transition-all duration-300 group-hover:border-emerald-200/50 dark:group-hover:border-emerald-800/50 group-hover:shadow-xl ${
          isHighlighted ? 'bg-gradient-to-br from-emerald-50 to-cyan-50 dark:from-emerald-900/20 dark:to-cyan-900/20' : ''
        }`}
      >
        {/* Icon + Stats */}
        <div className="flex items-center justify-between mb-6">
          <div className={`w-14 h-14 rounded-2xl bg-gradient-to-br ${gradient} flex items-center justify-center shadow-lg group-hover:scale-110 transition-transform duration-300`}>
            <div className="w-7 h-7 text-white">{icon}</div>
          </div>
          {stats && (
            <div className="text-right">
              <div className="text-2xl font-black text-slate-900 dark:text-white">{stats.value}</div>
              <div className="text-[11px] font-medium text-slate-500 uppercase tracking-wider">{stats.label}</div>
            </div>
          )}
        </div>

        {/* Content */}
        <h3 className="text-xl font-bold text-slate-900 dark:text-white mb-3">{title}</h3>
        <p className="text-slate-500 dark:text-slate-400 leading-relaxed mb-6 text-[15px]">{description}</p>

        {/* Details */}
        {details.length > 0 && (
          <div className="space-y-2.5 mb-6">
            {details.map((detail, i) => (
              <div key={i} className="flex items-center gap-2.5 text-sm text-slate-600 dark:text-slate-400">
                <CheckCircle2 className="w-4 h-4 text-emerald-500 flex-shrink-0" />
                <span>{detail}</span>
              </div>
            ))}
          </div>
        )}

        {/* Image */}
        {image && (
          <div className="relative w-full h-48 rounded-2xl overflow-hidden bg-gradient-to-br from-slate-100 to-slate-50 dark:from-slate-800 dark:to-slate-900 mt-6">
            <Image
              src={image}
              alt={title}
              fill
              className="object-cover group-hover:scale-105 transition-transform duration-300"
            />
          </div>
        )}
      </div>
    </motion.div>
  );
}
