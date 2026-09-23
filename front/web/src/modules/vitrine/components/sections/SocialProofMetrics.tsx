'use client';

import { motion } from 'framer-motion';
import { Building2, Globe2, Layers, ShieldCheck } from 'lucide-react';
import type { AppLocale } from '@/lib/i18n';
import { HeroGithubBadge } from '../hero/HeroProductShowcase';

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
    { icon: <Globe2 className="w-6 h-6" />, value: '21', label: 'Pays au catalogue de paie' },
    { icon: <Layers className="w-6 h-6" />, value: '4', label: 'Langues produit (FR/EN/TR/AR)' },
    { icon: <Building2 className="w-6 h-6" />, value: '7', label: 'Surfaces produit (web, mobile, kiosk)' },
    { icon: <ShieldCheck className="w-6 h-6" />, value: '1000+', label: 'Fichiers de tests backend' },
  ],
  en: [
    { icon: <Globe2 className="w-6 h-6" />, value: '21', label: 'Countries in the payroll catalog' },
    { icon: <Layers className="w-6 h-6" />, value: '4', label: 'Product languages (FR/EN/TR/AR)' },
    { icon: <Building2 className="w-6 h-6" />, value: '7', label: 'Product surfaces (web, mobile, kiosk)' },
    { icon: <ShieldCheck className="w-6 h-6" />, value: '1000+', label: 'Backend test files' },
  ],
  tr: [
    { icon: <Globe2 className="w-6 h-6" />, value: '21', label: 'Bordro katalogundaki ulke' },
    { icon: <Layers className="w-6 h-6" />, value: '4', label: 'Urun dili (FR/EN/TR/AR)' },
    { icon: <Building2 className="w-6 h-6" />, value: '7', label: 'Urun yuzeyi (web, mobil, kiosk)' },
    { icon: <ShieldCheck className="w-6 h-6" />, value: '1000+', label: 'Backend test dosyasi' },
  ],
  ar: [
    { icon: <Globe2 className="w-6 h-6" />, value: '21', label: 'دولة في كتالوج الرواتب' },
    { icon: <Layers className="w-6 h-6" />, value: '4', label: 'لغات المنتج (FR/EN/TR/AR)' },
    { icon: <Building2 className="w-6 h-6" />, value: '7', label: 'واجهات المنتج (ويب، موبايل، كشك)' },
    { icon: <ShieldCheck className="w-6 h-6" />, value: '1000+', label: 'ملف اختبار للخلفية' },
  ],
};

export interface SocialProofMetricsProps {
  locale?: AppLocale;
}

/**
 * #8070 — bloc sécurité/donnée honnête : uniquement des faits vérifiables
 * (chiffrement revendiqué par la FAQ produit, choix d'hébergement cloud ou
 * self-host, conformité locale présentée comme en cours — aucune certification
 * inventée).
 */
const securityLineByLocale: Record<AppLocale, string> = {
  fr: 'Chiffrement AES-256 & TLS 1.3 · Hébergement cloud ou sur votre serveur · Conformité locale en cours de déploiement',
  en: 'AES-256 & TLS 1.3 encryption · Cloud hosting or on your own server · Local compliance rolling out',
  tr: 'AES-256 ve TLS 1.3 şifreleme · Bulutta veya kendi sunucunuzda barındırma · Yerel uyumluluk kademeli olarak geliyor',
  ar: 'تشفير AES-256 وTLS 1.3 · استضافة سحابية أو على خادمك الخاص · التوافق المحلي قيد التوسيع',
};

export function SocialProofMetrics({ locale = 'fr' }: SocialProofMetricsProps) {
  const metrics = metricsByLocale[locale] ?? metricsByLocale.fr;
  const securityLine = securityLineByLocale[locale] ?? securityLineByLocale.fr;

  return (
    <section className="relative py-16 overflow-hidden">
      <div className="absolute inset-0 bg-gradient-to-r from-emerald-600 to-cyan-600 dark:from-emerald-800 dark:to-cyan-800" />
      <div className="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHZpZXdCb3g9IjAgMCA0MCA0MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48Y2lyY2xlIGN4PSIyMCIgY3k9IjIwIiByPSIxIiBmaWxsPSJyZ2JhKDI1NSwyNTUsMjU1LDAuMSkiLz48L3N2Zz4=')] opacity-50" />

      <div className="relative max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="grid grid-cols-2 md:grid-cols-4 gap-8">
          {metrics.map((metric, index) => (
            <motion.div
              key={metric.label}
              initial={{ y: 20 }}
              whileInView={{ y: 0 }}
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

        {/* #8070 — badge GitHub (stars/forks/licence) + sécurité honnête */}
        <div className="mt-10 flex flex-col items-center gap-4">
          <HeroGithubBadge locale={locale} />
          <p
            data-testid="social-proof-security-line"
            className="inline-flex items-center gap-2 text-center text-xs sm:text-sm font-medium text-white/85"
          >
            <ShieldCheck className="h-4 w-4 shrink-0" aria-hidden="true" />
            {securityLine}
          </p>
        </div>
      </div>
    </section>
  );
}
