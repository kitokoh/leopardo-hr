'use client';

import { useState } from 'react';
import Link from 'next/link';
import { motion } from 'framer-motion';
import {
  ArrowRight,
  Check,
  ChevronRight,
  Download,
  Fingerprint,
  Globe2,
  HardDrive,
  Laptop,
  Monitor,
  Shield,
  Smartphone,
  Wifi,
  WifiOff,
  Zap,
} from 'lucide-react';
import { Navbar, Footer, useScrollReveal } from '@/modules/vitrine';
import { useVitrineLocale } from '@/modules/vitrine/lib/vitrine-locale';

type FeatureCard = {
  icon: React.ReactNode;
  title: string;
  description: string;
};

type RequirementItem = {
  label: string;
  value: string;
};

type AppLocale = 'fr' | 'en' | 'tr' | 'ar';
type MobilePlatform = 'android' | 'ios';
type MobileAppSlug = 'employee' | 'manager' | 'platform-admin';

type MobileDownloadTarget = {
  href: string;
  isFallback: boolean;
};

const mobileDownloadEnv: Record<
  MobileAppSlug,
  Record<MobilePlatform, string | undefined>
> = {
  employee: {
    android: process.env.NEXT_PUBLIC_LEOPARDO_EMPLOYEE_ANDROID_URL,
    ios: process.env.NEXT_PUBLIC_LEOPARDO_EMPLOYEE_IOS_URL,
  },
  manager: {
    android: process.env.NEXT_PUBLIC_LEOPARDO_MANAGER_ANDROID_URL,
    ios: process.env.NEXT_PUBLIC_LEOPARDO_MANAGER_IOS_URL,
  },
  'platform-admin': {
    android: process.env.NEXT_PUBLIC_LEOPARDO_PLATFORM_ADMIN_ANDROID_URL,
    ios: process.env.NEXT_PUBLIC_LEOPARDO_PLATFORM_ADMIN_IOS_URL,
  },
};

function mobileDownloadTarget(
  slug: MobileAppSlug,
  platform: MobilePlatform,
): MobileDownloadTarget {
  const configured = mobileDownloadEnv[slug][platform]?.trim();

  if (configured) {
    return { href: configured, isFallback: false };
  }

  return {
    href: `/signup?source=download_${slug}_${platform}`,
    isFallback: true,
  };
}

function testerFallbackLabel(locale: AppLocale): string {
  switch (locale) {
    case 'en':
      return 'Join the tester list';
    case 'tr':
      return 'Test listesine katil';
    case 'ar':
      return 'انضم إلى قائمة الاختبار';
    default:
      return 'Rejoindre les testeurs';
  }
}

