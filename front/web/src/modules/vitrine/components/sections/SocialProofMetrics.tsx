'use client';

import { motion } from 'framer-motion';
import { Building2, Globe2, Layers, ShieldCheck } from 'lucide-react';
import type { AppLocale } from '@/lib/i18n';

/**
 * PA2-MKT-006: this section previously showed "500+ active companies",
 * "50K+ employees managed" and "99.9% SLA uptime" — none of which are
 * true. `PILOTAGE.md` ("Clients payants | 0 | 3-5 | 20-30 | 100-150")
 * confirms 0 paying customers exist to date, and no SLA/uptime monitor
 * (`docs/GESTION_PROJET/RUNBOOK_UPTIME_MONITORING.md`) has ever actually
 * been configured/measured — a "99.9%" figure with no monitor behind it
 * is a fabricated number, exactly the "chiffres non trompeurs" risk this
 * ticket flags.
 *
 * Replaced with 4 claims that are true today and checkable directly in
 * the repository, none of which imply a customer count or an SLA that
 * was never measured: number of country-specific payroll rule engines
 * actually implemented, number of supported product locales, number of
 * mobile/web/kiosk surfaces that make up the product, and the size of
 * the automated backend test suite (a real engineering signal a
 * technical evaluator can verify, unlike a marketing number).
 */
type MetricItem = {
  icon: React.ReactNode;
  value: string;
  label: string;
};

const metricsByLocale: Record<AppLocale, MetricItem[]> = {
  fr: [
    { icon: <Globe2 className="w-6 h-6" />, value: '6', label: 'Pays avec regles de paie dediees' },
    { icon: <Layers className="w-6 h-6" />, value: '4', label: 'Langues produit (FR/EN/TR/AR)' },
    { icon: <Building2 className="w-6 h-6" />, value: '7', label: 'Surfaces produit (web, mobile, kiosk)' },
    { icon: <ShieldCheck className="w-6 h-6" />, value: '1200+', label: 'Tests automatises backend' },
  ],
  en: [
    { icon: <Globe2 className="w-6 h-6" />, value: '6', label: 'Countries with dedicated payroll rules' },
    { icon: <Layers className="w-6 h-6" />, value: '4', label: 'Product languages (FR/EN/TR/AR)' },
    { icon: <Building2 className="w-6 h-6" />, value: '7', label: 'Product surfaces (web, mobile, kiosk)' },
    { icon: <ShieldCheck className="w-6 h-6" />, value: '1200+', label: 'Automated backend tests' },
  ],
  tr: [
    { icon: <Globe2 className="w-6 h-6" />, value: '6', label: 'Ozel bordro kurali olan ulke' },
    { icon: <Layers className="w-6 h-6" />, value: '4', label: 'Urun dili (FR/EN/TR/AR)' },
    { icon: <Building2 className="w-6 h-6" />, value: '7', label: 'Urun yuzeyi (web, mobil, kiosk)' },
    { icon: <ShieldCheck className="w-6 h-6" />, value: '1200+', label: 'Otomatik backend testi' },
  ],
  ar: [
    { icon: <Globe2 className="w-6 h-6" />, value: '6', label: 'دول بقواعد رواتب مخصصة' },
    { icon: <Layers className="w-6 h-6" />, value: '4', label: 'لغات المنتج (FR/EN/TR/AR)' },
    { icon: <Building2 className="w-6 h-6" />, value: '7', label: 'واجهات المنتج (ويب، موبايل، كشك)' },
    { icon: <ShieldCheck className="w-6 h-6" />, value: '1200+', label: 'اختبار تلقائي للخلفية' },
  ],
};

export interface SocialProofMetricsProps {
  locale?: AppLocale;
}

export function SocialProofMetrics({ locale = 'fr' }: SocialProofMetricsProps) {
  const metrics = metricsByLocale[locale] ?? metricsByLocale.fr;

  return (
    <section className="relative py-16 overflow-hidden">
      <div className="absolute inset-0 bg-gradient-to-r from-emerald-600 to-cyan-600 dark:from-emerald-800 dark:to-cyan-800" />
      <div className="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHZpZXdCb3g9IjAgMCA0MCA0MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48Y2lyY2xlIGN4PSIyMCIgY3k9IjIwIiByPSIxIiBmaWxsPSJyZ2JhKDI1NSwyNTUsMjU1LDAuMSkiLz48L3N2Zz4=')] opacity-50" />

      <div className="relative max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="grid grid-cols-2 md:grid-cols-4 gap-8">
          {metrics.map((metric, index) => (
            <motion.div
              key={metric.label}
              initial={{ opacity: 0, y: 20 }}
              whileInView={{ opacity: 1, y: 0 }}
              viewport={{ once: true }}
              transition={{ duration: 0.5, delay: index * 0.1 }}
              className="text-center"
            >
              <div className="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-white/10 text-white mb-3">
                {metric.icon}
              </div>
              <div className="text-3xl sm:text-4xl font-black text-white mb-1">
                {metric.value}
              </div>
              <div className="text-sm text-white/80 font-medium">
                {metric.label}
              </div>
            </motion.div>
          ))}
        </div>
      </div>
    </section>
  );
}
