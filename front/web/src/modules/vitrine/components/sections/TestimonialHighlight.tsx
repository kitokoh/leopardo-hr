'use client';

import { motion } from 'framer-motion';
import { Quote, Star } from 'lucide-react';
import type { AppLocale } from '@/lib/i18n';

type HighlightTestimonial = {
  quote: string;
  name: string;
  role: string;
  company: string;
  avatar: string;
  metric: string;
  metricLabel: string;
};

const highlightByLocale: Record<AppLocale, HighlightTestimonial> = {
  fr: {
    quote: "Depuis que nous utilisons Leopardo RH, nous avons reduit de 40% le temps consacre a l'administration RH. La paie multi-pays et le pointage biometrique ont transforme nos operations.",
    name: 'Amina Diallo',
    role: 'Directrice des Ressources Humaines',
    company: 'TechAfrika — 350 employes, 3 pays',
    avatar: 'AD',
    metric: '-40%',
    metricLabel: 'temps admin RH',
  },
  en: {
    quote: "Since adopting Leopardo RH, we cut HR admin time by 40%. Multi-country payroll and biometric attendance transformed our day-to-day operations across three offices.",
    name: 'Amina Diallo',
    role: 'HR Director',
    company: 'TechAfrika — 350 employees, 3 countries',
    avatar: 'AD',
    metric: '-40%',
    metricLabel: 'HR admin time',
  },
  tr: {
    quote: "Leopardo RH'yi kullanmaya basladigimizdan beri IK yonetim surelerimizi %40 azalttik. Cok ulkeli bordro ve biyometrik devam takibi operasyonlarimizi donusturdu.",
    name: 'Amina Diallo',
    role: 'IK Direktoru',
    company: 'TechAfrika — 350 calisan, 3 ulke',
    avatar: 'AD',
    metric: '-40%',
    metricLabel: 'IK yonetim suresi',
  },
  ar: {
    quote: "منذ اعتمادنا Leopardo RH، خفضنا وقت إدارة الموارد البشرية بنسبة 40%. الرواتب متعددة البلدان والحضور البيومتري غيّرا عملياتنا اليومية.",
    name: 'Amina Diallo',
    role: 'مديرة الموارد البشرية',
    company: 'TechAfrika — 350 موظف، 3 دول',
    avatar: 'AD',
    metric: '-40%',
    metricLabel: 'وقت إدارة الموارد البشرية',
  },
};

export interface TestimonialHighlightProps {
  locale?: AppLocale;
}

export function TestimonialHighlight({ locale = 'fr' }: TestimonialHighlightProps) {
  const testimonial = highlightByLocale[locale] ?? highlightByLocale.fr;

  return (
    <section className="relative py-24 overflow-hidden">
      <div className="absolute inset-0 bg-gradient-to-b from-white via-slate-50/80 to-white dark:from-slate-950 dark:via-slate-900/80 dark:to-slate-950" />

      <div className="relative max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <motion.div
          initial={{ opacity: 0, y: 30 }}
          whileInView={{ opacity: 1, y: 0 }}
          viewport={{ once: true }}
          transition={{ duration: 0.7 }}
          className="relative bg-white dark:bg-slate-900 rounded-3xl border border-slate-200/80 dark:border-slate-800/80 p-10 md:p-14 shadow-xl"
        >
          <Quote className="w-12 h-12 text-emerald-200 dark:text-emerald-900/50 mb-6" />

          <div className="flex gap-1 mb-6">
            {Array.from({ length: 5 }).map((_, i) => (
              <Star key={i} className="w-5 h-5 fill-amber-400 text-amber-400" />
            ))}
          </div>

          <blockquote className="text-xl md:text-2xl text-slate-700 dark:text-slate-200 leading-relaxed mb-8 font-medium">
            &ldquo;{testimonial.quote}&rdquo;
          </blockquote>

          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-6">
            <div className="flex items-center gap-4">
              <div className="w-14 h-14 rounded-full bg-gradient-to-br from-emerald-400 to-cyan-500 flex items-center justify-center text-white text-lg font-black">
                {testimonial.avatar}
              </div>
              <div>
                <div className="text-base font-bold text-slate-900 dark:text-white">{testimonial.name}</div>
                <div className="text-sm text-slate-500 dark:text-slate-400">{testimonial.role}</div>
                <div className="text-sm text-slate-400 dark:text-slate-500">{testimonial.company}</div>
              </div>
            </div>

            <div className="flex items-center gap-3 px-6 py-4 bg-emerald-50 dark:bg-emerald-900/20 rounded-2xl border border-emerald-100 dark:border-emerald-800/30">
              <div className="text-3xl font-black text-emerald-600 dark:text-emerald-400">
                {testimonial.metric}
              </div>
              <div className="text-sm text-emerald-700 dark:text-emerald-300 font-medium">
                {testimonial.metricLabel}
              </div>
            </div>
          </div>
        </motion.div>
      </div>
    </section>
  );
}
