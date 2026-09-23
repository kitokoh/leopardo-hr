'use client';

import { motion } from 'framer-motion';
import Image from 'next/image';
import Link from 'next/link';
import { ArrowRight, Fingerprint, MapPin, Wallet } from 'lucide-react';
import type { AppLocale } from '@/lib/i18n';

/**
 * #8072 — hook différenciant remonté dans la première moitié de page :
 * le pointage biométrique physique (bornes ZKTeco, NFC, QR, GPS, hors ligne)
 * connecté à la paie. Aucun concurrent du benchmark ne le propose ; il était
 * noyé dans une carte de features standard.
 *
 * Visuel : capture réelle de l'app mobile (public/screenshots), jamais de
 * mockup fictif (passe PA2-MKT).
 */

type Benefit = {
  icon: React.ReactNode;
  title: string;
  description: string;
};

type Copy = {
  badge: string;
  title: string;
  subtitle: string;
  benefits: Benefit[];
  cta: string;
  ctaSecondary: string;
  visualAlt: string;
  visualChips: string[];
};

const iconClass = 'w-5 h-5 text-emerald-600 dark:text-emerald-400';

const copyByLocale: Record<AppLocale, Copy> = {
  fr: {
    badge: 'Pointage biométrique',
    title: 'Vos pointeuses ZKTeco connectées à la paie en temps réel',
    subtitle:
      'Bornes ZKTeco, NFC, QR code et GPS mobile : les pointages arrivent directement dans Leopardo, prêts pour la paie — même hors ligne.',
    benefits: [
      {
        icon: <Fingerprint className={iconClass} />,
        title: 'Fin du pointage papier',
        description: 'Les présences sont enregistrées à la borne ou sur mobile, sans feuilles à ressaisir.',
      },
      {
        icon: <MapPin className={iconClass} />,
        title: 'Présence en direct',
        description: 'Qui est présent, où, sur quel site : visible en temps réel par les managers.',
      },
      {
        icon: <Wallet className={iconClass} />,
        title: 'Heures → paie sans ressaisie',
        description: 'Les heures et majorations alimentent les variables de paie automatiquement.',
      },
    ],
    cta: 'Voir le pointage en action',
    ctaSecondary: 'Découvrir les apps mobiles',
    visualAlt: 'Application mobile Leopardo : pointage et présence en temps réel',
    visualChips: ['ZKTeco', 'NFC', 'QR', 'GPS', 'Hors ligne'],
  },
  en: {
    badge: 'Biometric attendance',
    title: 'Your ZKTeco time clocks connected to payroll in real time',
    subtitle:
      'ZKTeco kiosks, NFC, QR code and mobile GPS: clock-ins flow straight into Leopardo, ready for payroll — even offline.',
    benefits: [
      {
        icon: <Fingerprint className={iconClass} />,
        title: 'No more paper clock-ins',
        description: 'Attendance is recorded at the kiosk or on mobile — no sheets to re-enter.',
      },
      {
        icon: <MapPin className={iconClass} />,
        title: 'Live presence',
        description: 'Who is present, where, on which site: visible to managers in real time.',
      },
      {
        icon: <Wallet className={iconClass} />,
        title: 'Hours → payroll, no re-entry',
        description: 'Hours and overtime feed payroll variables automatically.',
      },
    ],
    cta: 'See attendance in action',
    ctaSecondary: 'Discover the mobile apps',
    visualAlt: 'Leopardo mobile app: clock-in and real-time attendance',
    visualChips: ['ZKTeco', 'NFC', 'QR', 'GPS', 'Offline'],
  },
  tr: {
    badge: 'Biyometrik yoklama',
    title: 'ZKTeco pdks cihazlarınız bordroya gerçek zamanlı bağlı',
    subtitle:
      'ZKTeco terminalleri, NFC, QR kod ve mobil GPS: giriş-çıkışlar doğrudan Leopardo’ya akar, bordroya hazır — çevrimdışıyken bile.',
    benefits: [
      {
        icon: <Fingerprint className={iconClass} />,
        title: 'Kâğıt yoklamaya son',
        description: 'Giriş-çıkışlar terminalde veya mobilde kaydedilir, yeniden girilecek çizelge yok.',
      },
      {
        icon: <MapPin className={iconClass} />,
        title: 'Canlı yoklama',
        description: 'Kim nerede, hangi sahada: yöneticiler gerçek zamanlı görür.',
      },
      {
        icon: <Wallet className={iconClass} />,
        title: 'Saatler → bordro, yeniden giriş yok',
        description: 'Saatler ve fazla mesailer bordro değişkenlerini otomatik besler.',
      },
    ],
    cta: 'Yoklamayı canlı görün',
    ctaSecondary: 'Mobil uygulamaları keşfedin',
    visualAlt: 'Leopardo mobil uygulaması: giriş-çıkış ve gerçek zamanlı yoklama',
    visualChips: ['ZKTeco', 'NFC', 'QR', 'GPS', 'Çevrimdışı'],
  },
  ar: {
    badge: 'تسجيل الحضور بالبيومتري',
    title: 'أجهزة ZKTeco الخاصة بك متصلة بالرواتب في الوقت الفعلي',
    subtitle:
      'أجهزة ZKTeco وNFC وQR وGPS على الجوال: تصل تسجيلات الحضور مباشرة إلى Leopardo، جاهزة للرواتب — حتى دون اتصال.',
    benefits: [
      {
        icon: <Fingerprint className={iconClass} />,
        title: 'نهاية الحضور الورقي',
        description: 'يُسجَّل الحضور عند الجهاز أو على الجوال، بلا أوراق لإعادة إدخالها.',
      },
      {
        icon: <MapPin className={iconClass} />,
        title: 'حضور مباشر',
        description: 'من هو حاضر، أين، في أي موقع: مرئي للمدراء في الوقت الفعلي.',
      },
      {
        icon: <Wallet className={iconClass} />,
        title: 'الساعات → الرواتب بلا إعادة إدخال',
        description: 'تغذي الساعات والإضافي متغيرات الرواتب تلقائياً.',
      },
    ],
    cta: 'شاهد الحضور أثناء العمل',
    ctaSecondary: 'اكتشف تطبيقات الجوال',
    visualAlt: 'تطبيق Leopardo على الجوال: تسجيل الحضور في الوقت الفعلي',
    visualChips: ['ZKTeco', 'NFC', 'QR', 'GPS', 'دون اتصال'],
  },
};

