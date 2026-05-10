'use client';

import { motion } from 'framer-motion';
import { AlertCircle } from 'lucide-react';

export interface ProblemItem {
  title: string;
  description: string;
  icon?: React.ReactNode;
}

export interface ProblemSectionProps {
  title: string;
  subtitle: string;
  items: ProblemItem[];
  badge?: {
    text: string;
    icon?: React.ReactNode;
  };
}

export function ProblemSection({
  title,
  subtitle,
  items,
  badge,
}: ProblemSectionProps) {
  return (
    <section className="relative py-32 overflow-hidden">
      <div className="absolute inset-0 bg-gradient-to-b from-red-50/30 via-white to-slate-50/50 dark:from-red-900/10 dark:via-slate-950 dark:to-slate-900/50" />

      <div className="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        {/* Header */}
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          whileInView={{ opacity: 1, y: 0 }}
          viewport={{ once: true }}
          transition={{ duration: 0.6 }}
          className="text-center mb-20"
        >
          {badge && (
            <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-red-500/[0.08] border border-red-500/15 text-red-700 dark:text-red-400 text-sm font-semibold mb-6">
              {badge.icon && <span className="w-1.5 h-1.5 rounded-full bg-red-500 animate-pulse" />}
              {badge.text}
            </div>
          )}
          <h2 className="text-4xl sm:text-5xl lg:text-6xl font-black text-slate-900 dark:text-white mb-6 tracking-tight">
            {title}
          </h2>
          <p className="text-xl text-slate-600 dark:text-slate-400 max-w-2xl mx-auto">
            {subtitle}
          </p>
        </motion.div>

        {/* Problems Grid */}
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          {items.map((item, index) => (
            <motion.div
              key={index}
              initial={{ opacity: 0, y: 40 }}
              whileInView={{ opacity: 1, y: 0 }}
              viewport={{ once: true, margin: '-80px' }}
              transition={{ duration: 0.6, delay: index * 0.08, ease: [0.22, 1, 0.36, 1] }}
              whileHover={{ y: -4, transition: { duration: 0.25 } }}
              className="group"
            >
              <div className="relative h-full bg-white dark:bg-slate-900/80 backdrop-blur-sm rounded-2xl border border-slate-200/80 dark:border-slate-800/80 p-8 transition-all duration-300 group-hover:border-red-200/50 dark:group-hover:border-red-800/50 group-hover:shadow-lg">
                {/* Icon */}
                <div className="w-12 h-12 rounded-xl bg-gradient-to-br from-red-500 to-orange-500 flex items-center justify-center shadow-lg group-hover:scale-110 transition-transform duration-300 mb-6">
                  <div className="w-6 h-6 text-white">
                    {item.icon || <AlertCircle className="w-6 h-6" />}
                  </div>
                </div>

                {/* Content */}
                <h3 className="text-lg font-bold text-slate-900 dark:text-white mb-3">
                  {item.title}
                </h3>
                <p className="text-slate-600 dark:text-slate-400 leading-relaxed text-[15px]">
                  {item.description}
                </p>
              </div>
            </motion.div>
          ))}
        </div>
      </div>
    </section>
  );
}
