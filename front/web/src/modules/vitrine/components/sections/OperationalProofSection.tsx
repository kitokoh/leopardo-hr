'use client';

import Link from 'next/link';
import { ArrowRight, CheckCircle2, MonitorSmartphone, ShieldCheck } from 'lucide-react';
import type { AppLocale } from '@/lib/i18n';

export type OperationalProofSectionProps = {
  locale: AppLocale;
};

type ProofCopy = {
  badge: string;
  title: string;
  subtitle: string;
  primary: string;
  secondary: string;
  proofs: Array<{
    title: string;
    detail: string;
  }>;
};

const copyByLocale: Record<AppLocale, ProofCopy> = {
  fr: {
    badge: 'Pret pour le terrain',
    title: 'Une plateforme unique pour vendre, connecter et operer',
    subtitle: 'Vitrine publique, portail client, admin plateforme, apps employee/manager/admin et kiosk biometrie sont penses comme un meme systeme operationnel.',
    primary: 'Demarrer 30 jours gratuits',
    secondary: 'Voir la demo',
    proofs: [
      { title: '3 apps mobiles', detail: 'Employee, Manager/RH et Platform Admin avec workflows dedies.' },
      { title: '2 apps web', detail: 'Vitrine commerciale et espace client/admin connectes a la meme API.' },
      { title: 'Kiosk & biometrie', detail: 'Pointage terrain ZKTeco, QR et fallback offline-first.' },
      { title: 'API production', detail: 'Health, OpenAPI, smokes par profil et gouvernance release.' },
    ],
  },
  en: {
    badge: 'Field-ready',
    title: 'One platform to sell, connect and operate',
    subtitle: 'Public website, client portal, platform admin, employee/manager/admin mobile apps and biometric kiosk are designed as one operational system.',
    primary: 'Start 30-day trial',
    secondary: 'Watch demo',
    proofs: [
      { title: '3 mobile apps', detail: 'Employee, Manager/HR and Platform Admin with dedicated workflows.' },
      { title: '2 web apps', detail: 'Marketing website and client/admin workspace connected to the same API.' },
      { title: 'Kiosk & biometrics', detail: 'ZKTeco field attendance, QR and offline-first fallback.' },
      { title: 'Production API', detail: 'Health, OpenAPI, profile smokes and release governance.' },
    ],
  },
  tr: {
    badge: 'Saha icin hazir',
    title: 'Satmak, baglamak ve isletmek icin tek platform',
    subtitle: 'Genel vitrin, musteri portali, platform yonetimi, employee/manager/admin mobil uygulamalari ve biyometrik kiosk tek operasyonel sistem olarak tasarlandi.',
    primary: '30 gun ucretsiz basla',
    secondary: 'Demoyu gor',
    proofs: [
      { title: '3 mobil uygulama', detail: 'Employee, Manager/IK ve Platform Admin icin ayrilmis akislar.' },
      { title: '2 web uygulamasi', detail: 'Pazarlama sitesi ve musteri/admin alani ayni API ye bagli.' },
      { title: 'Kiosk ve biyometri', detail: 'ZKTeco saha yoklamasi, QR ve offline-first yedek.' },
      { title: 'Uretim API si', detail: 'Health, OpenAPI, profil smoke testleri ve release yonetisimi.' },
    ],
  },
  ar: {
    badge: 'جاهز للميدان',
    title: 'منصة واحدة للبيع والربط والتشغيل',
    subtitle: 'تعمل الواجهة العامة وبوابة العميل وتطبيقات الجوال والكشك البيومتري كمنظومة تشغيل واحدة.',
    primary: 'ابدأ 30 يوما مجانا',
    secondary: 'شاهد العرض',
    proofs: [
      { title: '3 تطبيقات جوال', detail: 'Employee وManager/RH وPlatform Admin بمسارات مخصصة.' },
      { title: 'تطبيقا ويب', detail: 'واجهة تسويقية ومساحة عميل وإدارة متصلتان بنفس API.' },
      { title: 'كشك وبيومتري', detail: 'حضور ميداني عبر ZKTeco وQR ودعم offline-first.' },
      { title: 'API إنتاجية', detail: 'Health وOpenAPI وsmokes حسب الملف وحوكمة إصدار.' },
    ],
  },
};

export function OperationalProofSection({ locale }: OperationalProofSectionProps) {
  const copy = copyByLocale[locale] ?? copyByLocale.fr;
  const isRtl = locale === 'ar';

  return (
    <section className="px-4 py-16 sm:px-6 lg:px-8" dir={isRtl ? 'rtl' : 'ltr'}>
      <div className="mx-auto max-w-7xl overflow-hidden rounded-[2rem] border border-emerald-200/70 bg-slate-950 text-white shadow-2xl shadow-emerald-950/20 dark:border-emerald-900/60">
        <div className="grid gap-0 lg:grid-cols-[1.05fr_0.95fr]">
          <div className="p-8 sm:p-10 lg:p-14">
            <div className="inline-flex items-center gap-2 rounded-full border border-emerald-400/30 bg-emerald-400/10 px-4 py-2 text-sm font-semibold text-emerald-200">
              <ShieldCheck className="h-4 w-4" />
              {copy.badge}
            </div>
            <h2 className="mt-6 max-w-3xl text-3xl font-black tracking-tight sm:text-4xl lg:text-5xl">
              {copy.title}
            </h2>
            <p className="mt-5 max-w-2xl text-base leading-8 text-slate-300 sm:text-lg">
              {copy.subtitle}
            </p>
            <div className="mt-8 flex flex-col gap-3 sm:flex-row">
              <Link
                href="/signup?source=operational-proof"
                className="inline-flex items-center justify-center gap-2 rounded-2xl bg-emerald-400 px-5 py-3 text-sm font-bold text-slate-950 transition hover:bg-emerald-300"
              >
                {copy.primary}
                <ArrowRight className={`h-4 w-4 ${isRtl ? 'rotate-180' : ''}`} />
              </Link>
              <Link
                href="/demo?source=operational-proof"
                className="inline-flex items-center justify-center rounded-2xl border border-white/15 px-5 py-3 text-sm font-bold text-white transition hover:bg-white/10"
              >
                {copy.secondary}
              </Link>
            </div>
          </div>

          <div className="border-t border-white/10 bg-white/[0.03] p-6 sm:p-8 lg:border-l lg:border-t-0">
            <div className="mb-5 flex items-center gap-3 text-emerald-200">
              <MonitorSmartphone className="h-5 w-5" />
              <span className="text-sm font-semibold uppercase tracking-[0.24em]">Leopardo stack</span>
            </div>
            <div className="grid gap-3">
              {copy.proofs.map((proof) => (
                <div key={proof.title} className="rounded-2xl border border-white/10 bg-white/[0.04] p-4">
                  <div className="flex items-start gap-3">
                    <CheckCircle2 className="mt-0.5 h-5 w-5 flex-none text-emerald-300" />
                    <div>
                      <div className="font-bold text-white">{proof.title}</div>
                      <div className="mt-1 text-sm leading-6 text-slate-300">{proof.detail}</div>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}
