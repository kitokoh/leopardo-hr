'use client';

import { motion } from 'framer-motion';
import { Quote, Star } from 'lucide-react';
import type { AppLocale } from '@/lib/i18n';
import { TESTIMONIALS_ARE_DEMO } from '@/modules/vitrine/data/testimonials';
import {
  getIllustrativeExampleLabel,
  getIllustrativeExampleSuffix,
} from '@/modules/vitrine/lib/vitrine-locale';

type HighlightTestimonial = {
  quote: string;
  name: string;
  role: string;
  company: string;
  avatar: string;
};

const highlightByLocale: Record<AppLocale, HighlightTestimonial> = {
  fr: {
    quote: "Depuis que nous utilisons Leopardo RH, nous avons reduit de 40% le temps consacre a l'administration RH. La paie multi-pays et le pointage biometrique ont transforme nos operations.",
    name: 'Amina Diallo',
    role: 'Directrice des Ressources Humaines',
    company: 'Entreprise IT · 350 employés, 3 pays',
    avatar: 'AD',
  },
  en: {
    quote: "Since adopting Leopardo RH, we cut HR admin time by 40%. Multi-country payroll and biometric attendance transformed our day-to-day operations across three offices.",
    name: 'Amina Diallo',
    role: 'HR Director',
    company: 'IT Company · 350 employees, 3 countries',
    avatar: 'AD',
  },
  tr: {
    quote: "Leopardo RH'yi kullanmaya basladigimizdan beri IK yonetim surelerimizi %40 azalttik. Cok ulkeli bordro ve biyometrik devam takibi operasyonlarimizi donusturdu.",
    name: 'Amina Diallo',
    role: 'IK Direktoru',
    company: 'BT Şirketi · 350 çalışan, 3 ülke',
    avatar: 'AD',
  },
  ar: {
    quote: "منذ اعتمادنا Leopardo RH، خفضنا وقت إدارة الموارد البشرية بنسبة 40%. الرواتب متعددة البلدان والحضور البيومتري غيّرا عملياتنا اليومية.",
    name: 'Amina Diallo',
    role: 'مديرة الموارد البشرية',
    company: 'شركة تقنية · 350 موظف، 3 دول',
    avatar: 'AD',
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
          {TESTIMONIALS_ARE_DEMO && (
            <span className="inline-flex items-center px-3 py-1 rounded-full bg-amber-100/70 dark:bg-amber-500/10 border border-amber-300/50 dark:border-amber-500/30 text-amber-700 dark:text-amber-400 text-xs font-semibold tracking-wide uppercase mb-6">
              {getIllustrativeExampleLabel(locale)}
            </span>
          )}

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
                <div className="text-base font-bold text-slate-900 dark:text-white">
                  {testimonial.name}
                  {TESTIMONIALS_ARE_DEMO && (
                    <span className="ml-1.5 font-normal text-slate-500 dark:text-slate-400">
                      {getIllustrativeExampleSuffix(locale)}
                    </span>
                  )}
                </div>
                <div className="text-sm text-slate-500 dark:text-slate-400">{testimonial.role}</div>
                <div className="text-sm text-slate-400 dark:text-slate-500">{testimonial.company}</div>
              </div>
            </div>
          </div>
        </motion.div>
      </div>
    </section>
  );
}
