import { useEffect, useMemo, useState } from 'react'
import {
  type AppLocale,
  applyDocumentLocale,
  getLocaleDirection,
  getPreferredLocale,
  normalizeLocale,
  storePreferredLocale,
} from '@/lib/i18n'

const LOCALE_EVENT = 'vitrine-locale-changed'

type LocaleOption = {
  value: AppLocale
  label: string
  nativeLabel: string
}

type HeroStat = {
  value: number
  suffix: string
  label: string
}

type DemoStat = {
  label: string
  value: string
}

type LandingCopy = {
  nav: {
    sections: Array<{ id: string; label: string }>
    login: string
    trial: string
    themeLabel: string
    menuLabel: string
    localeLabel: string
    brandTagline: string
  }
  hero: {
    badge: string
    badgeNew: string
    titleTop: string
    titleBottom: string
    subtitle: string
    subtitleHighlight: string
    subtitleTail: string
    primaryCta: string
    secondaryCta: string
    mobileBadge?: string
    downloadCta?: string
    stats: HeroStat[]
  }
  heroQuickTrial: {
    placeholder: string
    submit: string
    submitting: string
    legal: string
    success: string
    error: string
  }
  features: {
    badge: string
    title: string
    titleHighlight: string
    subtitle: string
  }
  demo: {
    badge: string
    title: string
    titleHighlight: string
    subtitle: string
    highlights: string[]
    appUrl: string
    miniStats: DemoStat[]
  }
  pricing: {
    badge: string
    title: string
    titleHighlight: string
    subtitle: string
    recommended: string
    currency: string
  }
  testimonials: {
    badge: string
    title: string
    titleHighlight: string
    subtitle: string
  }
  faq: {
    badge: string
    title: string
    titleHighlight: string
  }
  cta: {
    badge: string
    title: string
    titleHighlight: string
    subtitle: string
    primary: string
    secondary: string
  }
  changelog: {
    badge: string
    title: string
    titleHighlight: string
    subtitle: string
    repoNote: string
  }
  footer: {
    description: string
    sections: Array<{ title: string; links: string[] }>
    rights: string
  }
}

export const vitrineLocaleOptions: LocaleOption[] = [
  { value: 'fr', label: 'French', nativeLabel: 'Francais' },
  { value: 'en', label: 'English', nativeLabel: 'English' },
  { value: 'tr', label: 'Turkish', nativeLabel: 'Turkce' },
  { value: 'ar', label: 'Arabic', nativeLabel: 'العربية' },
]

