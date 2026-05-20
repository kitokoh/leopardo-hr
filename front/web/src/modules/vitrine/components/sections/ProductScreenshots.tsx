'use client';

import { motion } from 'framer-motion';
import { Monitor, Smartphone, TabletSmartphone } from 'lucide-react';
import type { AppLocale } from '@/lib/i18n';

type ProductScreen = {
  icon: React.ReactNode;
  title: string;
  description: string;
  features: string[];
  gradient: string;
  mockup: 'desktop' | 'mobile' | 'kiosk';
};

const screensByLocale: Record<AppLocale, { badge: string; title: string; titleHighlight: string; screens: ProductScreen[] }> = {
  fr: {
    badge: 'Interfaces',
    title: 'Une plateforme,',
    titleHighlight: 'tous les ecrans',
    screens: [
      {
        icon: <Monitor className="w-6 h-6" />,
        title: 'Dashboard Admin',
        description: 'Cockpit complet pour piloter vos RH : KPI, pointage, paie, recrutement et analytics en temps reel.',
        features: ['KPI temps reel', 'Gestion paie multi-pays', 'Pipeline recrutement', 'Exports CSV/PDF'],
        gradient: 'from-emerald-500 to-cyan-500',
        mockup: 'desktop',
      },
      {
        icon: <Smartphone className="w-6 h-6" />,
        title: 'App Mobile',
        description: 'Vos employes pointent, consultent leurs bulletins et soumettent leurs demandes depuis leur telephone.',
        features: ['Pointage GPS/biometrique', 'Bulletins de paie', 'Demandes de conges', 'Notifications push'],
        gradient: 'from-violet-500 to-purple-500',
        mockup: 'mobile',
      },
      {
        icon: <TabletSmartphone className="w-6 h-6" />,
        title: 'Kiosque ZKTeco',
        description: "Terminal en libre-service pour le pointage sur site, les annonces et l'acces aux documents RH.",
        features: ['Pointage biometrique', 'Annonces entreprise', 'QR code employe', 'Mode hors ligne'],
        gradient: 'from-amber-500 to-orange-500',
        mockup: 'kiosk',
      },
    ],
  },
  en: {
    badge: 'Interfaces',
    title: 'One platform,',
    titleHighlight: 'every screen',
    screens: [
      {
        icon: <Monitor className="w-6 h-6" />,
        title: 'Admin Dashboard',
        description: 'Full cockpit to manage HR: KPIs, attendance, payroll, recruitment, and real-time analytics.',
        features: ['Real-time KPIs', 'Multi-country payroll', 'Recruitment pipeline', 'CSV/PDF exports'],
        gradient: 'from-emerald-500 to-cyan-500',
        mockup: 'desktop',
      },
      {
        icon: <Smartphone className="w-6 h-6" />,
        title: 'Mobile App',
        description: 'Employees clock in, view pay slips, and submit requests directly from their phone.',
        features: ['GPS/biometric check-in', 'Pay slips', 'Leave requests', 'Push notifications'],
        gradient: 'from-violet-500 to-purple-500',
        mockup: 'mobile',
      },
      {
        icon: <TabletSmartphone className="w-6 h-6" />,
        title: 'ZKTeco Kiosk',
        description: 'Self-service terminal for on-site attendance, announcements, and HR document access.',
        features: ['Biometric check-in', 'Company announcements', 'Employee QR code', 'Offline mode'],
        gradient: 'from-amber-500 to-orange-500',
        mockup: 'kiosk',
      },
    ],
  },
  tr: {
    badge: 'Arayuzler',
    title: 'Tek platform,',
    titleHighlight: 'her ekran',
    screens: [
      {
        icon: <Monitor className="w-6 h-6" />,
        title: 'Yonetici Paneli',
        description: 'IK yonetimi icin tam kokpit: KPI, devam, bordro, ise alim ve gercek zamanli analiz.',
        features: ['Gercek zamanli KPI', 'Cok ulkeli bordro', 'Ise alim hatti', 'CSV/PDF ihracat'],
        gradient: 'from-emerald-500 to-cyan-500',
        mockup: 'desktop',
      },
      {
        icon: <Smartphone className="w-6 h-6" />,
        title: 'Mobil Uygulama',
        description: 'Calisanlar giris yapar, maas bordrolarini gorur ve taleplerini telefondan gonderir.',
        features: ['GPS/biyometrik giris', 'Maas bordrolari', 'Izin talepleri', 'Push bildirimler'],
        gradient: 'from-violet-500 to-purple-500',
        mockup: 'mobile',
      },
      {
        icon: <TabletSmartphone className="w-6 h-6" />,
        title: 'ZKTeco Kiosk',
        description: 'Sahada devam, duyurular ve IK belge erisimi icin self-servis terminal.',
        features: ['Biyometrik giris', 'Sirket duyurulari', 'Calisan QR kodu', 'Cevrimdisi mod'],
        gradient: 'from-amber-500 to-orange-500',
        mockup: 'kiosk',
      },
    ],
  },
  ar: {
    badge: 'الواجهات',
    title: 'منصة واحدة،',
    titleHighlight: 'كل الشاشات',
    screens: [
      {
        icon: <Monitor className="w-6 h-6" />,
        title: 'لوحة الإدارة',
        description: 'لوحة تحكم كاملة لإدارة الموارد البشرية: مؤشرات الأداء، الحضور، الرواتب، والتوظيف.',
        features: ['مؤشرات فورية', 'رواتب متعددة البلدان', 'خط أنابيب التوظيف', 'تصدير CSV/PDF'],
        gradient: 'from-emerald-500 to-cyan-500',
        mockup: 'desktop',
      },
      {
        icon: <Smartphone className="w-6 h-6" />,
        title: 'تطبيق الجوال',
        description: 'الموظفون يسجلون الحضور، يراجعون كشوف الرواتب، ويقدمون الطلبات من هواتفهم.',
        features: ['تسجيل GPS/بيومتري', 'كشوف الرواتب', 'طلبات الإجازة', 'إشعارات فورية'],
        gradient: 'from-violet-500 to-purple-500',
        mockup: 'mobile',
      },
      {
        icon: <TabletSmartphone className="w-6 h-6" />,
        title: 'كشك ZKTeco',
        description: 'محطة خدمة ذاتية للحضور في الموقع، الإعلانات، والوصول إلى مستندات الموارد البشرية.',
        features: ['تسجيل بيومتري', 'إعلانات الشركة', 'رمز QR الموظف', 'وضع دون اتصال'],
        gradient: 'from-amber-500 to-orange-500',
        mockup: 'kiosk',
      },
    ],
  },
};

