import type { AppLocale } from '@/lib/i18n'
import type { MetricItem } from '../components/sections/SocialProofMetrics'
import type { MiniCaseItem } from '../components/sections/SocialProofCases'
import type { ScreenshotItem } from '../components/sections/ProductScreenshots'

type SocialProofCopy = {
  metricsSection: MetricItem[]
  featuredTestimonial: {
    badge: string
    quote: string
    authorName: string
    authorRole: string
    authorCompany: string
    authorInitials: string
    rating: number
  }
  casesSection: {
    title: string
    titleHighlight: string
    subtitle: string
    badge: string
    cases: MiniCaseItem[]
  }
  screenshotsSection: {
    title: string
    titleHighlight: string
    subtitle: string
    badge: string
  }
}

const socialProofCopy: Record<AppLocale, SocialProofCopy> = {
  fr: {
    metricsSection: [
      { icon: 'building', value: '500+', label: 'Entreprises clientes' },
      { icon: 'users', value: '50K+', label: 'Employes geres' },
      { icon: 'clock', value: '99.9%', label: 'Disponibilite' },
      { icon: 'star', value: '4.9/5', label: 'Note moyenne' },
    ],
    featuredTestimonial: {
      badge: 'Temoignage vedette',
      quote: "Leopardo RH a reduit notre temps de traitement de la paie de 80%. En 3 mois, nous avons digitalise l'ensemble de nos processus RH pour 200 employes.",
      authorName: 'Amina Diallo',
      authorRole: 'DRH',
      authorCompany: 'TechAfrika - Alger',
      authorInitials: 'AD',
      rating: 5,
    },
    casesSection: {
      title: 'Ils ont choisi',
      titleHighlight: 'Leopardo RH',
      subtitle: 'Des entreprises de toute taille nous font confiance pour moderniser leur gestion du personnel.',
      badge: 'Cas clients',
      cases: [
        {
          company: 'TechAfrika',
          country: 'DZ',
          sector: 'IT / Technologie',
          metric: '-80%',
          metricLabel: 'temps paie',
          description: '200 employes, paie automatisee en 3 mois. Integration ZKTeco pointeuse sur 4 sites.',
        },
        {
          company: 'Atlas Digital',
          country: 'MA',
          sector: 'Digital / Marketing',
          metric: '3x',
          metricLabel: 'plus rapide',
          description: 'Onboarding et gestion des absences digitalises. Gain de productivite RH immediat.',
        },
        {
          company: 'SenLogistics',
          country: 'SN',
          sector: 'Logistique / Transport',
          metric: '-60%',
          metricLabel: 'erreurs pointage',
          description: 'Pointage biometrique et geolocalise sur 12 entrepots. Mode hors ligne operationnel.',
        },
        {
          company: 'MedTunisie',
          country: 'TN',
          sector: 'Sante / Cliniques',
          metric: '100%',
          metricLabel: 'conformite',
          description: 'Gestion des gardes, paie medicale et conformite reglementaire pour 3 cliniques.',
        },
        {
          company: 'AbidjanBTP',
          country: 'CI',
          sector: 'BTP / Construction',
          metric: '-45%',
          metricLabel: 'absenteisme',
          description: 'Suivi terrain des ouvriers, anomalies manager et paie multi-sites automatisee.',
        },
      ],
    },
    screenshotsSection: {
      title: 'Decouvrez',
      titleHighlight: "l'interface",
      subtitle: 'Une plateforme complete avec dashboard admin, application mobile et kiosques de pointage.',
      badge: 'Apercu produit',
    },
  },
  en: {
    metricsSection: [
      { icon: 'building', value: '500+', label: 'Client companies' },
      { icon: 'users', value: '50K+', label: 'Employees managed' },
      { icon: 'clock', value: '99.9%', label: 'Uptime' },
      { icon: 'star', value: '4.9/5', label: 'Average rating' },
    ],
    featuredTestimonial: {
      badge: 'Featured testimonial',
      quote: 'Leopardo RH cut our payroll processing time by 80%. In 3 months, we digitized all HR workflows for 200 employees.',
      authorName: 'Amina Diallo',
      authorRole: 'HR Director',
      authorCompany: 'TechAfrika - Algiers',
      authorInitials: 'AD',
      rating: 5,
    },
    casesSection: {
      title: 'They chose',
      titleHighlight: 'Leopardo RH',
      subtitle: 'Companies of all sizes trust us to modernize their people operations.',
      badge: 'Case studies',
      cases: [
        {
          company: 'TechAfrika',
          country: 'DZ',
          sector: 'IT / Technology',
          metric: '-80%',
          metricLabel: 'payroll time',
          description: '200 employees, payroll automated in 3 months. ZKTeco integration across 4 sites.',
        },
        {
          company: 'Atlas Digital',
          country: 'MA',
          sector: 'Digital / Marketing',
          metric: '3x',
          metricLabel: 'faster',
          description: 'Onboarding and leave management digitized. Immediate HR productivity gain.',
        },
        {
          company: 'SenLogistics',
          country: 'SN',
          sector: 'Logistics / Transport',
          metric: '-60%',
          metricLabel: 'attendance errors',
          description: 'Biometric and geolocated attendance across 12 warehouses. Offline mode operational.',
        },
        {
          company: 'MedTunisie',
          country: 'TN',
          sector: 'Healthcare / Clinics',
          metric: '100%',
          metricLabel: 'compliance',
          description: 'Shift management, medical payroll and regulatory compliance for 3 clinics.',
        },
        {
          company: 'AbidjanBTP',
          country: 'CI',
          sector: 'Construction',
          metric: '-45%',
          metricLabel: 'absenteeism',
          description: 'Field worker tracking, manager anomaly alerts and multi-site payroll automation.',
        },
      ],
    },
    screenshotsSection: {
      title: 'Discover the',
      titleHighlight: 'interface',
      subtitle: 'A complete platform with admin dashboard, mobile app and attendance kiosks.',
      badge: 'Product preview',
    },
  },
  tr: {
    metricsSection: [
      { icon: 'building', value: '500+', label: 'Musteri sirket' },
      { icon: 'users', value: '50K+', label: 'Yonetilen calisan' },
      { icon: 'clock', value: '99.9%', label: 'Calisma suresi' },
      { icon: 'star', value: '4.9/5', label: 'Ortalama puan' },
    ],
    featuredTestimonial: {
      badge: 'One cikan referans',
      quote: "Leopardo RH bordro islem surelerimizi %80 azaltti. 3 ayda 200 calisan icin tum IK sureclerini dijitallestirdik.",
      authorName: 'Amina Diallo',
      authorRole: 'IK Direktoru',
      authorCompany: 'TechAfrika - Cezayir',
      authorInitials: 'AD',
      rating: 5,
    },
    casesSection: {
      title: 'Tercih ettiler',
      titleHighlight: 'Leopardo RH',
      subtitle: 'Her olcekte sirketler personel yonetimlerini modernize etmek icin bize guveniyorlar.',
      badge: 'Musteri hikayeleri',
      cases: [
        {
          company: 'TechAfrika',
          country: 'DZ',
          sector: 'BT / Teknoloji',
          metric: '-%80',
          metricLabel: 'bordro suresi',
          description: '200 calisan, 3 ayda otomatik bordro. 4 tesiste ZKTeco entegrasyonu.',
        },
        {
          company: 'Atlas Digital',
          country: 'MA',
          sector: 'Dijital / Pazarlama',
          metric: '3x',
          metricLabel: 'daha hizli',
          description: 'Ise alim ve izin yonetimi dijitallestirildi. Aninda IK verimlilik kazanci.',
        },
        {
          company: 'SenLogistics',
          country: 'SN',
          sector: 'Lojistik / Tasima',
          metric: '-%60',
          metricLabel: 'yoklama hatalari',
          description: '12 depoda biyometrik ve konum tabanli yoklama. Cevrimdisi mod aktif.',
        },
        {
          company: 'MedTunisie',
          country: 'TN',
          sector: 'Saglik / Klinikler',
          metric: '%100',
          metricLabel: 'uyumluluk',
          description: '3 klinik icin nobetcilik, medikal bordro ve mevzuat uyumlulugu.',
        },
        {
          company: 'AbidjanBTP',
          country: 'CI',
          sector: 'Insaat',
          metric: '-%45',
          metricLabel: 'devamsizlik',
          description: 'Saha iscisi takibi, yonetici anomali uyarilari ve coklu tesis bordrosu.',
        },
      ],
    },
    screenshotsSection: {
      title: 'Arayuzu',
      titleHighlight: 'kesfedin',
      subtitle: 'Admin paneli, mobil uygulama ve yoklama kiosklari ile eksiksiz bir platform.',
      badge: 'Urun onizleme',
    },
  },
  ar: {
    metricsSection: [
      { icon: 'building', value: '+500', label: 'شركة عميلة' },
      { icon: 'users', value: '+50 الف', label: 'موظف مدار' },
      { icon: 'clock', value: '99.9%', label: 'وقت التشغيل' },
      { icon: 'star', value: '4.9/5', label: 'التقييم المتوسط' },
    ],
    featuredTestimonial: {
      badge: 'شهادة مميزة',
      quote: 'Leopardo RH قلص وقت معالجة الرواتب لدينا بنسبة 80%. في 3 اشهر رقمنا جميع عمليات الموارد البشرية لـ 200 موظف.',
      authorName: 'Amina Diallo',
      authorRole: 'مديرة الموارد البشرية',
      authorCompany: 'TechAfrika - الجزائر',
      authorInitials: 'AD',
      rating: 5,
    },
    casesSection: {
      title: 'اختاروا',
      titleHighlight: 'Leopardo RH',
      subtitle: 'شركات من جميع الاحجام تثق بنا لتحديث ادارة مواردها البشرية.',
      badge: 'قصص العملاء',
      cases: [
        {
          company: 'TechAfrika',
          country: 'DZ',
          sector: 'تكنولوجيا المعلومات',
          metric: '-%80',
          metricLabel: 'وقت الرواتب',
          description: '200 موظف، رواتب تلقائية في 3 اشهر. تكامل ZKTeco في 4 مواقع.',
        },
        {
          company: 'Atlas Digital',
          country: 'MA',
          sector: 'رقمي / تسويق',
          metric: '3x',
          metricLabel: 'اسرع',
          description: 'رقمنة عملية التوظيف وادارة الاجازات. مكاسب انتاجية فورية.',
        },
        {
          company: 'SenLogistics',
          country: 'SN',
          sector: 'لوجستيات / نقل',
          metric: '-%60',
          metricLabel: 'اخطاء الحضور',
          description: 'حضور بيومتري وجغرافي في 12 مستودع. وضع عدم الاتصال فعال.',
        },
        {
          company: 'MedTunisie',
          country: 'TN',
          sector: 'صحة / عيادات',
          metric: '%100',
          metricLabel: 'امتثال',
          description: 'ادارة المناوبات والرواتب الطبية والامتثال التنظيمي لـ 3 عيادات.',
        },
        {
          company: 'AbidjanBTP',
          country: 'CI',
          sector: 'بناء',
          metric: '-%45',
          metricLabel: 'غياب',
          description: 'تتبع العمال الميدانيين وتنبيهات المدراء ورواتب متعددة المواقع.',
        },
      ],
    },
    screenshotsSection: {
      title: 'اكتشف',
      titleHighlight: 'الواجهة',
      subtitle: 'منصة متكاملة مع لوحة تحكم ادارية وتطبيق جوال واكشاك حضور.',
      badge: 'معاينة المنتج',
    },
  },
}

