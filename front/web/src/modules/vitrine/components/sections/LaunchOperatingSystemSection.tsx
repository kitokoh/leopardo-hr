'use client';

import Link from 'next/link';
import { motion } from 'framer-motion';
import { ArrowRight, BadgeCheck, Building2, Clock3, ShieldCheck, Smartphone } from 'lucide-react';
import type { AppLocale } from '@/lib/i18n';

type LaunchCopy = {
  badge: string;
  title: string;
  subtitle: string;
  primary: string;
  secondary: string;
  outcomes: Array<{
    title: string;
    detail: string;
  }>;
};

const copyByLocale: Record<AppLocale, LaunchCopy> = {
  fr: {
    badge: 'Lancement pilote en 7 jours',
    title: 'Passez d une equipe dispersee a un systeme d entreprise mobile-first.',
    subtitle:
      'Leopardo rassemble pointage, demandes, managers, paie simple, documents, notifications et kiosk dans un parcours exploitable par vos equipes terrain des la premiere semaine.',
    primary: 'Demarrer un essai pilote',
    secondary: 'Voir les apps',
    outcomes: [
      { title: 'Jour 1', detail: 'Entreprise creee, manager actif, pays/devise/langue configures.' },
      { title: 'Jour 2-3', detail: 'Employes ajoutes par formulaire ou QR, horaires et regles affectes.' },
      { title: 'Jour 4-5', detail: 'Pointage mobile/kiosk, demandes d absence et avances visibles manager.' },
      { title: 'Jour 6-7', detail: 'Dashboard, exports, notifications et premiers rapports prets pour decision.' },
    ],
  },
  en: {
    badge: '7-day pilot launch',
    title: 'Move from scattered teams to a mobile-first company operating system.',
    subtitle:
      'Leopardo brings attendance, requests, managers, simple payroll, documents, notifications and kiosk into one workflow your field teams can use in the first week.',
    primary: 'Start a pilot trial',
    secondary: 'See the apps',
    outcomes: [
      { title: 'Day 1', detail: 'Company created, manager active, country/currency/language configured.' },
      { title: 'Day 2-3', detail: 'Employees added by form or QR, schedules and rules assigned.' },
      { title: 'Day 4-5', detail: 'Mobile/kiosk attendance, absence requests and advances visible to managers.' },
      { title: 'Day 6-7', detail: 'Dashboard, exports, notifications and first decision reports ready.' },
    ],
  },
  tr: {
    badge: '7 gunde pilot baslangic',
    title: 'Dagitik ekiplerden mobile-first sirket isletim sistemine gecin.',
    subtitle:
      'Leopardo yoklama, talepler, yoneticiler, basit bordro, belgeler, bildirimler ve kiosku saha ekiplerinizin ilk haftadan kullanabilecegi tek akista birlestirir.',
    primary: 'Pilot denemeyi baslat',
    secondary: 'Uygulamalari gor',
    outcomes: [
      { title: '1. gun', detail: 'Sirket olusturulur, yonetici aktif olur, ulke/para birimi/dil ayarlanir.' },
      { title: '2-3. gun', detail: 'Calisanlar form veya QR ile eklenir, vardiya ve kurallar atanir.' },
      { title: '4-5. gun', detail: 'Mobil/kiosk yoklama, izin talepleri ve avanslar yoneticide gorunur.' },
      { title: '6-7. gun', detail: 'Panel, dis aktarmalar, bildirimler ve ilk karar raporlari hazirdir.' },
    ],
  },
  ar: {
    badge: 'تشغيل تجريبي خلال 7 أيام',
    title: 'حوّل فرقك الميدانية إلى نظام تشغيل شركة يعمل من الهاتف أولا.',
    subtitle:
      'يجمع Leopardo الحضور، الطلبات، المديرين، الرواتب المبسطة، الوثائق، الإشعارات والكشك في مسار واحد قابل للاستخدام منذ الأسبوع الأول.',
    primary: 'ابدأ تجربة تشغيل',
    secondary: 'شاهد التطبيقات',
    outcomes: [
      { title: 'اليوم 1', detail: 'إنشاء الشركة وتفعيل المدير وضبط البلد والعملة واللغة.' },
      { title: 'اليوم 2-3', detail: 'إضافة الموظفين بالنموذج أو QR وتعيين الجداول والقواعد.' },
      { title: 'اليوم 4-5', detail: 'الحضور عبر الهاتف أو الكشك وطلبات الغياب والسلف تظهر للمدير.' },
      { title: 'اليوم 6-7', detail: 'لوحة القيادة والتصدير والإشعارات وأول التقارير جاهزة.' },
    ],
  },
};