export interface ZKTecoHookSectionProps {
  locale?: AppLocale;
}

export function ZKTecoHookSection({ locale = 'fr' }: ZKTecoHookSectionProps) {
  const copy = copyByLocale[locale] ?? copyByLocale.fr;

  return (
    <section
      aria-labelledby="zkteco-hook-title"
      className="relative py-24 overflow-hidden bg-slate-50/60 dark:bg-slate-900/40"
    >
      <div className="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="grid lg:grid-cols-2 gap-14 items-center">
          {/* Texte */}
          <motion.div
            initial={{ y: 20 }}
            whileInView={{ y: 0 }}
            viewport={{ once: true }}
            transition={{ duration: 0.6 }}
          >
            <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-emerald-500/[0.08] border border-emerald-500/15 text-emerald-700 dark:text-emerald-400 text-sm font-semibold mb-6">
              <span className="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse" />
              {copy.badge}
            </div>
            <h2
              id="zkteco-hook-title"
              className="text-3xl sm:text-4xl lg:text-5xl font-black text-slate-900 dark:text-white mb-5 tracking-tight"
            >
              {copy.title}
            </h2>
            <p className="text-lg text-slate-600 dark:text-slate-400 mb-8">
              {copy.subtitle}
            </p>

            <ul className="space-y-5 mb-10">
              {copy.benefits.map((benefit) => (
                <li key={benefit.title} className="flex gap-4">
                  <div className="flex-shrink-0 inline-flex items-center justify-center w-10 h-10 rounded-xl bg-emerald-500/10 dark:bg-emerald-500/15">
                    {benefit.icon}
                  </div>
                  <div>
                    <div className="font-bold text-slate-900 dark:text-white">
                      {benefit.title}
                    </div>
                    <div className="text-sm text-slate-600 dark:text-slate-400">
                      {benefit.description}
                    </div>
                  </div>
                </li>
              ))}
            </ul>

            <div className="flex flex-wrap items-center gap-4">
              <Link
                href="/demo"
                className="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-semibold transition-colors"
              >
                {copy.cta}
                <ArrowRight className="w-4 h-4" aria-hidden="true" />
              </Link>
              <Link
                href="/mobile"
                className="inline-flex items-center gap-2 px-6 py-3 rounded-xl border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 font-semibold hover:border-emerald-400 hover:text-emerald-600 dark:hover:text-emerald-400 transition-colors"
              >
                {copy.ctaSecondary}
              </Link>
            </div>
          </motion.div>

          {/* Visuel : capture réelle de l'app mobile */}
          <motion.div
            initial={{ y: 30, opacity: 0 }}
            whileInView={{ y: 0, opacity: 1 }}
            viewport={{ once: true }}
            transition={{ duration: 0.6, delay: 0.15 }}
            className="relative"
          >
            <div className="absolute -inset-4 bg-gradient-to-r from-emerald-500/15 to-cyan-500/15 rounded-3xl blur-2xl" aria-hidden="true" />
            <div className="relative mx-auto max-w-sm rounded-2xl overflow-hidden shadow-2xl border border-slate-200/60 dark:border-slate-700/60 bg-white dark:bg-slate-900">
              <Image
                src="/screenshots/mobile-attendance.png"
                alt={copy.visualAlt}
                width={390}
                height={844}
                className="w-full h-auto"
              />
            </div>
            <div className="mt-4 flex flex-wrap justify-center gap-2">
              {copy.visualChips.map((chip) => (
                <span
                  key={chip}
                  className="px-3 py-1 rounded-full bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-600 dark:text-slate-300"
                >
                  {chip}
                </span>
              ))}
            </div>
          </motion.div>
        </div>
      </div>
    </section>
  );
}

export default ZKTecoHookSection;
