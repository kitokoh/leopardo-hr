'use client';

import { useState } from 'react';
import Link from 'next/link';
import { Navbar, Footer, useScrollReveal } from '@/modules/vitrine';
import { motion } from 'framer-motion';
import {
  Bell,
  BriefcaseBusiness,
  Building2,
  CheckCircle,
  ChevronRight,
  Clock,
  Download,
  FileText,
  Globe,
  MapPin,
  Shield,
  Smartphone,
  Star,
  Users,
  Zap,
} from 'lucide-react';

/* ─────────────────────────────────────────
   Copy — 4 languages
───────────────────────────────────────── */
type Lang = 'fr' | 'en' | 'tr' | 'ar';
const langs: Lang[] = ['fr', 'en', 'tr', 'ar'];

interface LangCopy {
  badge: string;
  hero: string;
  heroSub: string;
  appsTitle: string;
  featuresTitle: string;
  downloadTitle: string;
  downloadSub: string;
  android: string;
  ios: string;
  soon: string;
}

const copy: Record<Lang, LangCopy> = {
  fr: {
    badge: 'Applications mobiles',
    hero: 'Gérez votre entreprise depuis votre poche',
    heroSub:
      'Trois applications Flutter dédiées — Employee, Manager et Platform Admin — pour couvrir chaque rôle de votre Mobile-First Company OS.',
    appsTitle: 'Trois apps, un écosystème',
    featuresTitle: 'Fonctionnalités clés',
    downloadTitle: 'Téléchargez les applications',
    downloadSub: 'Disponibles sur Android et iOS. Mises à jour automatiques via les stores.',
    android: 'Google Play',
    ios: 'App Store',
    soon: 'Bientôt disponible',
  },
  en: {
    badge: 'Mobile applications',
    hero: 'Manage your company from your pocket',
    heroSub:
      'Three dedicated Flutter apps — Employee, Manager and Platform Admin — to cover every role in your Mobile-First Company OS.',
    appsTitle: 'Three apps, one ecosystem',
    featuresTitle: 'Key features',
    downloadTitle: 'Download the apps',
    downloadSub: 'Available on Android and iOS. Automatic updates via the stores.',
    android: 'Google Play',
    ios: 'App Store',
    soon: 'Coming soon',
  },
  tr: {
    badge: 'Mobil uygulamalar',
    hero: "Şirketinizi cebinizden yönetin",
    heroSub:
      'Üç özel Flutter uygulaması — Employee, Manager ve Platform Admin — Mobile-First Company OS\'inizdeki her rolü kapsar.',
    appsTitle: 'Üç uygulama, bir ekosistem',
    featuresTitle: 'Temel özellikler',
    downloadTitle: 'Uygulamaları indirin',
    downloadSub: 'Android ve iOS\'ta mevcuttur. Mağazalar aracılığıyla otomatik güncellemeler.',
    android: 'Google Play',
    ios: 'App Store',
    soon: 'Çok yakında',
  },
  ar: {
    badge: 'التطبيقات المحمولة',
    hero: 'أدِر شركتك من جيبك',
    heroSub:
      'ثلاثة تطبيقات Flutter مخصصة — Employee وManager وPlatform Admin — لتغطية كل دور في نظام إدارة شركتك Mobile-First.',
    appsTitle: 'ثلاثة تطبيقات، منظومة واحدة',
    featuresTitle: 'الميزات الرئيسية',
    downloadTitle: 'نزّل التطبيقات',
    downloadSub: 'متوفرة على Android وiOS. تحديثات تلقائية عبر المتاجر.',
    android: 'Google Play',
    ios: 'App Store',
    soon: 'قريباً',
  },
};

