'use client';

import { motion } from 'framer-motion';
import { Building2, Users, Clock, TrendingUp } from 'lucide-react';
import type { AppLocale } from '@/lib/i18n';

type MetricItem = {
  icon: React.ReactNode;
  value: string;
  label: string;
};

const metricsByLocale: Record<AppLocale, MetricItem[]> = {
  fr: [
    { icon: <Building2 className="w-6 h-6" />, value: '500+', label: 'Entreprises actives' },
    { icon: <Users className="w-6 h-6" />, value: '50K+', label: 'Employes geres' },
    { icon: <Clock className="w-6 h-6" />, value: '99.9%', label: 'Disponibilite SLA' },
    { icon: <TrendingUp className="w-6 h-6" />, value: '40%', label: 'Gain de temps moyen' },
  ],
  en: [
    { icon: <Building2 className="w-6 h-6" />, value: '500+', label: 'Active companies' },
    { icon: <Users className="w-6 h-6" />, value: '50K+', label: 'Employees managed' },
    { icon: <Clock className="w-6 h-6" />, value: '99.9%', label: 'SLA uptime' },
    { icon: <TrendingUp className="w-6 h-6" />, value: '40%', label: 'Average time saved' },
  ],
  tr: [
    { icon: <Building2 className="w-6 h-6" />, value: '500+', label: 'Aktif sirket' },
    { icon: <Users className="w-6 h-6" />, value: '50K+', label: 'Yonetilen calisan' },
    { icon: <Clock className="w-6 h-6" />, value: '99.9%', label: 'SLA suresi' },
    { icon: <TrendingUp className="w-6 h-6" />, value: '40%', label: 'Ortalama zaman tasarrufu' },
  ],
  ar: [
    { icon: <Building2 className="w-6 h-6" />, value: '+500', label: 'شركة نشطة' },
    { icon: <Users className="w-6 h-6" />, value: '+50 الف', label: 'موظف مدار' },
    { icon: <Clock className="w-6 h-6" />, value: '99.9%', label: 'وقت التشغيل' },
    { icon: <TrendingUp className="w-6 h-6" />, value: '40%', label: 'توفير الوقت' },
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
