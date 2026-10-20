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

            <ul className="mb-10 space-y-4">
              {copy.benefits.map((benefit) => (
                <li
                  key={benefit.title}
                  className="flex gap-4 rounded-2xl border border-slate-200/80 bg-white/70 p-4.5 shadow-sm backdrop-blur-md transition-all duration-300 hover:border-emerald-300 hover:bg-white dark:border-slate-800/80 dark:bg-slate-900/60 dark:hover:border-emerald-800"
                >
                  <div className="flex-shrink-0 inline-flex items-center justify-center w-11 h-11 rounded-xl bg-gradient-to-br from-emerald-500/15 to-teal-500/10 text-emerald-600 ring-1 ring-emerald-500/20 dark:from-emerald-500/25 dark:to-teal-500/15 dark:text-emerald-400">
                    {benefit.icon}
                  </div>
                  <div>
                    <div className="text-base font-bold text-slate-900 dark:text-white">
                      {benefit.title}
                    </div>
                    <div className="text-sm leading-relaxed text-slate-600 dark:text-slate-300">
                      {benefit.description}
                    </div>
                  </div>
                </li>
              ))}
            </ul>

            <div className="flex flex-wrap items-center gap-4">
              <Link
                href="/demo"
                className="group inline-flex items-center gap-2.5 rounded-2xl bg-gradient-to-r from-emerald-600 to-teal-600 px-7 py-3.5 text-base font-bold text-white shadow-lg shadow-emerald-950/20 transition-all duration-300 hover:scale-[1.02] hover:shadow-emerald-500/25 active:scale-[0.98]"
              >
                {copy.cta}
                <ArrowRight className="h-4 w-4 transition-transform duration-300 group-hover:translate-x-1" aria-hidden="true" />
              </Link>
              <Link
                href="/mobile"
                className="inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-white/80 px-7 py-3.5 text-base font-semibold text-slate-700 shadow-sm backdrop-blur-sm transition-all duration-300 hover:border-emerald-400 hover:bg-white hover:text-emerald-600 dark:border-slate-700 dark:bg-slate-900/80 dark:text-slate-200 dark:hover:border-emerald-500 dark:hover:text-emerald-400"
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
            <div className="absolute -inset-4 rounded-3xl bg-gradient-to-r from-emerald-500/20 to-cyan-500/20 blur-2xl" aria-hidden="true" />
            <div className="relative mx-auto max-w-sm overflow-hidden rounded-3xl border border-slate-200/80 bg-white p-2 shadow-2xl shadow-emerald-950/15 dark:border-slate-700/80 dark:bg-slate-900">
              <div className="overflow-hidden rounded-2xl">
                <Image
                  src="/screenshots/mobile-attendance.png"
                  alt={copy.visualAlt}
                  width={390}
                  height={844}
                  className="h-auto w-full"
                />
              </div>
            </div>
            <div className="mt-5 flex flex-wrap justify-center gap-2">
              {copy.visualChips.map((chip) => (
                <span
                  key={chip}
                  className="rounded-full border border-emerald-500/20 bg-white/90 px-3.5 py-1 text-xs font-bold text-slate-700 shadow-sm backdrop-blur-md dark:border-emerald-500/30 dark:bg-slate-800/90 dark:text-slate-200"
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