export const productScreenshots: ScreenshotItem[] = [
  {
    id: 'admin-dashboard',
    title: 'Dashboard principal',
    description: 'Vue KPI temps reel : employes, absences, paie, anomalies.',
    platform: 'admin',
  },
  {
    id: 'admin-payroll',
    title: 'Gestion de la paie',
    description: 'Bulletins, cycle paie, exports bancaires multi-pays.',
    platform: 'admin',
  },
  {
    id: 'admin-leaves',
    title: 'Absences et conges',
    description: 'Calendrier, approbations, soldes et politiques.',
    platform: 'admin',
  },
  {
    id: 'admin-recruitment',
    title: 'Recrutement',
    description: 'Pipeline kanban, candidatures et onboarding.',
    platform: 'admin',
  },
  {
    id: 'mobile-attendance',
    title: 'Pointage mobile',
    description: 'Check-in geolocalise, historique et synchronisation offline.',
    platform: 'mobile',
  },
  {
    id: 'mobile-payslip',
    title: 'Bulletins de paie',
    description: 'Consultation et telechargement PDF depuis le mobile.',
    platform: 'mobile',
  },
  {
    id: 'mobile-leaves',
    title: 'Demandes de conge',
    description: 'Soumission, suivi et approbation en temps reel.',
    platform: 'mobile',
  },
  {
    id: 'kiosk-checkin',
    title: 'Pointage kiosque',
    description: 'Identification biometrique, QR code et badge RFID.',
    platform: 'kiosk',
  },
  {
    id: 'kiosk-announcements',
    title: 'Annonces entreprise',
    description: 'Affichage des communications internes sur le kiosque.',
    platform: 'kiosk',
  },
]

export function getSocialProofCopy(locale: AppLocale): SocialProofCopy {
  return socialProofCopy[locale] ?? socialProofCopy.fr
}