const landingCopy: Record<AppLocale, LandingCopy> = {
  fr: {
    nav: {
      sections: [
        { id: 'fonctionnalites', label: 'Fonctionnalites' },
        { id: 'tarifs', label: 'Tarifs' },
        { id: 'temoignages', label: 'Temoignages' },
        { id: 'faq', label: 'FAQ' },
      ],
      login: 'Connexion',
      trial: 'Essai gratuit',
      themeLabel: 'Changer le theme',
      menuLabel: 'Menu',
      localeLabel: 'Langue',
      brandTagline: 'RH Platform',
    },
    hero: {
      badge: 'Leo IA 2.0 disponible',
      badgeNew: 'Nouveau',
      titleTop: 'Le Mobile-First',
      titleBottom: 'Company OS.',
      subtitle: 'Workforce OS pour entreprises en mouvement : pointage, equipes, paie, validations et notifications.',
      subtitleHighlight: 'Employee, Manager, Platform Admin',
      subtitleTail: '— disponibles sur mobile, web et terrain.',
      mobileBadge: 'Disponible sur mobile',
      downloadCta: 'Telecharger les apps',
      primaryCta: 'Essai gratuit 30 jours',
      secondaryCta: 'Voir la demo',
      stats: [
        { value: 500, suffix: '+', label: 'Entreprises' },
        { value: 50, suffix: 'K+', label: 'Employes geres' },
        { value: 99, suffix: '.9%', label: 'Uptime' },
        { value: 4, suffix: '.9', label: 'Note moyenne' },
      ],
    },
    heroQuickTrial: {
      placeholder: 'email@entreprise.com',
      submit: 'Tester maintenant',
      submitting: 'Envoi...',
      legal: 'Email uniquement. Notre equipe prepare un essai adapte, sans mot de passe ni carte bancaire.',
      success: "Demande recue. L'equipe Leopardo vous contacte sous 24h ouvrables.",
      error: "Impossible d'envoyer la demande pour le moment.",
    },
    features: {
      badge: 'Fonctionnalites',
      title: 'Tout ce dont vous avez',
      titleHighlight: 'besoin',
      subtitle: "Une suite complete d'outils RH concue pour simplifier chaque aspect de votre quotidien.",
    },
    demo: {
      badge: 'Interface moderne',
      title: 'Une experience',
      titleHighlight: 'revolutionnaire',
      subtitle: 'Decouvrez une interface pensee pour la productivite. Chaque pixel est concu pour simplifier vos operations RH quotidiennes.',
      highlights: [
        'Tableau de bord en temps reel',
        'Rapports automatises avec IA',
        'Integration multi-device native',
        'Notifications intelligentes',
        'Compatibilite ZKTeco',
      ],
      appUrl: 'app.leopardo-rh.com/dashboard',
      miniStats: [
        { label: 'Employes', value: '247' },
        { label: 'Presents', value: '231' },
        { label: 'Securite', value: '100%' },
      ],
    },
    pricing: {
      badge: 'Tarifs',
      title: 'Des prix',
      titleHighlight: 'transparents',
      subtitle: 'Sans engagement. Sans surprise. Commencez gratuitement.',
      recommended: 'Recommande',
      currency: 'EUR',
    },
    testimonials: {
      badge: 'Temoignages',
      title: 'Ils nous font',
      titleHighlight: 'confiance',
      subtitle: 'Plus de 500 entreprises utilisent Leopardo RH au quotidien.',
    },
    faq: {
      badge: 'FAQ',
      title: 'Questions',
      titleHighlight: 'frequentes',
    },
    cta: {
      badge: 'Rejoignez 500+ entreprises',
      title: 'Pret a transformer',
      titleHighlight: 'votre gestion RH ?',
      subtitle: 'Commencez votre essai gratuit de 30 jours. Aucune carte de credit requise. Configuration en moins de 5 minutes.',
      primary: 'Commencer gratuitement',
      secondary: 'Demander une demo',
    },
    changelog: {
      badge: 'Produit',
      title: 'Journal des',
      titleHighlight: 'versions',
      subtitle: 'Dernieres livraisons majeures de la plateforme (extrait editorialise).',
      repoNote: 'Historique detaille : fichier CHANGELOG.md a la racine du depot.',
    },
    footer: {
      description: "Mobile-First Company OS pour gerer votre personnel sur le terrain, en bureau et a distance. Employee, Manager et Platform Admin disponibles sur mobile.",
      sections: [
        { title: 'Produit', links: ['Fonctionnalites', 'Tarifs', 'Integrations', 'API', 'Changelog', 'Leopardo for Windows'] },
        { title: 'Ressources', links: ['Documentation', 'Guides', 'Blog', 'Contact', 'Communaute'] },
        { title: 'Applications mobiles', links: ['Employee (Android)', 'Employee (iOS)', 'Manager (Android)', 'Manager (iOS)', 'Platform Admin (Android)'] },
        { title: 'Legal', links: ['Confidentialite', 'CGU', 'Mentions legales', 'RGPD'] },
      ],
      rights: 'Tous droits reserves.',
    },
  },
  en: {
    nav: {
      sections: [
        { id: 'fonctionnalites', label: 'Features' },
        { id: 'tarifs', label: 'Pricing' },
        { id: 'temoignages', label: 'Testimonials' },
        { id: 'faq', label: 'FAQ' },
      ],
      login: 'Sign in',
      trial: 'Free trial',
      themeLabel: 'Toggle theme',
      menuLabel: 'Menu',
      localeLabel: 'Language',
      brandTagline: 'HR Platform',
    },
    hero: {
      badge: 'Leo AI 2.0 available',
      badgeNew: 'New',
      titleTop: 'The Mobile-First',
      titleBottom: 'Company OS.',
      subtitle: 'Workforce OS for companies on the move: attendance, teams, payroll, approvals and smart notifications.',
      subtitleHighlight: 'Employee, Manager, Platform Admin',
      subtitleTail: '— available on mobile, web and field.',
      mobileBadge: 'Available on mobile',
      downloadCta: 'Download the apps',
      primaryCta: 'Start 30-day free trial',
      secondaryCta: 'Watch demo',
      stats: [
        { value: 500, suffix: '+', label: 'Companies' },
        { value: 50, suffix: 'K+', label: 'Employees managed' },
        { value: 99, suffix: '.9%', label: 'Uptime' },
        { value: 4, suffix: '.9', label: 'Average rating' },
      ],
    },
    heroQuickTrial: {
      placeholder: 'work@email.com',
      submit: 'Try now',
      submitting: 'Sending...',
      legal: 'Email only. Our team prepares the right trial access, no password or card required.',
      success: 'Request received. The Leopardo team will contact you within 24 business hours.',
      error: 'Unable to send the request right now.',
    },
    features: {
      badge: 'Features',
      title: 'Everything your team',
      titleHighlight: 'needs',
      subtitle: 'A complete HR suite designed to simplify each operational workflow across web, mobile, and field teams.',
    },
    demo: {
      badge: 'Modern interface',
      title: 'An experience built for',
      titleHighlight: 'speed',
      subtitle: 'Discover an interface shaped for productivity, visibility, and daily operational clarity.',
      highlights: [
        'Real-time executive dashboard',
        'AI-assisted automated reports',
        'Native multi-device experience',
        'Smart notifications',
        'ZKTeco-ready attendance',
      ],
      appUrl: 'app.leopardo-rh.com/dashboard',
      miniStats: [
        { label: 'Employees', value: '247' },
        { label: 'Present', value: '231' },
        { label: 'Security', value: '100%' },
      ],
    },
    pricing: {
      badge: 'Pricing',
      title: 'Clear and',
      titleHighlight: 'predictable',
      subtitle: 'No lock-in. No surprises. Start small and scale with confidence.',
      recommended: 'Recommended',
      currency: 'EUR',
    },
    testimonials: {
      badge: 'Testimonials',
      title: 'Trusted by',
      titleHighlight: 'growing teams',
      subtitle: 'More than 500 companies rely on Leopardo RH every day.',
    },
    faq: {
      badge: 'FAQ',
      title: 'Frequently asked',
      titleHighlight: 'questions',
    },
    cta: {
      badge: 'Join 500+ companies',
      title: 'Ready to transform',
      titleHighlight: 'your HR operations?',
      subtitle: 'Launch your 30-day free trial. No credit card required. Production setup in under five minutes.',
      primary: 'Start for free',
      secondary: 'Request a demo',
    },
    changelog: {
      badge: 'Product',
      title: 'Release',
      titleHighlight: 'notes',
      subtitle: 'Major platform updates (curated excerpt).',
      repoNote: 'Full history: CHANGELOG.md at the repository root.',
    },
    footer: {
      description: 'Mobile-First Company OS for managing your workforce in the field, at the office and remotely. Employee, Manager and Platform Admin available on mobile.',
      sections: [
        { title: 'Product', links: ['Features', 'Pricing', 'Integrations', 'API', 'Changelog', 'Leopardo for Windows'] },
        { title: 'Resources', links: ['Documentation', 'Guides', 'Blog', 'Contact', 'Community'] },
        { title: 'Mobile Apps', links: ['Employee (Android)', 'Employee (iOS)', 'Manager (Android)', 'Manager (iOS)', 'Platform Admin (Android)'] },
        { title: 'Legal', links: ['Privacy', 'Terms', 'Legal notice', 'GDPR'] },
      ],
      rights: 'All rights reserved.',
    },
  },
  tr: {
    nav: {
      sections: [
        { id: 'fonctionnalites', label: 'Ozellikler' },
        { id: 'tarifs', label: 'Fiyatlar' },
        { id: 'temoignages', label: 'Musteriler' },
        { id: 'faq', label: 'SSS' },
      ],
      login: 'Giris yap',
      trial: 'Ucretsiz dene',
      themeLabel: 'Temayi degistir',
      menuLabel: 'Menu',
      localeLabel: 'Dil',
      brandTagline: 'IK Platformu',
    },
    hero: {
      badge: 'Leo IA 2.0 hazir',
      badgeNew: 'Yeni',
      titleTop: 'Mobile-First',
      titleBottom: 'Company OS.',
      subtitle: 'Hareket halindeki sirketler icin Workforce OS: devam, ekipler, bordro, onaylar ve akilli bildirimler.',
      subtitleHighlight: 'Employee, Manager, Platform Admin',
      subtitleTail: '— mobil, web ve sahada kullanilabilir.',
      mobileBadge: 'Mobilde kullanilabilir',
      downloadCta: 'Uygulamalari indir',
      primaryCta: '30 gun ucretsiz deneyin',
      secondaryCta: 'Demoyu izle',
      stats: [
        { value: 500, suffix: '+', label: 'Sirket' },
        { value: 50, suffix: 'K+', label: 'Yonetilen calisan' },
        { value: 99, suffix: '.9%', label: 'Calisabilirlik' },
        { value: 4, suffix: '.9', label: 'Ortalama puan' },
      ],
    },
    heroQuickTrial: {
      placeholder: 'is@eposta.com',
      submit: 'Hemen dene',
      submitting: 'Gonderiliyor...',
      legal: 'Sadece e-posta. Ekibimiz sifre veya kart istemeden uygun deneme erisimini hazirlar.',
      success: 'Talep alindi. Leopardo ekibi 24 is saati icinde size ulasir.',
      error: 'Talep su anda gonderilemiyor.',
    },
    features: {
      badge: 'Ozellikler',
      title: 'Ekibinizin ihtiyac duydugu',
      titleHighlight: 'her sey',
      subtitle: 'Web, mobil ve saha ekipleri icin gunluk IK operasyonlarini sadeleştiren kapsamli arac paketi.',
    },
    demo: {
      badge: 'Modern arayuz',
      title: 'Hiz icin tasarlanmis',
      titleHighlight: 'bir deneyim',
      subtitle: 'Uretkenlik, gorunurluk ve saha kullanimi icin tasarlanmis arayuzu kesfedin.',
      highlights: [
        'Gercek zamanli yonetici paneli',
        'Yapay zekali otomatik raporlar',
        'Cok cihazli yerel deneyim',
        'Akilli bildirimler',
        'ZKTeco uyumlu takip',
      ],
      appUrl: 'app.leopardo-rh.com/dashboard',
      miniStats: [
        { label: 'Calisan', value: '247' },
        { label: 'Mevcut', value: '231' },
        { label: 'Guvenlik', value: '100%' },
      ],
    },
    pricing: {
      badge: 'Fiyatlar',
      title: 'Net ve',
      titleHighlight: 'surprizsiz',
      subtitle: 'Taahhutsuz. Gizli maliyet yok. Kucuk baslayin, rahatca buyuyun.',
      recommended: 'Onerilen',
      currency: 'EUR',
    },
    testimonials: {
      badge: 'Musteriler',
      title: 'Buyuyen ekiplerin',
      titleHighlight: 'tercihi',
      subtitle: '500+ sirket Leopardo RH ile gunluk IK operasyonlarini yonetiyor.',
    },
    faq: {
      badge: 'SSS',
      title: 'Sik sorulan',
      titleHighlight: 'sorular',
    },
    cta: {
      badge: '500+ sirkete katilin',
      title: 'IK sureclerini',
      titleHighlight: 'donusturmeye hazir misiniz?',
      subtitle: '30 gun ucretsiz deneyin. Kredi karti gerekmez. Kurulum bes dakikadan kisa surer.',
      primary: 'Ucretsiz basla',
      secondary: 'Demo iste',
    },
    changelog: {
      badge: 'Urun',
      title: 'Surum',
      titleHighlight: 'gunlugu',
      subtitle: 'Onemli platform guncellemeleri (ozet).',
      repoNote: 'Tam gecmis: depodaki CHANGELOG.md dosyasi.',
    },
    footer: {
      description: 'Saha, ofis ve uzaktan calisanlarinizi yonetmek icin Mobile-First Company OS. Employee, Manager ve Platform Admin mobilde kullanilabilir.',
      sections: [
        { title: 'Urun', links: ['Ozellikler', 'Fiyatlar', 'Entegrasyonlar', 'API', 'Degisiklikler', 'Windows icin Leopardo'] },
        { title: 'Kaynaklar', links: ['Dokumantasyon', 'Rehberler', 'Blog', 'Iletisim', 'Topluluk'] },
        { title: 'Mobil Uygulamalar', links: ['Employee (Android)', 'Employee (iOS)', 'Manager (Android)', 'Manager (iOS)', 'Platform Admin (Android)'] },
        { title: 'Yasal', links: ['Gizlilik', 'Kullanim Kosullari', 'Yasal Bildirim', 'KVKK/GDPR'] },
      ],
      rights: 'Tum haklari saklidir.',
    },
  },
  ar: {
    nav: {
      sections: [
        { id: 'fonctionnalites', label: 'الميزات' },
        { id: 'tarifs', label: 'الاسعار' },
        { id: 'temoignages', label: 'العملاء' },
        { id: 'faq', label: 'الاسئلة' },
      ],
      login: 'تسجيل الدخول',
      trial: 'تجربة مجانية',
      themeLabel: 'تبديل السمة',
      menuLabel: 'القائمة',
      localeLabel: 'اللغة',
      brandTagline: 'منصة الموارد البشرية',
    },
    hero: {
      badge: 'Leo IA 2.0 متاح الان',
      badgeNew: 'جديد',
      titleTop: 'نظام تشغيل الشركة',
      titleBottom: 'المتحرك بالجوال.',
      subtitle: 'Workforce OS للشركات في حركة دائمة: الحضور، الفرق، الرواتب، الموافقات والإشعارات.',
      subtitleHighlight: 'Employee, Manager, Platform Admin',
      subtitleTail: '— متاح على الجوال والويب والميدان.',
      mobileBadge: 'متاح على الجوال',
      downloadCta: 'تحميل التطبيقات',
      primaryCta: 'ابدأ تجربة 30 يوما',
      secondaryCta: 'شاهد العرض',
      stats: [
        { value: 500, suffix: '+', label: 'شركة' },
        { value: 50, suffix: 'K+', label: 'موظف تتم ادارتهم' },
        { value: 99, suffix: '.9%', label: 'جاهزية' },
        { value: 4, suffix: '.9', label: 'متوسط التقييم' },
      ],
    },
    heroQuickTrial: {
      placeholder: 'email@company.com',
      submit: 'جرّب الآن',
      submitting: 'جار الإرسال...',
      legal: 'البريد فقط. نجهز تجربة مناسبة بدون كلمة مرور أو بطاقة دفع.',
      success: 'تم استلام الطلب. سيتواصل معك فريق Leopardo خلال 24 ساعة عمل.',
      error: 'تعذر إرسال الطلب الآن.',
    },
    features: {
      badge: 'الميزات',
      title: 'كل ما يحتاجه فريقك',
      titleHighlight: 'في مكان واحد',
      subtitle: 'مجموعة متكاملة من ادوات الموارد البشرية للويب والجوال والعمل الميداني.',
    },
    demo: {
      badge: 'واجهة حديثة',
      title: 'تجربة مصممة',
      titleHighlight: 'للانتاجية',
      subtitle: 'اكتشف واجهة تساعدك على الرؤية الفورية وسرعة التنفيذ في العمليات اليومية.',
      highlights: [
        'لوحة تحكم مباشرة',
        'تقارير مؤتمتة بالذكاء الاصطناعي',
        'تجربة اصلية على كل الاجهزة',
        'اشعارات ذكية',
        'تكامل جاهز مع ZKTeco',
      ],
      appUrl: 'app.leopardo-rh.com/dashboard',
      miniStats: [
        { label: 'الموظفون', value: '247' },
        { label: 'الحاضرون', value: '231' },
        { label: 'الامان', value: '100%' },
      ],
    },
    pricing: {
      badge: 'الاسعار',
      title: 'اسعار واضحة',
      titleHighlight: 'وقابلة للتوسع',
      subtitle: 'بدون التزام طويل. بدون مفاجآت. ابدأ مجانا ثم توسع بثقة.',
      recommended: 'موصى به',
      currency: 'EUR',
    },
    testimonials: {
      badge: 'العملاء',
      title: 'موثوق من قبل',
      titleHighlight: 'الفرق النامية',
      subtitle: 'اكثر من 500 شركة تعتمد على Leopardo RH يوميا.',
    },
    faq: {
      badge: 'الاسئلة الشائعة',
      title: 'الاسئلة',
      titleHighlight: 'المتكررة',
    },
    cta: {
      badge: 'انضم الى اكثر من 500 شركة',
      title: 'هل انت مستعد',
      titleHighlight: 'لتطوير عمليات الموارد البشرية؟',
      subtitle: 'ابدأ تجربة مجانية لمدة 30 يوما بدون بطاقة ائتمان. التشغيل يتم خلال اقل من خمس دقائق.',
      primary: 'ابدأ مجانا',
      secondary: 'اطلب عرضا',
    },
    changelog: {
      badge: 'المنتج',
      title: 'سجل',
      titleHighlight: 'الاصدارات',
      subtitle: 'اهم تحديثات المنصة (مختارات تحريرية).',
      repoNote: 'السجل الكامل في ملف CHANGELOG.md في جذر المستودع.',
    },
    footer: {
      description: 'Mobile-First Company OS لإدارة فريقك في الميدان والمكتب وعن بُعد. Employee وManager وPlatform Admin متاحة على الجوال.',
      sections: [
        { title: 'المنتج', links: ['الميزات', 'الاسعار', 'التكاملات', 'API', 'سجل التغييرات', 'ليوباردو لويندوز'] },
        { title: 'الموارد', links: ['التوثيق', 'أدلة', 'المدونة', 'اتصل بنا', 'المجتمع'] },
        { title: 'تطبيقات الجوال', links: ['Employee (Android)', 'Employee (iOS)', 'Manager (Android)', 'Manager (iOS)', 'Platform Admin (Android)'] },
        { title: 'قانوني', links: ['الخصوصية', 'الشروط', 'الاشعارات القانونية', 'GDPR'] },
      ],
      rights: 'جميع الحقوق محفوظة.',
    },
  },
}