const copy: Record<AppLocale, {
  badge: string;
  headline: string;
  subheadline: string;
  downloadCta: string;
  version: string;
  size: string;
  requirement: string;
  features: FeatureCard[];
  requirements: RequirementItem[];
  howItWorks: {
    title: string;
    steps: Array<{ step: string; title: string; description: string }>;
  };
  faq: Array<{ question: string; answer: string }>;
}> = {
  fr: {
    badge: 'Application Desktop',
    headline: 'Leopardo pour Windows',
    subheadline: 'Le client lourd pour synchroniser vos pointeuses ZKTeco, gerer le mode hors-ligne, et superviser vos sites depuis le bureau.',
    downloadCta: 'Telecharger pour Windows',
    version: 'v2.1.0',
    size: '68 Mo',
    requirement: 'Windows 10+ (64-bit)',
    features: [
      { icon: <Fingerprint className="w-6 h-6" />, title: 'Synchronisation ZKTeco', description: 'Connexion directe aux bornes biometriques ZKTeco. Push/pull des pointages en temps reel via TCP/IP ou USB.' },
      { icon: <WifiOff className="w-6 h-6" />, title: 'Mode hors-ligne', description: 'Continuez a travailler sans internet. Les pointages sont stockes localement et synchronises automatiquement au retour du reseau.' },
      { icon: <Monitor className="w-6 h-6" />, title: 'Supervision multi-sites', description: "Surveillez plusieurs sites depuis un seul poste. Alertes en temps reel pour les anomalies d'acces." },
      { icon: <Shield className="w-6 h-6" />, title: 'Securise et chiffre', description: "Communication chiffree TLS 1.3. Les donnees biometriques restent sur le terminal, seuls les hash d'identification transitent." },
      { icon: <Zap className="w-6 h-6" />, title: 'Installation rapide', description: "Installateur MSI silencieux. Deploiement GPO/SCCM possible pour les grandes organisations." },
      { icon: <HardDrive className="w-6 h-6" />, title: 'Logs et audit', description: "Journal complet des operations. Export CSV pour conformite RGPD et audit interne." },
    ],
    requirements: [
      { label: 'OS', value: 'Windows 10 / 11 (64-bit)' },
      { label: 'RAM', value: '4 Go minimum' },
      { label: 'Disque', value: '200 Mo espace libre' },
      { label: 'Reseau', value: 'LAN pour ZKTeco, Internet pour sync cloud' },
      { label: '.NET', value: '.NET 8 Runtime (inclus dans l\'installateur)' },
    ],
    howItWorks: {
      title: 'Comment ca marche',
      steps: [
        { step: '01', title: 'Installez', description: 'Telechargez et lancez l\'installateur. Configuration automatique en 2 minutes.' },
        { step: '02', title: 'Connectez', description: 'Entrez l\'adresse IP de vos bornes ZKTeco. Detection automatique sur le reseau local.' },
        { step: '03', title: 'Synchronisez', description: 'Les pointages remontent automatiquement vers Leopardo RH dans le cloud. Temps reel ou par batch.' },
      ],
    },
    faq: [
      { question: 'Le client Windows est-il gratuit ?', answer: 'Oui, le client desktop est inclus dans tous les plans Leopardo RH, y compris Starter.' },
      { question: 'Quelles bornes sont supportees ?', answer: 'Toutes les bornes ZKTeco (iClock, SpeedFace, ProFace, uFace). Support etendu pour d\'autres fabricants prevu en 2026.' },
      { question: 'Peut-on deployer via GPO ?', answer: 'Oui, l\'installateur MSI supporte le deploiement silencieux. Documentation disponible dans le guide d\'administration.' },
    ],
  },
  en: {
    badge: 'Desktop Application',
    headline: 'Leopardo for Windows',
    subheadline: 'The desktop client to sync your ZKTeco terminals, manage offline mode, and supervise your sites from your workstation.',
    downloadCta: 'Download for Windows',
    version: 'v2.1.0',
    size: '68 MB',
    requirement: 'Windows 10+ (64-bit)',
    features: [
      { icon: <Fingerprint className="w-6 h-6" />, title: 'ZKTeco Synchronization', description: 'Direct connection to ZKTeco biometric terminals. Push/pull attendance in real time via TCP/IP or USB.' },
      { icon: <WifiOff className="w-6 h-6" />, title: 'Offline Mode', description: 'Keep working without internet. Attendance data is stored locally and synced automatically when connectivity returns.' },
      { icon: <Monitor className="w-6 h-6" />, title: 'Multi-Site Supervision', description: 'Monitor multiple sites from a single workstation. Real-time alerts for access anomalies.' },
      { icon: <Shield className="w-6 h-6" />, title: 'Encrypted & Secure', description: 'TLS 1.3 encrypted communication. Biometric data stays on the terminal — only identification hashes transit.' },
      { icon: <Zap className="w-6 h-6" />, title: 'Quick Setup', description: 'Silent MSI installer. GPO/SCCM deployment for enterprise-scale rollouts.' },
      { icon: <HardDrive className="w-6 h-6" />, title: 'Logs & Audit', description: 'Complete operation journal. CSV export for GDPR compliance and internal audit.' },
    ],
    requirements: [
      { label: 'OS', value: 'Windows 10 / 11 (64-bit)' },
      { label: 'RAM', value: '4 GB minimum' },
      { label: 'Disk', value: '200 MB free space' },
      { label: 'Network', value: 'LAN for ZKTeco, Internet for cloud sync' },
      { label: '.NET', value: '.NET 8 Runtime (bundled with installer)' },
    ],
    howItWorks: {
      title: 'How it works',
      steps: [
        { step: '01', title: 'Install', description: 'Download and run the installer. Auto-configuration in 2 minutes.' },
        { step: '02', title: 'Connect', description: 'Enter your ZKTeco terminal IP addresses. Auto-detection on local network.' },
        { step: '03', title: 'Sync', description: 'Attendance data flows automatically to Leopardo RH in the cloud. Real-time or batch mode.' },
      ],
    },
    faq: [
      { question: 'Is the Windows client free?', answer: 'Yes, the desktop client is included in all Leopardo RH plans, including Starter.' },
      { question: 'Which terminals are supported?', answer: 'All ZKTeco terminals (iClock, SpeedFace, ProFace, uFace). Extended support for other manufacturers planned for 2026.' },
      { question: 'Can it be deployed via GPO?', answer: 'Yes, the MSI installer supports silent deployment. Documentation available in the admin guide.' },
    ],
  },
  tr: {
    badge: 'Masaustu Uygulamasi',
    headline: 'Windows icin Leopardo',
    subheadline: 'ZKTeco terminallerinizi senkronize etmek, cevrimdisi modu yonetmek ve is istasyonunuzdan sitelerinizi denetlemek icin masaustu istemcisi.',
    downloadCta: 'Windows icin indir',
    version: 'v2.1.0',
    size: '68 MB',
    requirement: 'Windows 10+ (64-bit)',
    features: [
      { icon: <Fingerprint className="w-6 h-6" />, title: 'ZKTeco Senkronizasyonu', description: 'ZKTeco biyometrik terminallere dogrudan baglanti. TCP/IP veya USB uzerinden gercek zamanli yoklama.' },
      { icon: <WifiOff className="w-6 h-6" />, title: 'Cevrimdisi Mod', description: 'Internet olmadan calismaya devam edin. Yoklama verileri yerel olarak saklanir ve otomatik senkronize edilir.' },
      { icon: <Monitor className="w-6 h-6" />, title: 'Coklu Site Denetimi', description: 'Tek bir is istasyonundan birden fazla siteyi izleyin. Erisim anomalileri icin gercek zamanli uyarilar.' },
      { icon: <Shield className="w-6 h-6" />, title: 'Sifreli ve Guvenli', description: 'TLS 1.3 sifreli iletisim. Biyometrik veriler terminalde kalir.' },
      { icon: <Zap className="w-6 h-6" />, title: 'Hizli Kurulum', description: 'Sessiz MSI yukleyici. Kurumsal olcekli dagitimlar icin GPO/SCCM destegi.' },
      { icon: <HardDrive className="w-6 h-6" />, title: 'Gunlukler ve Denetim', description: 'Eksiksiz islem gunlugu. KVKK uyumlulugu ve ic denetim icin CSV aktarimi.' },
    ],
    requirements: [
      { label: 'OS', value: 'Windows 10 / 11 (64-bit)' },
      { label: 'RAM', value: 'Minimum 4 GB' },
      { label: 'Disk', value: '200 MB bos alan' },
      { label: 'Ag', value: 'ZKTeco icin LAN, bulut senkronizasyonu icin internet' },
      { label: '.NET', value: '.NET 8 Runtime (yukleyiciye dahil)' },
    ],
    howItWorks: {
      title: 'Nasil calisir',
      steps: [
        { step: '01', title: 'Kurun', description: 'Yukleyiciyi indirin ve calistirin. 2 dakikada otomatik yapilandirma.' },
        { step: '02', title: 'Baglanin', description: 'ZKTeco terminal IP adreslerini girin. Yerel agda otomatik algilama.' },
        { step: '03', title: 'Senkronize edin', description: 'Yoklama verileri otomatik olarak buluttaki Leopardo RH\'ye akar.' },
      ],
    },
    faq: [
      { question: 'Windows istemcisi ucretsiz mi?', answer: 'Evet, masaustu istemcisi Starter dahil tum Leopardo RH planlarinda yer alir.' },
      { question: 'Hangi terminaller destekleniyor?', answer: 'Tum ZKTeco terminalleri (iClock, SpeedFace, ProFace, uFace). Diger ureticiler icin genisletilmis destek 2026\'da planlanmaktadir.' },
      { question: 'GPO ile dagitilabilir mi?', answer: 'Evet, MSI yukleyici sessiz dagitimi destekler.' },
    ],
  },
  ar: {
    badge: 'تطبيق سطح المكتب',
    headline: 'ليوباردو لويندوز',
    subheadline: 'عميل سطح المكتب لمزامنة أجهزة ZKTeco وإدارة وضع عدم الاتصال والإشراف على مواقعك.',
    downloadCta: 'تحميل لويندوز',
    version: 'v2.1.0',
    size: '68 ميغابايت',
    requirement: 'ويندوز 10+ (64-بت)',
    features: [
      { icon: <Fingerprint className="w-6 h-6" />, title: 'مزامنة ZKTeco', description: 'اتصال مباشر بأجهزة ZKTeco البيومترية. دفع/سحب الحضور في الوقت الفعلي.' },
      { icon: <WifiOff className="w-6 h-6" />, title: 'وضع عدم الاتصال', description: 'استمر في العمل بدون إنترنت. يتم تخزين بيانات الحضور محلياً ومزامنتها تلقائياً.' },
      { icon: <Monitor className="w-6 h-6" />, title: 'إشراف متعدد المواقع', description: 'راقب عدة مواقع من محطة عمل واحدة. تنبيهات فورية لحالات الشذوذ.' },
      { icon: <Shield className="w-6 h-6" />, title: 'مشفر وآمن', description: 'اتصال مشفر TLS 1.3. البيانات البيومترية تبقى على الجهاز.' },
      { icon: <Zap className="w-6 h-6" />, title: 'إعداد سريع', description: 'مثبت MSI صامت. نشر GPO/SCCM للمؤسسات الكبيرة.' },
      { icon: <HardDrive className="w-6 h-6" />, title: 'سجلات ومراجعة', description: 'سجل عمليات كامل. تصدير CSV للامتثال والمراجعة الداخلية.' },
    ],
    requirements: [
      { label: 'نظام التشغيل', value: 'ويندوز 10 / 11 (64-بت)' },
      { label: 'الذاكرة', value: '4 جيجابايت كحد أدنى' },
      { label: 'القرص', value: '200 ميغابايت مساحة حرة' },
      { label: 'الشبكة', value: 'LAN لـ ZKTeco، إنترنت للمزامنة السحابية' },
      { label: '.NET', value: '.NET 8 Runtime (مضمن مع المثبت)' },
    ],
    howItWorks: {
      title: 'كيف يعمل',
      steps: [
        { step: '01', title: 'ثبّت', description: 'حمّل وشغّل المثبت. إعداد تلقائي في دقيقتين.' },
        { step: '02', title: 'اتصل', description: 'أدخل عناوين IP لأجهزة ZKTeco. كشف تلقائي على الشبكة المحلية.' },
        { step: '03', title: 'زامن', description: 'تتدفق بيانات الحضور تلقائياً إلى Leopardo RH في السحابة.' },
      ],
    },
    faq: [
      { question: 'هل عميل ويندوز مجاني؟', answer: 'نعم، عميل سطح المكتب مضمن في جميع خطط Leopardo RH بما في ذلك Starter.' },
      { question: 'ما الأجهزة المدعومة؟', answer: 'جميع أجهزة ZKTeco (iClock, SpeedFace, ProFace, uFace).' },
      { question: 'هل يمكن نشره عبر GPO؟', answer: 'نعم، مثبت MSI يدعم النشر الصامت.' },
    ],
  },
};