/* ─────────────────────────────────────────
   App definitions
───────────────────────────────────────── */
const apps = [
  {
    id: 'employee',
    name: 'Leopardo Employee',
    subtitle: {
      fr: "L'app de l'employé",
      en: "The employee app",
      tr: "Çalışan uygulaması",
      ar: "تطبيق الموظف",
    },
    desc: {
      fr: "Pointage mobile avec géolocalisation, consultation des bulletins, demandes d'absence, avances salaires et notifications push en temps réel.",
      en: "Mobile attendance with geolocation, payslip access, leave requests, salary advances and real-time push notifications.",
      tr: "Coğrafi konumlu mobil devam takibi, maaş bordrosu erişimi, izin talepleri, maaş avansları ve gerçek zamanlı bildirimler.",
      ar: "تتبع الحضور بالموقع الجغرافي، عرض قسائم الراتب، طلبات الإجازات، السلف والإشعارات الفورية.",
    },
    color: 'emerald',
    gradient: 'from-emerald-500 to-teal-600',
    icon: Clock,
    features: [
      { icon: Clock, label: { fr: 'Pointage check-in/out', en: 'Check-in / check-out', tr: 'Giriş/Çıkış', ar: 'تسجيل الدخول/الخروج' } },
      { icon: MapPin, label: { fr: 'Géolocalisation GPS', en: 'GPS geolocation', tr: 'GPS konumu', ar: 'تحديد الموقع GPS' } },
      { icon: FileText, label: { fr: 'Bulletins de paie PDF', en: 'PDF payslips', tr: 'PDF bordro', ar: 'قسائم الراتب PDF' } },
      { icon: Bell, label: { fr: 'Notifications push FCM', en: 'FCM push notifications', tr: 'FCM bildirimleri', ar: 'إشعارات FCM' } },
      { icon: Zap, label: { fr: "Demandes d'absence", en: 'Leave requests', tr: 'İzin talepleri', ar: 'طلبات الإجازات' } },
      { icon: CheckCircle, label: { fr: 'Avances salaires', en: 'Salary advances', tr: 'Maaş avansları', ar: 'سلف الراتب' } },
    ],
    androidHref: '#android-employee',
    iosHref: '#ios-employee',
  },
  {
    id: 'manager',
    name: 'Leopardo Manager',
    subtitle: {
      fr: "L'app du manager",
      en: "The manager app",
      tr: "Yönetici uygulaması",
      ar: "تطبيق المدير",
    },
    desc: {
      fr: "Pilotage de l'équipe, gestion des horaires, tâches, validation des demandes, suivi des avances et paie simplifiée depuis le terrain.",
      en: "Team management, schedule management, tasks, request validation, advance tracking and simplified payroll from the field.",
      tr: "Ekip yönetimi, program yönetimi, görevler, talep onaylama, avans takibi ve saha üzerinden basitleştirilmiş bordro.",
      ar: "إدارة الفريق والجداول الزمنية والمهام والموافقات وتتبع السلف وكشف الرواتب المبسط من الميدان.",
    },
    color: 'blue',
    gradient: 'from-blue-500 to-indigo-600',
    icon: Users,
    features: [
      { icon: Users, label: { fr: 'Vue équipe temps réel', en: 'Real-time team view', tr: 'Gerçek zamanlı ekip', ar: 'الفريق في الوقت الحقيقي' } },
      { icon: Clock, label: { fr: 'Horaires & plannings', en: 'Schedules & planning', tr: 'Programlar', ar: 'الجداول الزمنية' } },
      { icon: CheckCircle, label: { fr: 'Approbations demandes', en: 'Request approvals', tr: 'Talep onayları', ar: 'الموافقات' } },
      { icon: BriefcaseBusiness, label: { fr: 'Tâches journalières', en: 'Daily tasks', tr: 'Günlük görevler', ar: 'المهام اليومية' } },
      { icon: FileText, label: { fr: 'Paie simplifiée', en: 'Simplified payroll', tr: 'Basit bordro', ar: 'رواتب مبسطة' } },
      { icon: Bell, label: { fr: 'Alertes équipe', en: 'Team alerts', tr: 'Ekip uyarıları', ar: 'تنبيهات الفريق' } },
    ],
    androidHref: '#android-manager',
    iosHref: '#ios-manager',
  },
  {
    id: 'platform-admin',
    name: 'Leopardo Platform Admin',
    subtitle: {
      fr: "L'app super-admin",
      en: "The super-admin app",
      tr: "Süper yönetici uygulaması",
      ar: "تطبيق المشرف العام",
    },
    desc: {
      fr: "Création et gestion multi-tenant, supervision des entreprises clientes, authentification 2FA renforcée et provisioning de nouveaux comptes.",
      en: "Multi-tenant creation and management, client company oversight, enhanced 2FA authentication and new account provisioning.",
      tr: "Çok kiracılı oluşturma ve yönetim, müşteri şirket gözetimi, gelişmiş 2FA kimlik doğrulaması ve yeni hesap provizyon.",
      ar: "إنشاء وإدارة متعدد المستأجرين، الإشراف على الشركات العميلة، المصادقة الثنائية المحسّنة وتوفير الحسابات.",
    },
    color: 'amber',
    gradient: 'from-amber-500 to-orange-600',
    icon: Building2,
    features: [
      { icon: Building2, label: { fr: 'Création tenants', en: 'Tenant creation', tr: 'Kiracı oluşturma', ar: 'إنشاء المستأجرين' } },
      { icon: Shield, label: { fr: 'Auth 2FA renforcée', en: 'Enhanced 2FA auth', tr: 'Gelişmiş 2FA', ar: 'مصادقة ثنائية' } },
      { icon: Users, label: { fr: 'Supervision clients', en: 'Client supervision', tr: 'Müşteri denetimi', ar: 'إشراف العملاء' } },
      { icon: Globe, label: { fr: 'Multi-tenant isolation', en: 'Multi-tenant isolation', tr: 'Çok kiracılı yalıtım', ar: 'عزل متعدد المستأجرين' } },
      { icon: Zap, label: { fr: 'Provisioning rapide', en: 'Fast provisioning', tr: 'Hızlı provizyon', ar: 'توفير سريع' } },
      { icon: Bell, label: { fr: 'Alertes plateforme', en: 'Platform alerts', tr: 'Platform uyarıları', ar: 'تنبيهات المنصة' } },
    ],
    androidHref: '#android-platform-admin',
    iosHref: '#ios-platform-admin',
  },
];