function MockupFrame({ type, gradient }: { type: 'desktop' | 'mobile' | 'kiosk'; gradient: string }) {
  if (type === 'mobile') {
    return (
      <div className="relative mx-auto w-[180px] h-[320px]">
        <div className="absolute inset-0 rounded-[2rem] border-4 border-slate-800 dark:border-slate-600 bg-slate-900 shadow-2xl overflow-hidden">
          <div className="absolute top-0 left-1/2 -translate-x-1/2 w-20 h-5 bg-slate-800 dark:bg-slate-600 rounded-b-xl" />
          <div className={`absolute inset-[3px] rounded-[1.7rem] bg-gradient-to-br ${gradient} opacity-20`} />
          <div className="absolute inset-[3px] rounded-[1.7rem] flex flex-col items-center justify-center gap-2 p-4">
            <div className="w-full h-3 bg-white/20 rounded" />
            <div className="w-3/4 h-3 bg-white/15 rounded" />
            <div className="w-full h-16 bg-white/10 rounded-lg mt-2" />
            <div className="w-full h-8 bg-white/10 rounded-lg" />
            <div className="w-full h-8 bg-white/10 rounded-lg" />
          </div>
        </div>
      </div>
    );
  }

  if (type === 'kiosk') {
    return (
      <div className="relative mx-auto w-[220px] h-[280px]">
        <div className="absolute inset-0 rounded-2xl border-4 border-slate-700 dark:border-slate-600 bg-slate-900 shadow-2xl overflow-hidden">
          <div className={`absolute inset-[3px] rounded-xl bg-gradient-to-br ${gradient} opacity-20`} />
          <div className="absolute inset-[3px] rounded-xl flex flex-col items-center justify-center gap-3 p-4">
            <div className="w-16 h-16 rounded-full bg-white/15 flex items-center justify-center">
              <div className="w-8 h-8 rounded-full bg-white/20" />
            </div>
            <div className="w-3/4 h-3 bg-white/20 rounded" />
            <div className="w-full h-10 bg-white/10 rounded-lg mt-1" />
            <div className="w-full h-10 bg-white/10 rounded-lg" />
          </div>
        </div>
        <div className="absolute -bottom-3 left-1/2 -translate-x-1/2 w-12 h-3 bg-slate-700 dark:bg-slate-600 rounded-b-lg" />
      </div>
    );
  }

  return (
    <div className="relative mx-auto w-full max-w-[300px] h-[200px]">
      <div className="absolute inset-0 rounded-xl border-2 border-slate-700 dark:border-slate-600 bg-slate-900 shadow-2xl overflow-hidden">
        <div className="absolute top-0 left-0 right-0 h-6 bg-slate-800 dark:bg-slate-700 flex items-center gap-1.5 px-3">
          <span className="w-2 h-2 rounded-full bg-red-500/60" />
          <span className="w-2 h-2 rounded-full bg-amber-500/60" />
          <span className="w-2 h-2 rounded-full bg-green-500/60" />
        </div>
        <div className={`absolute inset-[2px] top-6 rounded-b-lg bg-gradient-to-br ${gradient} opacity-15`} />
        <div className="absolute inset-[2px] top-6 rounded-b-lg flex flex-col gap-2 p-3">
          <div className="flex gap-2">
            <div className="w-1/4 h-full bg-white/10 rounded-lg min-h-[130px]" />
            <div className="flex-1 flex flex-col gap-2">
              <div className="flex gap-2">
                <div className="flex-1 h-12 bg-white/10 rounded-lg" />
                <div className="flex-1 h-12 bg-white/10 rounded-lg" />
                <div className="flex-1 h-12 bg-white/10 rounded-lg" />
              </div>
              <div className="flex-1 bg-white/10 rounded-lg" />
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

export interface ProductScreenshotsProps {
  locale?: AppLocale;
}

export function ProductScreenshots({ locale = 'fr' }: ProductScreenshotsProps) {
  const data = screensByLocale[locale] ?? screensByLocale.fr;

  return (
    <section className="relative py-24 overflow-hidden">
      <div className="absolute inset-0 bg-gradient-to-b from-white via-slate-50/50 to-white dark:from-slate-950 dark:via-slate-900/50 dark:to-slate-950" />

      <div className="relative max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          whileInView={{ opacity: 1, y: 0 }}
          viewport={{ once: true }}
          transition={{ duration: 0.6 }}
          className="text-center mb-16"
        >
          <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-violet-500/[0.08] border border-violet-500/15 text-violet-700 dark:text-violet-400 text-sm font-semibold mb-6">
            <span className="w-1.5 h-1.5 rounded-full bg-violet-500 animate-pulse" />
            {data.badge}
          </div>
          <h2 className="text-3xl sm:text-4xl lg:text-5xl font-black text-slate-900 dark:text-white tracking-tight">
            {data.title}{' '}
            <span className="bg-gradient-to-r from-violet-500 to-purple-500 bg-clip-text text-transparent">
              {data.titleHighlight}
            </span>
          </h2>
        </motion.div>

        <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
          {data.screens.map((screen, index) => (
            <motion.div
              key={screen.title}
              initial={{ opacity: 0, y: 30 }}
              whileInView={{ opacity: 1, y: 0 }}
              viewport={{ once: true }}
              transition={{ duration: 0.6, delay: index * 0.15 }}
              className="group"
            >
              <div className="bg-white dark:bg-slate-900/80 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 p-6 hover:shadow-xl transition-all duration-300">
                <div className="mb-6">
                  <MockupFrame type={screen.mockup} gradient={screen.gradient} />
                </div>

                <div className="flex items-center gap-3 mb-3">
                  <div className={`w-10 h-10 rounded-xl bg-gradient-to-br ${screen.gradient} flex items-center justify-center text-white`}>
                    {screen.icon}
                  </div>
                  <h3 className="text-lg font-bold text-slate-900 dark:text-white">{screen.title}</h3>
                </div>

                <p className="text-sm text-slate-600 dark:text-slate-300 mb-4 leading-relaxed">
                  {screen.description}
                </p>

                <ul className="space-y-1.5">
                  {screen.features.map((feature) => (
                    <li key={feature} className="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                      <span className={`w-1 h-1 rounded-full bg-gradient-to-r ${screen.gradient}`} />
                      {feature}
                    </li>
                  ))}
                </ul>
              </div>
            </motion.div>
          ))}
        </div>
      </div>
    </section>
  );
}