type MobileApp = {
  slug: MobileAppSlug;
  name: string;
  description: string;
  androidLabel: string;
  iosLabel: string;
};

const mobileAppsData: Record<AppLocale, {
  sectionTitle: string;
  sectionSubtitle: string;
  apps: MobileApp[];
}> = {
  fr: {
    sectionTitle: 'Applications mobiles',
    sectionSubtitle: 'Pointage, gestion RH et supervision multi-tenant directement depuis votre smartphone.',
    apps: [
      {
        slug: 'employee',
        name: 'Leopardo Employee',
        description: 'Pointage mobile, demandes de conge, fiche de paie et notifications RH pour les collaborateurs.',        androidLabel: 'Bientot sur Google Play',
        iosLabel: "Bientot sur l'App Store",
      },
      {
        slug: 'manager',
        name: 'Leopardo Manager',
        description: 'Gestion des equipes, planification des horaires, approbation des demandes et suivi des presences.',        androidLabel: 'Bientot sur Google Play',
        iosLabel: "Bientot sur l'App Store",
      },
      {
        slug: 'platform-admin',
        name: 'Leopardo Platform Admin',
        description: 'Supervision multi-tenant, configuration globale et controle des tenants depuis mobile.',        androidLabel: 'Bientot sur Google Play',
        iosLabel: "Bientot sur l'App Store",
      },
    ],
  },
  en: {
    sectionTitle: 'Mobile Apps',
    sectionSubtitle: 'Attendance, HR management and multi-tenant supervision directly from your smartphone.',
    apps: [
      {
        slug: 'employee',
        name: 'Leopardo Employee',
        description: 'Mobile attendance, leave requests, payslip access and HR notifications for employees.',        androidLabel: 'Coming soon on Google Play',
        iosLabel: 'Coming soon on App Store',
      },
      {
        slug: 'manager',
        name: 'Leopardo Manager',
        description: 'Team management, schedule planning, approval workflows and attendance monitoring.',        androidLabel: 'Coming soon on Google Play',
        iosLabel: 'Coming soon on App Store',
      },
      {
        slug: 'platform-admin',
        name: 'Leopardo Platform Admin',
        description: 'Multi-tenant supervision, global configuration and tenant controls from your mobile.',        androidLabel: 'Coming soon on Google Play',
        iosLabel: 'Coming soon on App Store',
      },
    ],
  },
  tr: {
    sectionTitle: 'Mobil Uygulamalar',
    sectionSubtitle: 'Akilli telefonunuzdan devam takibi, IK yonetimi ve cok kiracili denetim.',
    apps: [
      {
        slug: 'employee',
        name: 'Leopardo Employee',
        description: 'Mobil devam takibi, izin talepleri, odeme belgeleri ve calisan bildirimleri.',        androidLabel: "Google Play'de Yakin Zamanda",
        iosLabel: "App Store'da Yakin Zamanda",
      },
      {
        slug: 'manager',
        name: 'Leopardo Manager',
        description: 'Takim yonetimi, program planlama, onay surecleri ve devam izleme.',        androidLabel: "Google Play'de Yakin Zamanda",
        iosLabel: "App Store'da Yakin Zamanda",
      },
      {
        slug: 'platform-admin',
        name: 'Leopardo Platform Admin',
        description: 'Cok kiracili denetim, global yapilandirma ve mobilden kira kontrolleri.',        androidLabel: "Google Play'de Yakin Zamanda",
        iosLabel: "App Store'da Yakin Zamanda",
      },
    ],
  },
  ar: {
    sectionTitle: 'تطبيقات الجوال',
    sectionSubtitle: 'الحضور وإدارة الموارد البشرية والإشراف متعدد المستأجرين مباشرة من هاتفك الذكي.',
    apps: [
      {
        slug: 'employee',
        name: 'Leopardo Employee',
        description: 'تسجيل الحضور عبر الجوال وطلبات الإجازة وقسائم الرواتب وإشعارات الموظفين.',        androidLabel: 'قريبًا على Google Play',
        iosLabel: 'قريبًا على App Store',
      },
      {
        slug: 'manager',
        name: 'Leopardo Manager',
        description: 'إدارة الفريق وجدولة المواعيد وسير عمل الموافقات ومراقبة الحضور.',        androidLabel: 'قريبًا على Google Play',
        iosLabel: 'قريبًا على App Store',
      },
      {
        slug: 'platform-admin',
        name: 'Leopardo Platform Admin',
        description: 'الإشراف متعدد المستأجرين والتكوين العام والتحكم في المستأجرين من الجوال.',        androidLabel: 'قريبًا على Google Play',
        iosLabel: 'قريبًا على App Store',
      },
    ],
  },
};