const colorMap: Record<string, { bg: string; icon: string; border: string; badge: string }> = {
  emerald: {
    bg: 'bg-emerald-50 dark:bg-emerald-900/20',
    icon: 'text-emerald-600 dark:text-emerald-400',
    border: 'border-emerald-200 dark:border-emerald-800',
    badge: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
  },
  blue: {
    bg: 'bg-blue-50 dark:bg-blue-900/20',
    icon: 'text-blue-600 dark:text-blue-400',
    border: 'border-blue-200 dark:border-blue-800',
    badge: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
  },
  amber: {
    bg: 'bg-amber-50 dark:bg-amber-900/20',
    icon: 'text-amber-600 dark:text-amber-400',
    border: 'border-amber-200 dark:border-amber-800',
    badge: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
  },
};

/* ─────────────────────────────────────────
   Page component
───────────────────────────────────────── */
export default function MobilePage() {
  const [isDark, setIsDark] = useState(false);
  const [lang, setLang] = useState<Lang>('fr');
  useScrollReveal();

  const t = copy[lang];
  const isRtl = lang === 'ar';

  return (
    <div
      className={`min-h-screen transition-colors duration-500 ${isDark ? 'dark bg-slate-950' : 'bg-white'}`}
      dir={isRtl ? 'rtl' : 'ltr'}
    >
      <Navbar isDark={isDark} onToggleDark={() => setIsDark(!isDark)} />

      {/* ── Hero ───────────────────────────────── */}
      <section className="pt-32 pb-20 px-4">
        <div className="max-w-4xl mx-auto text-center">
          {/* Lang switcher */}
          <div className="flex justify-center gap-2 mb-8">
            {langs.map((l) => (
              <button
                key={l}
                onClick={() => setLang(l)}
                className={`px-3 py-1 rounded-full text-xs font-semibold transition-colors ${
                  lang === l
                    ? 'bg-slate-900 dark:bg-white text-white dark:text-slate-900'
                    : 'bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-700'
                }`}
              >
                {l.toUpperCase()}
              </button>
            ))}
          </div>

          <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-slate-500/[0.08] border border-slate-500/15 text-slate-700 dark:text-slate-300 text-sm font-semibold mb-6">
            <Smartphone className="w-3.5 h-3.5" />
            {t.badge}
          </div>

          <motion.h1
            key={lang + '-hero'}
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            className="text-4xl sm:text-5xl lg:text-6xl font-black text-slate-900 dark:text-white tracking-tight mb-6"
          >
            {t.hero}
          </motion.h1>

          <motion.p
            key={lang + '-sub'}
            initial={{ opacity: 0, y: 10 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.1 }}
            className="text-lg text-slate-500 dark:text-slate-400 max-w-2xl mx-auto mb-8"
          >
            {t.heroSub}
          </motion.p>

          {/* Stats */}
          <div className="flex flex-wrap justify-center gap-6">
            {[
              { value: '3', label: { fr: 'Applications', en: 'Apps', tr: 'Uygulama', ar: 'تطبيقات' } },
              { value: 'Flutter', label: { fr: 'Framework', en: 'Framework', tr: 'Framework', ar: 'إطار' } },
              { value: '4', label: { fr: 'Langues', en: 'Languages', tr: 'Dil', ar: 'لغات' } },
              { value: 'FCM', label: { fr: 'Notifications', en: 'Notifications', tr: 'Bildirimler', ar: 'إشعارات' } },
            ].map((stat) => (
              <div key={stat.value} className="text-center">
                <p className="text-2xl font-black text-slate-900 dark:text-white">{stat.value}</p>
                <p className="text-xs text-slate-500 dark:text-slate-400">{stat.label[lang]}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ── Three apps ─────────────────────────── */}
      <section className="py-16 px-4 bg-slate-50 dark:bg-slate-900 border-y border-slate-200 dark:border-slate-800">
        <div className="max-w-6xl mx-auto">
          <h2 className="text-2xl font-bold text-slate-900 dark:text-white text-center mb-12">{t.appsTitle}</h2>

          <div className="space-y-16">
            {apps.map((app, idx) => {
              const c = colorMap[app.color];
              return (
                <motion.div
                  key={app.id}
                  id={app.id}
                  initial={{ opacity: 0, y: 30 }}
                  whileInView={{ opacity: 1, y: 0 }}
                  transition={{ delay: 0.05 }}
                  viewport={{ once: true }}
                  className={`flex flex-col ${idx % 2 === 1 ? 'lg:flex-row-reverse' : 'lg:flex-row'} gap-8 items-center`}
                >
                  {/* App card visual */}
                  <div className="w-full lg:w-2/5 flex-shrink-0">
                    <div className={`rounded-3xl border ${c.border} ${c.bg} p-8 flex flex-col items-center text-center shadow-sm`}>
                      <div className={`w-16 h-16 rounded-2xl bg-gradient-to-br ${app.gradient} flex items-center justify-center mb-4 shadow-lg`}>
                        <app.icon className="w-8 h-8 text-white" />
                      </div>
                      <h3 className="font-black text-xl text-slate-900 dark:text-white mb-1">{app.name}</h3>
                      <p className={`text-xs font-semibold px-3 py-1 rounded-full ${c.badge} mb-4`}>
                        {app.subtitle[lang]}
                      </p>
                      {/* Fake phone mockup */}
                      <div className="w-48 h-80 rounded-3xl border-4 border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 shadow-xl overflow-hidden mx-auto">
                        <div className={`h-12 bg-gradient-to-r ${app.gradient} flex items-center px-3 gap-2`}>
                          <div className="w-6 h-6 rounded-full bg-white/20" />
                          <div className="flex-1 h-2 bg-white/30 rounded" />
                        </div>
                        <div className="p-3 space-y-2">
                          {[1, 2, 3, 4].map((i) => (
                            <div key={i} className="flex items-center gap-2">
                              <div className={`w-6 h-6 rounded ${c.bg} flex-shrink-0`} />
                              <div className="flex-1 space-y-1">
                                <div className="h-2 bg-slate-100 dark:bg-slate-800 rounded w-full" />
                                <div className="h-1.5 bg-slate-100 dark:bg-slate-800 rounded w-2/3" />
                              </div>
                            </div>
                          ))}
                        </div>
                      </div>
                    </div>
                  </div>

                  {/* Description + features */}
                  <div className="flex-1">
                    <p className="text-slate-600 dark:text-slate-400 text-base leading-relaxed mb-6">
                      {app.desc[lang]}
                    </p>
                    <h4 className="font-bold text-slate-900 dark:text-white mb-4 text-sm uppercase tracking-wide">
                      {t.featuresTitle}
                    </h4>
                    <div className="grid sm:grid-cols-2 gap-3">
                      {app.features.map((feat) => (
                        <div key={feat.label.en} className="flex items-center gap-3">
                          <div className={`w-8 h-8 rounded-lg ${c.bg} flex items-center justify-center flex-shrink-0`}>
                            <feat.icon className={`w-4 h-4 ${c.icon}`} />
                          </div>
                          <span className="text-sm text-slate-700 dark:text-slate-300">{feat.label[lang]}</span>
                        </div>
                      ))}
                    </div>

                    {/* Download buttons */}
                    <div className="mt-6 flex flex-wrap gap-3">
                      <Link
                        href={app.androidHref}
                        className={`inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-semibold text-sm bg-gradient-to-r ${app.gradient} text-white opacity-75 cursor-not-allowed`}
                        aria-label={`${t.android} — ${t.soon}`}
                      >
                        <Download className="w-4 h-4" />
                        {t.android}
                        <span className="ml-1 text-xs opacity-80">({t.soon})</span>
                      </Link>
                      <Link
                        href={app.iosHref}
                        className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-semibold text-sm border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 opacity-75 cursor-not-allowed"
                        aria-label={`${t.ios} — ${t.soon}`}
                      >
                        <Smartphone className="w-4 h-4" />
                        {t.ios}
                        <span className="ml-1 text-xs opacity-80">({t.soon})</span>
                      </Link>
                    </div>
                  </div>
                </motion.div>
              );
            })}
          </div>
        </div>
      </section>

      {/* ── Download CTA ───────────────────────── */}
      <section id="download" className="py-20 px-4">
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          whileInView={{ opacity: 1, y: 0 }}
          viewport={{ once: true }}
          className="max-w-3xl mx-auto text-center"
        >
          <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-emerald-500/[0.08] border border-emerald-500/15 text-emerald-700 dark:text-emerald-400 text-sm font-semibold mb-6">
            <Star className="w-3.5 h-3.5" />
            Mobile-First Company OS
          </div>
          <h2 className="text-3xl sm:text-4xl font-black text-slate-900 dark:text-white mb-4">{t.downloadTitle}</h2>
          <p className="text-slate-500 dark:text-slate-400 mb-8">{t.downloadSub}</p>

          <div className="flex flex-wrap justify-center gap-4">
            {apps.map((app) => {
              const c = colorMap[app.color];
              return (
                <div
                  key={app.id}
                  className={`flex items-center gap-3 px-5 py-3 rounded-xl border ${c.border} ${c.bg}`}
                >
                  <app.icon className={`w-5 h-5 ${c.icon}`} />
                  <div className="text-start">
                    <p className="text-sm font-bold text-slate-900 dark:text-white">{app.name}</p>
                    <p className="text-xs text-slate-500 dark:text-slate-400">{t.soon}</p>
                  </div>
                </div>
              );
            })}
          </div>

          <p className="mt-8 text-sm text-slate-400 dark:text-slate-500">
            {/* Placeholder note */}
            {/* TODO: replace placeholders with real store links */}
            En attendant, demandez une démo ou consultez notre{' '}
            <Link href="/docs#mobile-install" className="text-emerald-600 dark:text-emerald-400 hover:underline">
              guide d'installation
            </Link>
            .
          </p>

          <div className="mt-6">
            <Link
              href="/demo"
              className="inline-flex items-center gap-2 px-8 py-3.5 rounded-xl font-semibold bg-slate-900 dark:bg-white text-white dark:text-slate-900 hover:opacity-90 transition-opacity"
            >
              Demander une démo
              <ChevronRight className="w-4 h-4" />
            </Link>
          </div>
        </motion.div>
      </section>

      <Footer />
    </div>
  );
}