const icons = [Building2, Smartphone, Clock3, ShieldCheck];

export function LaunchOperatingSystemSection({ locale }: { locale: AppLocale }) {
  const copy = copyByLocale[locale] ?? copyByLocale.fr;
  const isRtl = locale === 'ar';

  return (
    <section className="relative overflow-hidden px-4 py-20 sm:px-6 lg:px-8" dir={isRtl ? 'rtl' : 'ltr'}>
      <div className="absolute inset-0 bg-white dark:bg-slate-950" />
      <div className="relative mx-auto max-w-7xl">
        <div className="grid gap-10 lg:grid-cols-[0.9fr_1.1fr] lg:items-center">
          <motion.div
            initial={{ opacity: 0, y: 18 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            transition={{ duration: 0.55 }}
          >
            <div className="inline-flex items-center gap-2 rounded-full border border-emerald-500/20 bg-emerald-500/10 px-4 py-2 text-sm font-bold text-emerald-700 dark:text-emerald-300">
              <BadgeCheck className="h-4 w-4" />
              {copy.badge}
            </div>
            <h2 className="mt-6 text-3xl font-black tracking-tight text-slate-950 dark:text-white sm:text-4xl lg:text-5xl">
              {copy.title}
            </h2>
            <p className="mt-5 max-w-2xl text-base leading-8 text-slate-600 dark:text-slate-300 sm:text-lg">
              {copy.subtitle}
            </p>
            <div className="mt-8 flex flex-col gap-3 sm:flex-row">
              <Link
                href="/signup?source=launch_os_section"
                className="inline-flex items-center justify-center gap-2 rounded-2xl bg-slate-950 px-5 py-3 text-sm font-black text-white transition hover:bg-emerald-600 dark:bg-emerald-500 dark:text-slate-950 dark:hover:bg-emerald-300"
              >
                {copy.primary}
                <ArrowRight className={`h-4 w-4 ${isRtl ? 'rotate-180' : ''}`} />
              </Link>
              <Link
                href="/download?source=launch_os_section"
                className="inline-flex items-center justify-center rounded-2xl border border-slate-200 px-5 py-3 text-sm font-bold text-slate-800 transition hover:border-emerald-300 hover:bg-emerald-50 dark:border-slate-800 dark:text-slate-100 dark:hover:bg-emerald-950/30"
              >
                {copy.secondary}
              </Link>
            </div>
          </motion.div>

          <div className="grid gap-4 sm:grid-cols-2">
            {copy.outcomes.map((item, index) => {
              const Icon = icons[index] ?? BadgeCheck;

              return (
                <motion.div
                  key={item.title}
                  initial={{ opacity: 0, y: 18 }}
                  whileInView={{ opacity: 1, y: 0 }}
                  viewport={{ once: true }}
                  transition={{ duration: 0.5, delay: index * 0.08 }}
                  className="rounded-3xl border border-slate-200 bg-slate-50/80 p-6 shadow-sm transition hover:-translate-y-1 hover:border-emerald-200 hover:shadow-xl hover:shadow-emerald-950/5 dark:border-slate-800 dark:bg-slate-900/70 dark:hover:border-emerald-900"
                >
                  <div className="mb-5 inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-500 text-white shadow-lg shadow-emerald-500/20">
                    <Icon className="h-5 w-5" />
                  </div>
                  <h3 className="text-lg font-black text-slate-950 dark:text-white">{item.title}</h3>
                  <p className="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">{item.detail}</p>
                </motion.div>
              );
            })}
          </div>
        </div>
      </div>
    </section>
  );
}