const platformLabels: Record<AppLocale, Array<{ platform: string; title: string; description: string; href: string }>> = {
  fr: [
    { platform: 'Windows', title: 'Leopardo Desktop Windows', description: 'Synchronisation ZKTeco, mode hors-ligne et supervision site.', href: '/contact?topic=download-windows' },
    { platform: 'macOS', title: 'Leopardo Desktop macOS', description: 'Client bureau pour les equipes terrain et administrateurs.', href: '/contact?topic=download-macos' },
    { platform: 'Android', title: 'Leopardo Mobile Android', description: 'Pointage mobile, demandes RH et notifications employe.', href: '/download#mobile-apps'},
    { platform: 'iPhone', title: 'Leopardo Mobile iOS', description: 'Experience mobile managers et employes sur iPhone.', href: '/download#mobile-apps'},
  ],
  en: [
    { platform: 'Windows', title: 'Leopardo Desktop Windows', description: 'ZKTeco sync, offline mode and site supervision.', href: '/contact?topic=download-windows' },
    { platform: 'macOS', title: 'Leopardo Desktop macOS', description: 'Desktop client for field teams and administrators.', href: '/contact?topic=download-macos' },
    { platform: 'Android', title: 'Leopardo Mobile Android', description: 'Mobile attendance, HR requests and employee notifications.', href: '/download#mobile-apps'},
    { platform: 'iPhone', title: 'Leopardo Mobile iOS', description: 'Mobile experience for managers and employees on iPhone.', href: '/download#mobile-apps'},
  ],
  tr: [
    { platform: 'Windows', title: 'Leopardo Desktop Windows', description: 'ZKTeco senkronizasyonu, cevrimdisi mod ve saha denetimi.', href: '/contact?topic=download-windows' },
    { platform: 'macOS', title: 'Leopardo Desktop macOS', description: 'Saha ekipleri ve yoneticiler icin masaustu istemcisi.', href: '/contact?topic=download-macos' },
    { platform: 'Android', title: 'Leopardo Mobile Android', description: 'Mobil yoklama, IK talepleri ve calisan bildirimleri.', href: '/download#mobile-apps'},
    { platform: 'iPhone', title: 'Leopardo Mobile iOS', description: 'iPhone uzerinde yonetici ve calisan deneyimi.', href: '/download#mobile-apps'},
  ],
  ar: [
    { platform: 'Windows', title: 'Leopardo Desktop Windows', description: 'مزامنة ZKTeco ووضع عدم الاتصال وإشراف المواقع.', href: '/contact?topic=download-windows' },
    { platform: 'macOS', title: 'Leopardo Desktop macOS', description: 'عميل مكتبي لفرق الميدان والمسؤولين.', href: '/contact?topic=download-macos' },
    { platform: 'Android', title: 'Leopardo Mobile Android', description: 'الحضور عبر الهاتف وطلبات الموارد البشرية والإشعارات.', href: '/download#mobile-apps'},
    { platform: 'iPhone', title: 'Leopardo Mobile iOS', description: 'تجربة موبايل للمديرين والموظفين على iPhone.', href: '/download#mobile-apps'},
  ],
};

