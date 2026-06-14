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
  problem: {
    badge: string
    title: string
    subtitle: string
    items: Array<{ title: string; description: string }>
  }
  solution: {
    badge: string
    title: string
    subtitle: string
    description: string
    features: Array<{ title: string; description: string }>
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
      badge: 'OS mobile-first pour equipes terrain',
      badgeNew: 'Nouveau',
      titleTop: 'Pilotez votre entreprise',
      titleBottom: 'avec une precision absolue.',
      subtitle: 'Simplifiez vos RH, automatisez votre paie et connectez vos equipes terrain.',
      subtitleHighlight: 'Leopardo est l\'OS tout-en-un',
      subtitleTail: 'pour transformer vos operations quotidiennes sans la complexite d\'un ERP.',
      mobileBadge: 'Disponible sur mobile',
      downloadCta: 'Telecharger les apps',
      primaryCta: 'Essai gratuit 30 jours',
      secondaryCta: 'Voir la demo',
      stats: [
        { value: 3, suffix: '', label: 'Apps mobiles' },
        { value: 2, suffix: '', label: 'Apps web' },
        { value: 30, suffix: 'j', label: 'Essai gratuit' },
        { value: 7, suffix: 'j', label: 'Pilote terrain' },
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
    problem: {
      badge: 'Le constat',
      title: 'La gestion RH traditionnelle vous freine ?',
      subtitle: 'Les feuilles de presence papier, les erreurs de paie et le manque de visibilite sur le terrain ralentissent votre croissance.',
      items: [
        { title: 'Pointage manuel et erreurs', description: 'Les oublis et les saisies manuelles coutent des heures precieuses chaque semaine.' },
        { title: 'Opacite du terrain', description: 'Difficile de savoir qui est present et sur quelle tache en temps reel.' },
        { title: 'Complexite de la paie', description: 'Le calcul des variables de paie est un casse-tete mensuel sujet aux erreurs.' },
        { title: 'Documents eparpilles', description: 'Les contrats et justificatifs sont perdus dans des emails ou des classeurs.' },
      ],
    },
    solution: {
      badge: 'La solution Leopardo',
      title: 'Un systeme d\'exploitation pour',
      subtitle: 'vos operations terrain.',
      description: 'Leopardo unifie tout votre flux operationnel dans une plateforme moderne, mobile-first et intuitive.',
      features: [
        { title: 'Pointage Biometrique & Mobile', description: 'Securisez les entrees avec ZKTeco, QR code ou GPS mobile.' },
        { title: 'Automatisation de la Paie', description: 'Generez les variables de paie en un clic, sans risque d\'erreur.' },
        { title: 'Visibilite en Temps Reel', description: 'Dashboards dynamiques pour une prise de decision immediate.' },
        { title: 'Self-Service Employe', description: 'Donnez de l\'autonomie a vos equipes avec une application dediee.' },
      ],
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
      title: 'Des offres',
      titleHighlight: 'pour lancer vite',
      subtitle: 'Commencez par un pilote gratuit, puis payez selon vos employes actifs et vos besoins terrain.',
      recommended: 'Recommande',
      currency: 'EUR',
    },
    testimonials: {
      badge: 'Temoignages',
      title: 'Ils nous font',
      titleHighlight: 'confiance',
      subtitle: 'Les premiers pilotes utilisent Leopardo pour unifier terrain, managers et admin plateforme sans multiplier les outils.',
    },
    faq: {
      badge: 'FAQ',
      title: 'Questions',
      titleHighlight: 'frequentes',
    },
    cta: {
      badge: 'Pret pour les pilotes terrain',
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
      badge: 'Mobile-first OS for field teams',
      badgeNew: 'New',
      titleTop: 'Run your business',
      titleBottom: 'with absolute precision.',
      subtitle: 'Simplify your HR, automate your payroll, and connect your field teams.',
      subtitleHighlight: 'Leopardo is the all-in-one OS',
      subtitleTail: 'to transform your daily operations without the complexity of a heavy ERP.',
      mobileBadge: 'Available on mobile',
      downloadCta: 'Download the apps',
      primaryCta: 'Start 30-day free trial',
      secondaryCta: 'Watch demo',
      stats: [
        { value: 3, suffix: '', label: 'Mobile apps' },
        { value: 2, suffix: '', label: 'Web apps' },
        { value: 30, suffix: 'd', label: 'Free trial' },
        { value: 7, suffix: 'd', label: 'Field pilot' },
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
    problem: {
      badge: 'The Reality',
      title: 'Tired of outdated HR management?',
      subtitle: 'Paper timesheets, payroll errors, and lack of field visibility are holding your business back.',
      items: [
        { title: 'Manual Tracking Errors', description: 'Manual entries and forgotten clock-ins cost hours of administrative work every week.' },
        { title: 'Field Opacity', description: 'It is hard to know who is present, where, and on what task in real-time.' },
        { title: 'Payroll Complexity', description: 'Calculating monthly payroll variables is a manual headache prone to mistakes.' },
        { title: 'Scattered Documents', description: 'Contracts and justifications are lost across emails, chats, and physical folders.' },
      ],
    },
    solution: {
      badge: 'The Leopardo Solution',
      title: 'An operating system for',
      subtitle: 'your field operations.',
      description: 'Leopardo unifies your entire operational workflow into a modern, mobile-first, and intuitive platform.',
      features: [
        { title: 'Biometric & Mobile Clock-in', description: 'Secure entries with ZKTeco hardware, QR codes, or mobile GPS.' },
        { title: 'Payroll Automation', description: 'Generate payroll variables in one click, eliminating manual error risks.' },
        { title: 'Real-Time Visibility', description: 'Dynamic dashboards for immediate operational decision-making.' },
        { title: 'Employee Self-Service', description: 'Empower your teams with a dedicated app for requests and documents.' },
      ],
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
      title: 'Plans built',
      titleHighlight: 'for real rollout',
      subtitle: 'Start with a free pilot, then pay based on active employees and field operations needs.',
      recommended: 'Recommended',
      currency: 'EUR',
    },
    testimonials: {
      badge: 'Testimonials',
      title: 'Trusted by',
      titleHighlight: 'growing teams',
      subtitle: 'Early pilots use Leopardo to connect field teams, managers and platform admins without multiplying tools.',
    },
    faq: {
      badge: 'FAQ',
      title: 'Frequently asked',
      titleHighlight: 'questions',
    },
    cta: {
      badge: 'Ready for field pilots',
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
      titleTop: 'Sirketinizi',
      titleBottom: 'tek uygulamadan yonetin.',
      subtitle: 'Leopardo yoklama, ekipler, talepler, basit bordro, belgeler ve kiosku gunluk operasyon sistemine donusturur.',
      subtitleHighlight: 'Employee, Manager, Platform Admin',
      subtitleTail: 'agir ERP olmadan saha pilotu baslatmaniz icin.',
      mobileBadge: 'Mobilde kullanilabilir',
      downloadCta: 'Uygulamalari indir',
      primaryCta: '30 gun ucretsiz deneyin',
      secondaryCta: 'Demoyu izle',
      stats: [
        { value: 3, suffix: '', label: 'Mobil app' },
        { value: 2, suffix: '', label: 'Web app' },
        { value: 30, suffix: 'g', label: 'Ucretsiz deneme' },
        { value: 7, suffix: 'g', label: 'Saha pilotu' },
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
    problem: {
      badge: 'Gercekler',
      title: 'Eski usul IK yonetiminden yoruldunuz mu?',
      subtitle: 'Kagit puantajlar, bordro hatalari ve saha gorunurlugu eksikligi buyumenizi engelliyor.',
      items: [
        { title: 'Manuel Takip Hatalari', description: 'Unutulan girisler ve manuel kayitlar her hafta saatlerce zaman kaybettirir.' },
        { title: 'Saha Opasitesi', description: 'Kimin nerede, hangi gorevde oldugunu gercek zamanli bilmek zordur.' },
        { title: 'Bordro Karmasasi', description: 'Bordro degiskenlerini hesaplamak hatalara acik, yorucu bir surectir.' },
        { title: 'Daginik Belgeler', description: 'Sozlesmeler ve belgeler e-postalar veya klasorler arasinda kaybolur.' },
      ],
    },
    solution: {
      badge: 'Leopardo Cozumu',
      title: 'Saha operasyonlariniz icin',
      subtitle: 'bir isletim sistemi.',
      description: 'Leopardo, tum operasyonel akisinizi modern, mobil oncelikli ve sezgisel bir platformda birlestirir.',
      features: [
        { title: 'Biyometrik ve Mobil Yoklama', description: 'ZKTeco, QR kod veya mobil GPS ile girisleri guvence altina alin.' },
        { title: 'Bordro Otomasyonu', description: 'Bordro degiskenlerini tek tikla, hata riski olmadan olusturun.' },
        { title: 'Gercek Zamanli Gorunurluk', description: 'Anlik karar verme icin dinamik yonetici panelleri.' },
        { title: 'Calisan Oz-Hizmet', description: 'Ekiplerinize talepler ve belgeler icin ozel bir uygulama sunun.' },
      ],
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
      title: 'Gercek kurulum',
      titleHighlight: 'icin paketler',
      subtitle: 'Ucretsiz pilotla baslayin, sonra aktif calisan ve saha ihtiyaclarina gore odeyin.',
      recommended: 'Onerilen',
      currency: 'EUR',
    },
    testimonials: {
      badge: 'Musteriler',
      title: 'Buyuyen ekiplerin',
      titleHighlight: 'tercihi',
      subtitle: 'Ilk pilotlar Leopardo ile saha ekiplerini, yoneticileri ve platform adminini tek akista birlestiriyor.',
    },
    faq: {
      badge: 'SSS',
      title: 'Sik sorulan',
      titleHighlight: 'sorular',
    },
    cta: {
      badge: 'Saha pilotlari icin hazir',
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
      subtitle: 'Leopardo يجمع الحضور والفرق والطلبات والرواتب المبسطة والوثائق والكشك في نظام تشغيل يومي.',
      subtitleHighlight: 'Employee, Manager, Platform Admin',
      subtitleTail: 'لتشغيل تجربة ميدانية بدون نظام ERP ثقيل.',
      mobileBadge: 'متاح على الجوال',
      downloadCta: 'تحميل التطبيقات',
      primaryCta: 'ابدأ تجربة 30 يوما',
      secondaryCta: 'شاهد العرض',
      stats: [
        { value: 3, suffix: '', label: 'تطبيقات جوال' },
        { value: 2, suffix: '', label: 'تطبيقات ويب' },
        { value: 30, suffix: 'ي', label: 'تجربة مجانية' },
        { value: 7, suffix: 'ي', label: 'تشغيل ميداني' },
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
    problem: {
      badge: 'الواقع',
      title: 'هل تعبت من إدارة الموارد البشرية التقليدية؟',
      subtitle: 'تؤدي سجلات الحضور الورقية وأخطاء الرواتب ونقص الرؤية الميدانية إلى إبطاء نموك.',
      items: [
        { title: 'أخطاء التتبع اليدوي', description: 'تستغرق عمليات الإدخال اليدوي المنسية ساعات ثمينة كل أسبوع.' },
        { title: 'عدم الوضوح الميداني', description: 'من الصعب معرفة من هو حاضر وفي أي مهمة في الوقت الفعلي.' },
        { title: 'تعقيد الرواتب', description: 'يعد حساب متغيرات الرواتب شهرياً صداعاً عرضة للأخطاء.' },
        { title: 'وثائق مبعثرة', description: 'تضيع العقود والمبررات في رسائل البريد الإلكتروني أو المجلدات.' },
      ],
    },
    solution: {
      badge: 'حل ليوباردو',
      title: 'نظام تشغيل لعملياتك',
      subtitle: 'الميدانية.',
      description: 'يجمع ليوباردو تدفق عملك التشغيلي بالكامل في منصة حديثة وسهلة الاستخدام تركز على الجوال.',
      features: [
        { title: 'الحضور البيومتري والجوال', description: 'تأمين المداخل عبر ZKTeco أو QR code أو GPS الجوال.' },
        { title: 'أتمتة الرواتب', description: 'إنشاء متغيرات الرواتب بنقرة واحدة، دون مخاطر الأخطاء.' },
        { title: 'رؤية في الوقت الفعلي', description: 'لوحات تحكم ديناميكية لاتخاذ قرارات فورية.' },
        { title: 'الخدمة الذاتية للموظف', description: 'امنح فرقك استقلالية من خلال تطبيق مخصص للطلبات والوثائق.' },
      ],
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
      title: 'باقات',
      titleHighlight: 'لإطلاق حقيقي',
      subtitle: 'ابدأ بتشغيل تجريبي مجاني ثم ادفع حسب الموظفين النشطين واحتياجات الميدان.',
      recommended: 'موصى به',
      currency: 'EUR',
    },
    testimonials: {
      badge: 'العملاء',
      title: 'موثوق من قبل',
      titleHighlight: 'الفرق النامية',
      subtitle: 'تستخدم الفرق التجريبية Leopardo لربط الميدان والمديرين وإدارة المنصة دون أدوات متفرقة.',
    },
    faq: {
      badge: 'الاسئلة الشائعة',
      title: 'الاسئلة',
      titleHighlight: 'المتكررة',
    },
    cta: {
      badge: 'جاهز للتشغيل الميداني',
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
