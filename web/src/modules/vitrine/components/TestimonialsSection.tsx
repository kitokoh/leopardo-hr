'use client';

import { motion } from 'framer-motion';
import { Star, Quote } from 'lucide-react';
import { testimonials } from '../data/testimonials';

export function TestimonialsSection() {
  return (
    <section id="temoignages" className="relative py-32 overflow-hidden">
      <div className="absolute inset-0 bg-gradient-to-b from-slate-50/50 via-white to-slate-50/50 dark:from-slate-900/50 dark:via-slate-950 dark:to-slate-900/50" />

      <div className="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        {/* Header */}
        <div className="text-center mb-20 gsap-reveal">
          <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-amber-500/[0.08] border border-amber-500/15 text-amber-700 dark:text-amber-400 text-sm font-semibold mb-6">
            <span className="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse" />
            Temoignages
          </div>
          <h2 className="text-4xl sm:text-5xl lg:text-6xl font-black text-slate-900 dark:text-white mb-6 tracking-tight">
            Ils nous font{' '}
            <span className="bg-gradient-to-r from-amber-500 to-orange-500 bg-clip-text text-transparent">confiance</span>
          </h2>
          <p className="text-xl text-slate-500 dark:text-slate-400 max-w-2xl mx-auto">
            Plus de 500 entreprises utilisent Leopardo RH au quotidien.
          </p>
        </div>

        {/* Cards */}
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6 max-w-5xl mx-auto">
          {testimonials.map((t, index) => (
            <motion.div
              key={index}
              initial={{ opacity: 0, y: 30 }}
              whileInView={{ opacity: 1, y: 0 }}
              viewport={{ once: true, margin: '-60px' }}
              transition={{ duration: 0.6, delay: index * 0.1 }}
              className="group relative"
            >
              <div className="relative bg-white dark:bg-slate-900/80 backdrop-blur-sm rounded-3xl border border-slate-200/80 dark:border-slate-800/80 p-8 transition-all duration-300 hover:shadow-xl hover:border-amber-200/50 dark:hover:border-amber-800/50">
                {/* Quote icon */}
                <Quote className="w-8 h-8 text-amber-200 dark:text-amber-900/50 mb-4" />

                {/* Stars */}
                <div className="flex gap-1 mb-4">
                  {Array.from({ length: t.rating }).map((_, i) => (
                    <Star key={i} className="w-4 h-4 fill-amber-400 text-amber-400" />
                  ))}
                </div>

                {/* Content */}
                <p className="text-slate-600 dark:text-slate-300 leading-relaxed mb-6 text-[15px]">
                  &ldquo;{t.content}&rdquo;
                </p>

                {/* Author */}
                <div className="flex items-center gap-3">
                  <div className="w-10 h-10 rounded-full bg-gradient-to-br from-emerald-400 to-cyan-500 flex items-center justify-center text-white text-sm font-black">
                    {t.avatar}
                  </div>
                  <div>
                    <div className="text-sm font-bold text-slate-900 dark:text-white">{t.name}</div>
                    <div className="text-xs text-slate-500">{t.role} - {t.company}</div>
                  </div>
                </div>
              </div>
            </motion.div>
          ))}
        </div>
      </div>
    </section>
  );
}