export default function DownloadPage() {
  const [isDark, setIsDark] = useState(false);
  useScrollReveal();
  const { locale } = useVitrineLocale();
  const c = copy[locale as AppLocale] ?? copy.fr;
  const platforms = platformLabels[locale as AppLocale] ?? platformLabels.fr;
  const mobileApps = mobileAppsData[locale as AppLocale] ?? mobileAppsData.fr;

  return (
    <div className={`min-h-screen transition-colors duration-500 ${isDark ? 'dark bg-slate-950' : 'bg-white'}`}>
      <Navbar isDark={isDark} onToggleDark={() => setIsDark(!isDark)} />

      <section className="relative pt-32 pb-20 overflow-hidden">
        <div className="absolute inset-0 bg-gradient-to-br from-slate-50 via-emerald-50/30 to-cyan-50/20 dark:from-slate-950 dark:via-emerald-950/20 dark:to-cyan-950/10" />
        <div className="absolute top-20 right-0 w-96 h-96 rounded-full bg-emerald-400/5 blur-3xl" />

        <div className="relative max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.5 }}
          >
            <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-emerald-500/[0.08] border border-emerald-500/15 text-emerald-700 dark:text-emerald-400 text-sm font-semibold mb-6">
              <Laptop className="w-4 h-4" />
              {c.badge}
            </div>

            <h1 className="text-4xl sm:text-5xl lg:text-6xl font-black text-slate-900 dark:text-white mb-6 tracking-tight">
              {c.headline}
            </h1>
            <p className="text-lg sm:text-xl text-slate-500 dark:text-slate-400 max-w-2xl mx-auto mb-10">
              {c.subheadline}
            </p>

            <div className="flex flex-col sm:flex-row items-center justify-center gap-4">
              <Link
                href="/contact?topic=download"
                className="inline-flex items-center gap-3 px-8 py-4 bg-gradient-to-r from-emerald-500 to-emerald-600 text-white text-lg font-bold rounded-2xl hover:from-emerald-600 hover:to-emerald-700 transition-all shadow-xl shadow-emerald-500/20 hover:shadow-emerald-500/40 hover:scale-[1.02] active:scale-[0.98]"
              >
                <Download className="w-5 h-5" />
                {c.downloadCta}
              </Link>
              <div className="flex items-center gap-4 text-sm text-slate-500 dark:text-slate-400">
                <span>{c.version}</span>
                <span className="w-1 h-1 rounded-full bg-slate-300" />
                <span>{c.size}</span>
                <span className="w-1 h-1 rounded-full bg-slate-300" />
                <span>{c.requirement}</span>
              </div>
            </div>
          </motion.div>
        </div>
      </section>

      <section className="relative -mt-10 pb-16">
        <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            {platforms.map((item) => (
              <Link
                key={item.platform}
                href={item.href}
                className="rounded-2xl border border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900 p-5 shadow-sm hover:border-emerald-300 hover:shadow-lg transition-all"
              >
                <div className="mb-4 flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-300">
                  {item.platform === 'Android' || item.platform === 'iPhone' ? <Smartphone className="h-5 w-5" /> : <Laptop className="h-5 w-5" />}
                </div>
                <p className="text-xs font-bold uppercase tracking-[0.16em] text-emerald-600 dark:text-emerald-400">{item.platform}</p>
                <h2 className="mt-2 text-base font-black text-slate-900 dark:text-white">{item.title}</h2>
                <p className="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">{item.description}</p>
              </Link>
            ))}
          </div>
        </div>
      </section>

      <section className="relative py-24">
        <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            {c.features.map((feature, index) => (
              <motion.div
                key={feature.title}
                initial={{ opacity: 0, y: 30 }}
                whileInView={{ opacity: 1, y: 0 }}
                viewport={{ once: true }}
                transition={{ duration: 0.5, delay: index * 0.1 }}
                className="relative rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 p-6 hover:shadow-lg hover:border-emerald-200 dark:hover:border-emerald-800/50 transition-all"
              >
                <div className="w-12 h-12 rounded-xl bg-gradient-to-br from-emerald-50 to-cyan-50 dark:from-emerald-950/50 dark:to-cyan-950/50 text-emerald-600 dark:text-emerald-400 flex items-center justify-center mb-4">
                  {feature.icon}
                </div>
                <h3 className="text-lg font-bold text-slate-900 dark:text-white mb-2">{feature.title}</h3>
                <p className="text-sm text-slate-500 dark:text-slate-400 leading-relaxed">{feature.description}</p>
              </motion.div>
            ))}
          </div>
        </div>
      </section>

      <section className="relative py-24 bg-slate-50 dark:bg-slate-900/50">
        <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
          <h2 className="text-3xl font-black text-slate-900 dark:text-white text-center mb-16">
            {c.howItWorks.title}
          </h2>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
            {c.howItWorks.steps.map((step, index) => (
              <motion.div
                key={step.step}
                initial={{ opacity: 0, y: 20 }}
                whileInView={{ opacity: 1, y: 0 }}
                viewport={{ once: true }}
                transition={{ duration: 0.5, delay: index * 0.15 }}
                className="text-center"
              >
                <div className="w-16 h-16 mx-auto mb-4 rounded-2xl bg-gradient-to-br from-emerald-500 to-cyan-500 text-white font-black text-xl flex items-center justify-center shadow-lg shadow-emerald-500/20">
                  {step.step}
                </div>
                <h3 className="text-xl font-bold text-slate-900 dark:text-white mb-2">{step.title}</h3>
                <p className="text-sm text-slate-500 dark:text-slate-400">{step.description}</p>
              </motion.div>
            ))}
          </div>
        </div>
      </section>

      <section className="relative py-24">
        <div className="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
          <h2 className="text-3xl font-black text-slate-900 dark:text-white text-center mb-12">
            {locale === 'fr' ? 'Configuration requise' : locale === 'tr' ? 'Sistem Gereksinimleri' : locale === 'ar' ? 'متطلبات النظام' : 'System Requirements'}
          </h2>
          <div className="rounded-2xl border border-slate-200/80 dark:border-slate-800/80 overflow-hidden">
            {c.requirements.map((req, index) => (
              <div
                key={req.label}
                className={`flex items-center justify-between px-6 py-4 ${
                  index % 2 === 0 ? 'bg-white dark:bg-slate-900' : 'bg-slate-50 dark:bg-slate-900/50'
                }`}
              >
                <span className="text-sm font-semibold text-slate-900 dark:text-white">{req.label}</span>
                <span className="text-sm text-slate-500 dark:text-slate-400">{req.value}</span>
              </div>
            ))}
          </div>
        </div>
      </section>

      <section className="relative py-24 bg-slate-50 dark:bg-slate-900/50">
        <div className="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
          <h2 className="text-3xl font-black text-slate-900 dark:text-white text-center mb-12">FAQ</h2>
          <div className="space-y-4">
            {c.faq.map((item) => (
              <div
                key={item.question}
                className="rounded-2xl border border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900 p-6"
              >
                <h3 className="text-base font-bold text-slate-900 dark:text-white mb-2">{item.question}</h3>
                <p className="text-sm text-slate-500 dark:text-slate-400 leading-relaxed">{item.answer}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ===== Section Applications Mobiles ===== */}
      <section id="mobile-apps" className="relative py-24 bg-slate-50 dark:bg-slate-900/50">
        <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="text-center mb-16">
            <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-emerald-500/[0.08] border border-emerald-500/15 text-emerald-700 dark:text-emerald-400 text-sm font-semibold mb-4">
              <Smartphone className="w-4 h-4" />
              {locale === 'fr' ? 'Mobile-First' : locale === 'ar' ? 'الأولوية للجوال' : locale === 'tr' ? 'Mobil Oncelikli' : 'Mobile-First'}
            </div>
            <h2 className="text-3xl font-black text-slate-900 dark:text-white mb-4">{mobileApps.sectionTitle}</h2>
            <p className="text-lg text-slate-500 dark:text-slate-400 max-w-2xl mx-auto">{mobileApps.sectionSubtitle}</p>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
            {mobileApps.apps.map((app, index) => {
              const androidTarget = mobileDownloadTarget(app.slug, 'android');
              const iosTarget = mobileDownloadTarget(app.slug, 'ios');
              const fallbackLabel = testerFallbackLabel(locale as AppLocale);

              return (
                <motion.div
                  key={app.name}
                  initial={{ opacity: 0, y: 30 }}
                  whileInView={{ opacity: 1, y: 0 }}
                  viewport={{ once: true }}
                  transition={{ duration: 0.5, delay: index * 0.1 }}
                  className="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 p-6 shadow-sm hover:shadow-lg hover:border-emerald-200 dark:hover:border-emerald-800/50 transition-all"
                >
                <div className="w-12 h-12 rounded-xl bg-gradient-to-br from-emerald-50 to-cyan-50 dark:from-emerald-950/50 dark:to-cyan-950/50 text-emerald-600 dark:text-emerald-400 flex items-center justify-center mb-4">
                  <Smartphone className="w-6 h-6" />
                </div>
                <h3 className="text-lg font-black text-slate-900 dark:text-white mb-2">{app.name}</h3>
                <p className="text-sm text-slate-500 dark:text-slate-400 leading-relaxed mb-6">{app.description}</p>

                <div className="space-y-3">
                  {/* Google Play button */}
                  <a
                    href={androidTarget.href}
                    className="flex items-center gap-3 w-full px-4 py-3 rounded-xl bg-slate-900 dark:bg-slate-800 text-white hover:bg-emerald-700 transition-colors text-sm font-semibold"
                    aria-label={`${app.name} - Google Play`}
                  >
                    <svg className="w-5 h-5 flex-shrink-0" viewBox="0 0 24 24" fill="currentColor">
                      <path d="M3.18 23.76c.31.17.67.18.99.04l12.45-7.2-2.88-2.87-10.56 10.03zM.8 1.4C.3 1.88 0 2.64 0 3.65v16.7c0 1.01.3 1.77.81 2.25l.12.11 9.35-9.35v-.22L.92 3.29.8 1.4zM20.67 10.4l-2.82-1.63-3.22 3.22 3.22 3.22 2.85-1.65c.81-.47.81-1.23-.03-1.7v-.06zM3.18.24L15.63 7.43l-2.88 2.87L2.19.27C2.5.13 2.87.07 3.18.24z"/>
                    </svg>
                    <span>{androidTarget.isFallback ? fallbackLabel : app.androidLabel}</span>
                  </a>

                  {/* App Store button */}
                  <a
                    href={iosTarget.href}
                    className="flex items-center gap-3 w-full px-4 py-3 rounded-xl bg-slate-900 dark:bg-slate-800 text-white hover:bg-emerald-700 transition-colors text-sm font-semibold"
                    aria-label={`${app.name} - App Store`}
                  >
                    <svg className="w-5 h-5 flex-shrink-0" viewBox="0 0 24 24" fill="currentColor">
                      <path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.8-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M13 3.5c.73-.83 1.94-1.46 2.94-1.5.13 1.17-.34 2.35-1.04 3.19-.69.85-1.83 1.51-2.95 1.42-.15-1.15.41-2.35 1.05-3.11z"/>
                    </svg>
                    <span>{iosTarget.isFallback ? fallbackLabel : app.iosLabel}</span>
                  </a>
                </div>
              </motion.div>
              );
            })}
          </div>
        </div>
      </section>

      <Footer />
    </div>
  );
}
