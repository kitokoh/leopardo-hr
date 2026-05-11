import { Brain, Clock, Shield, Smartphone, Users, Wallet } from 'lucide-react'
import type { LucideIcon } from 'lucide-react'
import type { AppLocale } from '@/lib/i18n'

export type Feature = {
  icon: LucideIcon
  title: string
  description: string
  gradient: string
  stats: string
  statsLabel: string
  details: string[]
}

const featuresByLocale: Record<AppLocale, Feature[]> = {
  fr: [
    {
      icon: Clock,
      title: 'Pointage intelligent',
      description: 'Gestion ultra-precise des presences avec compatibilite ZKTeco, NFC et biometrie avancee.',
      gradient: 'from-emerald-400 to-teal-500',
      stats: '99.9%',
      statsLabel: 'Precision',
      details: ['Reconnaissance faciale', 'NFC / QR code', 'Geolocalisation', 'Mode hors ligne'],
    },
    {
      icon: Users,
      title: 'Gestion des absences',
      description: 'Workflow complet de demande, validation et suivi des conges avec calendrier partage.',
      gradient: 'from-blue-400 to-indigo-500',
      stats: '50K+',
      statsLabel: 'Utilisateurs',
      details: ['Soldes en temps reel', 'Validation multi-niveaux', 'Calendrier equipe', 'Alertes automatiques'],
    },
    {
      icon: Wallet,
      title: 'Paie automatisee',
      description: 'Calcul automatique adapte aux reglementations locales avec generation de bulletins.',
      gradient: 'from-amber-400 to-orange-500',
      stats: '3x',
      statsLabel: 'Plus rapide',
      details: ['Multi-devises', 'Exports comptables', 'Avances sur salaire', 'Conformite fiscale'],
    },
    {
      icon: Shield,
      title: 'Securite renforcee',
      description: 'Authentification forte, chiffrement bout-en-bout et audit trail complet.',
      gradient: 'from-violet-400 to-purple-500',
      stats: 'SOC2',
      statsLabel: 'Hebergement',
      details: ['2FA', 'Chiffrement AES-256', 'Journal d audit', 'Conformite RGPD'],
    },
    {
      icon: Brain,
      title: 'Leo IA',
      description: 'Assistant IA pour analyser vos donnees RH, prioriser les anomalies et accelerer les decisions.',
      gradient: 'from-fuchsia-400 to-pink-500',
      stats: 'IA',
      statsLabel: 'Assistee',
      details: ['Analyse predictive', 'Rapports automatiques', 'Syntheses manager', 'Suggestions intelligentes'],
    },
    {
      icon: Smartphone,
      title: 'Mobile first',
      description: 'Application iOS et Android avec synchronisation temps reel et mode offline.',
      gradient: 'from-cyan-400 to-sky-500',
      stats: '4.9',
      statsLabel: 'App store',
      details: ['iOS & Android', 'Mode offline', 'Push notifications', 'Experience terrain'],
    },
  ],
  en: [
    {
      icon: Clock,
      title: 'Smart attendance',
      description: 'High-precision attendance with ZKTeco, NFC, and biometric device support.',
      gradient: 'from-emerald-400 to-teal-500',
      stats: '99.9%',
      statsLabel: 'Accuracy',
      details: ['Face recognition', 'NFC / QR code', 'Geofencing', 'Offline mode'],
    },
    {
      icon: Users,
      title: 'Leave workflows',
      description: 'Complete leave request, approval, and balance tracking with shared calendars.',
      gradient: 'from-blue-400 to-indigo-500',
      stats: '50K+',
      statsLabel: 'Users',
      details: ['Live balances', 'Multi-level approvals', 'Team calendar', 'Automatic alerts'],
    },
    {
      icon: Wallet,
      title: 'Automated payroll',
      description: 'Payroll calculations tailored to local rules with payslip generation built in.',
      gradient: 'from-amber-400 to-orange-500',
      stats: '3x',
      statsLabel: 'Faster',
      details: ['Multi-currency', 'Accounting exports', 'Salary advances', 'Tax compliance'],
    },
    {
      icon: Shield,
      title: 'Hardened security',
      description: 'Strong authentication, end-to-end encryption, and full audit visibility.',
      gradient: 'from-violet-400 to-purple-500',
      stats: 'SOC2',
      statsLabel: 'Hosted',
      details: ['2FA', 'AES-256 encryption', 'Audit trails', 'GDPR readiness'],
    },
    {
      icon: Brain,
      title: 'Leo AI',
      description: 'AI assistant for HR insight, anomaly prioritization, and faster team decisions.',
      gradient: 'from-fuchsia-400 to-pink-500',
      stats: 'AI',
      statsLabel: 'Powered',
      details: ['Predictive analysis', 'Automated reports', 'Manager summaries', 'Smart suggestions'],
    },
    {
      icon: Smartphone,
      title: 'Mobile first',
      description: 'Native iOS and Android experience with real-time sync and offline continuity.',
      gradient: 'from-cyan-400 to-sky-500',
      stats: '4.9',
      statsLabel: 'App score',
      details: ['iOS & Android', 'Offline mode', 'Push notifications', 'Field-ready UX'],
    },
  ],
  tr: [
    {
      icon: Clock,
      title: 'Akilli devam takibi',
      description: 'ZKTeco, NFC ve biyometrik cihazlarla hassas personel takibi.',
      gradient: 'from-emerald-400 to-teal-500',
      stats: '99.9%',
      statsLabel: 'Dogruluk',
      details: ['Yuz tanima', 'NFC / QR kod', 'Konum kontrolu', 'Cevrimdisi mod'],
    },
    {
      icon: Users,
      title: 'Izin surecleri',
      description: 'Izin talebi, onay ve bakiye yonetimini tek akis icinde yonetin.',
      gradient: 'from-blue-400 to-indigo-500',
      stats: '50K+',
      statsLabel: 'Kullanici',
      details: ['Canli bakiyeler', 'Cok seviyeli onay', 'Takim takvimi', 'Otomatik uyari'],
    },
    {
      icon: Wallet,
      title: 'Otomatik bordro',
      description: 'Yerel kurallara uygun bordro hesaplari ve maas pusulasi uretimi.',
      gradient: 'from-amber-400 to-orange-500',
      stats: '3x',
      statsLabel: 'Daha hizli',
      details: ['Coklu doviz', 'Muhasebe aktarimi', 'Maas avansi', 'Vergi uyumu'],
    },
    {
      icon: Shield,
      title: 'Guclu guvenlik',
      description: 'Guclu kimlik dogrulama, sifreleme ve tam denetim kaydi.',
      gradient: 'from-violet-400 to-purple-500',
      stats: 'SOC2',
      statsLabel: 'Barindirma',
      details: ['2FA', 'AES-256 sifreleme', 'Denetim kaydi', 'GDPR uyumu'],
    },
    {
      icon: Brain,
      title: 'Leo IA',
      description: 'IK verilerini yorumlayan ve anomali onceliklendiren akilli asistan.',
      gradient: 'from-fuchsia-400 to-pink-500',
      stats: 'YZ',
      statsLabel: 'Destekli',
      details: ['Tahmine dayali analiz', 'Otomatik rapor', 'Yonetici ozeti', 'Akilli oneriler'],
    },
    {
      icon: Smartphone,
      title: 'Mobil once',
      description: 'Gercek zamanli senkronizasyonlu iOS ve Android deneyimi.',
      gradient: 'from-cyan-400 to-sky-500',
      stats: '4.9',
      statsLabel: 'Puan',
      details: ['iOS & Android', 'Cevrimdisi mod', 'Bildirimler', 'Saha uyumlu deneyim'],
    },
  ],
  ar: [
    {
      icon: Clock,
      title: 'حضور ذكي',
      description: 'متابعة دقيقة للحضور مع تكامل ZKTeco و NFC والاجهزة البيومترية.',
      gradient: 'from-emerald-400 to-teal-500',
      stats: '99.9%',
      statsLabel: 'دقة',
      details: ['التعرف على الوجه', 'NFC / QR', 'تحديد الموقع', 'وضع دون اتصال'],
    },
    {
      icon: Users,
      title: 'ادارة الاجازات',
      description: 'سير عمل كامل للطلبات والموافقات والارصدة مع تقويم مشترك.',
      gradient: 'from-blue-400 to-indigo-500',
      stats: '50K+',
      statsLabel: 'مستخدم',
      details: ['ارصدة مباشرة', 'موافقات متعددة', 'تقويم الفريق', 'تنبيهات تلقائية'],
    },
    {
      icon: Wallet,
      title: 'رواتب مؤتمتة',
      description: 'حسابات رواتب متوافقة محليا مع توليد كشوف الرواتب بشكل مدمج.',
      gradient: 'from-amber-400 to-orange-500',
      stats: '3x',
      statsLabel: 'اسرع',
      details: ['عملات متعددة', 'تصدير محاسبي', 'سلف رواتب', 'امتثال ضريبي'],
    },
    {
      icon: Shield,
      title: 'امان معزز',
      description: 'مصادقة قوية وتشفير كامل وسجل تدقيق لكل العمليات.',
      gradient: 'from-violet-400 to-purple-500',
      stats: 'SOC2',
      statsLabel: 'استضافة',
      details: ['2FA', 'تشفير AES-256', 'سجل تدقيق', 'جاهزية GDPR'],
    },
    {
      icon: Brain,
      title: 'Leo IA',
      description: 'مساعد ذكي لتحليل بيانات الموارد البشرية واولويات التشغيل.',
      gradient: 'from-fuchsia-400 to-pink-500',
      stats: 'AI',
      statsLabel: 'مدعوم',
      details: ['تحليل تنبؤي', 'تقارير تلقائية', 'ملخصات المدير', 'اقتراحات ذكية'],
    },
    {
      icon: Smartphone,
      title: 'الجوال اولا',
      description: 'تجربة اصلية على iOS و Android مع مزامنة فورية ووضع دون اتصال.',
      gradient: 'from-cyan-400 to-sky-500',
      stats: '4.9',
      statsLabel: 'تقييم',
      details: ['iOS و Android', 'وضع دون اتصال', 'اشعارات فورية', 'جاهز للميدان'],
    },
  ],
}

export function getFeatures(locale: AppLocale): Feature[] {
  return featuresByLocale[locale] ?? featuresByLocale.fr
}
