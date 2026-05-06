'use client';

import { motion } from 'framer-motion';
import { TestimonialCard, type TestimonialCardProps } from './TestimonialCard';

export interface TestimonialsSectionProps {
  title: string;
  subtitle: string;
  badge?: {
    text: string;
    icon?: React.ReactNode;
  };
  testimonials: TestimonialCardProps[];
  columns?: 1 | 2 | 3;
}

export function TestimonialsSection({
  title,
  subtitle,
  badge,
  testimonials,
  columns = 3,
}: TestimonialsSectionProps) {
  const gridColsClass = {
    1: 'md:grid-cols-1',
    2: 'md:grid-cols-2',
    3: 'md:grid-cols-3',
  }[columns];

  return (
    <section className="relative py-32 overflow-hidden">
      <div className="absolute inset-0 bg-gradient-to-b from-slate-50/50 via-white to-slate-50/50 dark:from-slate-900/50 dark:via-slate-950 dark:to-slate-900/50" />

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

        {/* Testimonials Grid */}
        <div className={`grid grid-cols-1 ${gridColsClass} gap-8`}>
          {testimonials.map((testimonial, index) => (
            <TestimonialCard key={index} {...testimonial} index={index} />
          ))}
        </div>
      </div>
    </section>
  );
}