function getCurrentLocale(): AppLocale {
  if (typeof window === 'undefined') {
    return 'fr'
  }

  const urlLocale = new URLSearchParams(window.location.search).get('lang')
    ?? new URLSearchParams(window.location.search).get('locale')

  if (urlLocale) {
    const normalized = normalizeLocale(urlLocale)
    storePreferredLocale(normalized)
    return normalized
  }

  return normalizeLocale(getPreferredLocale())
}

function broadcastLocaleChange(): void {
  if (typeof window === 'undefined') {
    return
  }

  window.dispatchEvent(new Event(LOCALE_EVENT))
}

export function setVitrineLocale(locale: AppLocale): void {
  storePreferredLocale(locale)
  applyDocumentLocale(locale, locale === 'ar')
  broadcastLocaleChange()
}

export function useVitrineLocale() {
  const [locale, setLocaleState] = useState<AppLocale>(() => getCurrentLocale())

  useEffect(() => {
    const syncLocale = () => {
      const nextLocale = getCurrentLocale()
      setLocaleState(nextLocale)
      applyDocumentLocale(nextLocale, nextLocale === 'ar')
    }

    syncLocale()
    window.addEventListener('storage', syncLocale)
    window.addEventListener(LOCALE_EVENT, syncLocale)

    return () => {
      window.removeEventListener('storage', syncLocale)
      window.removeEventListener(LOCALE_EVENT, syncLocale)
    }
  }, [])

  const copy = useMemo(() => landingCopy[locale], [locale])
  const direction = getLocaleDirection(locale, locale === 'ar')

  return {
    locale,
    copy,
    direction,
    options: vitrineLocaleOptions,
    setLocale: (nextLocale: AppLocale) => setVitrineLocale(nextLocale),
  }
}
