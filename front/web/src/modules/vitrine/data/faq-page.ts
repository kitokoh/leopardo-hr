import type { AppLocale } from '@/lib/i18n'

// Contenu de la page /faq par locale (issue #2605 — T004).
// Clés de catégorie canoniques (id) + libellés localisés.

export type FaqPageCategory = 'general' | 'pricing' | 'features' | 'security' | 'support' | 'integration'

export type FaqPageItem = {
  category: FaqPageCategory
  question: string
  answer: string
}

export type FaqPageContent = {
  hero: {
    badge: string
    headline: string
    subheadline: string
  }
  searchPlaceholder: string
  searchLabel: string
  categories: Record<FaqPageCategory, string>
  allCategory: string
  noResults: string
  cta: {
    headline: string
    subheadline: string
    primary: string
    secondary: string
  }
  items: FaqPageItem[]
}

const fr: FaqPageContent = {
  hero: {
    badge: 'FAQ',
    headline: 'Questions Fréquentes',
    subheadline: 'Tout ce que vous devez savoir sur Leopardo RH',
  },
  searchPlaceholder: 'Rechercher une question…',
  searchLabel: 'Rechercher dans la FAQ',
  categories: {
    general: 'Général',
    pricing: 'Tarification',
    features: 'Fonctionnalités',
    security: 'Sécurité',
    support: 'Support',
    integration: 'Intégration',
  },
  allCategory: 'Tous',
  noResults: 'Aucun résultat pour cette recherche.',
  cta: {
    headline: 'Encore des Questions ?',
    subheadline: "Contactez notre équipe pour une réponse personnalisée",
    primary: 'Nous Contacter',
    secondary: 'Essai gratuit',
  },
  items: [
    { category: 'general', question: "Qu'est-ce que Leopardo RH ?", answer: "Leopardo RH est une plateforme SaaS multi-tenant de gestion des ressources humaines qui couvre la paie, les congés, le pointage, le recrutement, la formation et bien plus. Elle est disponible en version web, mobile (Flutter) et borne kiosk (ZKTeco)." },
    { category: 'general', question: "Pour quels types d'entreprises est conçu Leopardo RH ?", answer: "Leopardo RH est conçu pour les PME, startups et grandes entreprises de tous secteurs. L'architecture multi-tenant permet une isolation complète des données entre entreprises tout en partageant la même infrastructure." },
    { category: 'pricing', question: 'Comment fonctionne la tarification ?', answer: "Nous proposons des plans mensuels et annuels adaptés à la taille de votre équipe. Le plan Free est gratuit jusqu'à 5 employés, le plan Pilot démarre à 29 €/mois (30 employés inclus), le plan Operations à 99 €/mois (250 employés inclus), et le plan Enterprise sur devis avec employés illimités et options sur mesure." },
    { category: 'pricing', question: 'Y a-t-il un essai gratuit ?', answer: "Oui ! Nous offrons un essai gratuit de 14 jours sans engagement et sans carte de crédit. Vous avez accès à toutes les fonctionnalités des plans payants pendant la période d'essai." },
    { category: 'features', question: 'Comment fonctionne le pointage biométrique ?', answer: "Leopardo RH s'intègre avec les bornes ZKTeco pour le pointage par empreinte digitale, reconnaissance faciale ou QR code. Les pointages sont synchronisés en temps réel avec le serveur central, avec support du mode hors ligne." },
    { category: 'features', question: 'Peut-on générer des bulletins de paie multi-pays ?', answer: "Oui, Leopardo RH supporte la paie pour plusieurs pays (France, Algérie, Turquie, Maroc, Tunisie, Sénégal, Côte d'Ivoire…) avec les barèmes fiscaux et cotisations sociales spécifiques à chaque pays. Les bulletins sont générés en PDF." },
    { category: 'features', question: "L'application mobile est-elle disponible ?", answer: "Oui, l'application mobile Leopardo RH est disponible pour iOS et Android. Elle permet aux employés de pointer, consulter leurs fiches de paie, demander des congés, voir l'organigramme et recevoir des notifications push." },
    { category: 'security', question: 'Comment sont protégées les données ?', answer: "Toutes les données sont chiffrées en transit (TLS 1.3) et au repos. L'architecture multi-tenant utilise des schémas PostgreSQL isolés par entreprise. Nous appliquons les bonnes pratiques OWASP et effectuons des audits de sécurité réguliers." },
    { category: 'security', question: 'Êtes-vous conforme au RGPD ?', answer: "Oui, Leopardo RH est conforme au RGPD. Les données sont hébergées en Europe, et nous offrons des outils d'export et de suppression des données personnelles conformes aux exigences réglementaires." },
    { category: 'support', question: 'Quel support est disponible ?', answer: "Le plan Free inclut le support communautaire. Le plan Pilot inclut le support par email sous 48h. Le plan Operations inclut le support prioritaire avec un temps de réponse sous 24h. Le plan Enterprise inclut un account manager dédié et un support 24/7." },
    { category: 'integration', question: 'Quelles intégrations sont disponibles ?', answer: "Leopardo RH s'intègre avec Google Calendar, Microsoft Outlook, les bornes ZKTeco, Firebase Cloud Messaging pour les notifications push, et expose une API REST complète pour les intégrations personnalisées." },
    { category: 'integration', question: 'Peut-on exporter les données vers des logiciels comptables ?', answer: "Oui, Leopardo RH supporte l'export des écritures de paie en format SEPA XML, CCP DZ et CSV, compatible avec la plupart des logiciels comptables. Les connecteurs dédiés Sage et QuickBooks arrivent prochainement." },
  ],
}

const en: FaqPageContent = {
  hero: {
    badge: 'FAQ',
    headline: 'Frequently Asked Questions',
    subheadline: 'Everything you need to know about Leopardo RH',
  },
  searchPlaceholder: 'Search a question…',
  searchLabel: 'Search the FAQ',
  categories: {
    general: 'General',
    pricing: 'Pricing',
    features: 'Features',
    security: 'Security',
    support: 'Support',
    integration: 'Integration',
  },
  allCategory: 'All',
  noResults: 'No results for this search.',
  cta: {
    headline: 'Still Have Questions?',
    subheadline: 'Contact our team for a personalised answer',
    primary: 'Contact Us',
    secondary: 'Free trial',
  },
  items: [
    { category: 'general', question: 'What is Leopardo RH?', answer: 'Leopardo RH is a multi-tenant SaaS platform for HR management covering payroll, leave, attendance, recruitment, training and more. It is available as a web app, mobile apps (Flutter), and ZKTeco kiosk terminals.' },
    { category: 'general', question: 'What types of companies is Leopardo RH designed for?', answer: 'Leopardo RH is built for SMEs, startups and larger companies in every sector. The multi-tenant architecture provides complete data isolation between companies while sharing the same infrastructure.' },
    { category: 'pricing', question: 'How does pricing work?', answer: 'We offer monthly and annual plans adapted to team size. The Free plan is free for up to 5 employees, the Pilot plan starts at €29/month (30 employees included), the Operations plan at €99/month (250 employees included), and the Enterprise plan is on quote with unlimited employees and custom options.' },
    { category: 'pricing', question: 'Is there a free trial?', answer: 'Yes! We offer a free 14-day trial with no commitment and no credit card required. You get access to all paid plan features during the trial.' },
    { category: 'features', question: 'How does biometric attendance work?', answer: 'Leopardo RH integrates with ZKTeco terminals for fingerprint, facial recognition or QR code attendance. Punches sync in real time with the central server, with full offline mode support.' },
    { category: 'features', question: 'Can we generate multi-country pay slips?', answer: 'Yes, Leopardo RH supports payroll for several countries (France, Algeria, Turkey, Morocco, Tunisia, Senegal, Ivory Coast…) with country-specific tax scales and social contributions. Payslips are generated as PDF.' },
    { category: 'features', question: 'Is the mobile app available?', answer: 'Yes, the Leopardo RH mobile app is available for iOS and Android. Employees can punch in, view pay slips, request leave, browse the org chart, and receive push notifications.' },
    { category: 'security', question: 'How is data protected?', answer: 'All data is encrypted in transit (TLS 1.3) and at rest. The multi-tenant architecture uses PostgreSQL schemas isolated per company. We follow OWASP best practices and run regular security audits.' },
    { category: 'security', question: 'Are you GDPR compliant?', answer: 'Yes, Leopardo RH is GDPR compliant. Data is hosted in Europe, and we provide export and deletion tools for personal data in line with regulatory requirements.' },
    { category: 'support', question: 'What support is available?', answer: 'The Free plan includes community support. The Pilot plan includes email support within 48h. The Operations plan includes priority support with a response time under 24h. The Enterprise plan includes a dedicated account manager and 24/7 support.' },
    { category: 'integration', question: 'What integrations are available?', answer: 'Leopardo RH integrates with Google Calendar, Microsoft Outlook, ZKTeco terminals, Firebase Cloud Messaging for push notifications, and exposes a complete REST API for custom integrations.' },
    { category: 'integration', question: 'Can we export data to accounting software?', answer: 'Yes, Leopardo RH supports payroll export in SEPA XML, CCP DZ and CSV formats, compatible with most accounting software. Dedicated Sage and QuickBooks connectors are coming soon.' },
  ],
}

const tr: FaqPageContent = {
  hero: {
    badge: 'SSS',
    headline: 'Sıkça Sorulan Sorular',
    subheadline: 'Leopardo RH hakkında bilmeniz gereken her şey',
  },
  searchPlaceholder: 'Bir soru arayın…',
  searchLabel: 'SSS içinde ara',
  categories: {
    general: 'Genel',
    pricing: 'Fiyatlandırma',
    features: 'Özellikler',
    security: 'Güvenlik',
    support: 'Destek',
    integration: 'Entegrasyon',
  },
  allCategory: 'Tümü',
  noResults: 'Bu arama için sonuç yok.',
  cta: {
    headline: 'Hâlâ Sorularınız mı Var?',
    subheadline: 'Kişisel bir yanıt için ekibimizle iletişime geçin',
    primary: 'Bize Ulaşın',
    secondary: 'Ücretsiz deneme',
  },
  items: [
    { category: 'general', question: 'Leopardo RH nedir?', answer: 'Leopardo RH, maaş, izin, devam, işe alım, eğitim ve daha fazlasını kapsayan çok kiracılı bir SaaS İK yönetim platformudur. Web uygulaması, mobil uygulamalar (Flutter) ve ZKTeco kiosk terminalleri olarak sunulur.' },
    { category: 'general', question: 'Leopardo RH hangi şirketler için tasarlandı?', answer: 'Leopardo RH, her sektörden KOBİ, girişim ve büyük şirketler için tasarlanmıştır. Çok kiracılı mimari, aynı altyapıyı paylaşırken şirketler arasında tam veri izolasyonu sağlar.' },
    { category: 'pricing', question: 'Fiyatlandırma nasıl çalışır?', answer: 'Ekip boyutuna uygun aylık ve yıllık planlar sunuyoruz. Free planı 5 çalışana kadar ücretsizdir, Pilot planı ayda 29 €\'dan başlar (30 çalışan dahil), Operations planı ayda 99 € (250 çalışan dahil), Enterprise planı ise sınırsız çalışan ve özel seçeneklerle teklif üzerinedir.' },
    { category: 'pricing', question: 'Ücretsiz deneme var mı?', answer: 'Evet! Taahhütsüz ve kredi kartı gerektirmeyen 14 günlük ücretsiz deneme sunuyoruz. Deneme süresince ücretli planların tüm özelliklerine erişirsiniz.' },
    { category: 'features', question: 'Biyometrik devam nasıl çalışır?', answer: 'Leopardo RH, parmak izi, yüz tanıma veya QR kod ile devam için ZKTeco terminalleriyle entegre olur. Yoklamalar, tam çevrimdışı mod desteğiyle merkezi sunucuya gerçek zamanlı senkronize edilir.' },
    { category: 'features', question: 'Çok ülkeli maaş bordrosu oluşturabilir miyiz?', answer: 'Evet, Leopardo RH birden fazla ülke (Fransa, Cezayir, Türkiye, Fas, Tunus, Senegal, Fildişi Sahili…) için ülkeye özgü vergi dilimleri ve sosyal katkılarla maaş hesaplamayı destekler. Bordrolar PDF olarak üretilir.' },
    { category: 'features', question: 'Mobil uygulama mevcut mu?', answer: 'Evet, Leopardo RH mobil uygulaması iOS ve Android için mevcuttur. Çalışanlar yoklama yapabilir, maaş bordrolarını görebilir, izin talep edebilir, organizasyon şemasını görüntüleyebilir ve bildirim alabilir.' },
    { category: 'security', question: 'Veriler nasıl korunuyor?', answer: 'Tüm veriler aktarım sırasında (TLS 1.3) ve depolamada şifrelenir. Çok kiracılı mimari, şirket başına izole PostgreSQL şemaları kullanır. OWASP en iyi uygulamalarını izliyor ve düzenli güvenlik denetimleri yapıyoruz.' },
    { category: 'security', question: 'KVKK/GDPR uyumlu musunuz?', answer: 'Evet, Leopardo RH GDPR uyumludur. Veriler Avrupa\'da barındırılır ve kişisel veriler için yasal gerekliliklere uygun dışa aktarma ve silme araçları sunuyoruz.' },
    { category: 'support', question: 'Hangi destek mevcut?', answer: 'Free planı topluluk desteği içerir. Pilot planı 48 saat içinde e-posta desteği içerir. Operations planı 24 saatten kısa yanıt süresiyle öncelikli destek içerir. Enterprise planı özel hesap yöneticisi ve 7/24 destek içerir.' },
    { category: 'integration', question: 'Hangi entegrasyonlar mevcut?', answer: 'Leopardo RH; Google Calendar, Microsoft Outlook, ZKTeco terminalleri, push bildirimleri için Firebase Cloud Messaging ile entegre olur ve özel entegrasyonlar için eksiksiz bir REST API sunar.' },
    { category: 'integration', question: 'Verileri muhasebe yazılımlarına aktarabilir miyiz?', answer: 'Evet, Leopardo RH çoğu muhasebe yazılımıyla uyumlu SEPA XML, CCP DZ ve CSV formatlarında maaş dışa aktarımını destekler. Sage ve QuickBooks için özel bağlayıcılar yakında geliyor.' },
  ],
}

const ar: FaqPageContent = {
  hero: {
    badge: 'الأسئلة الشائعة',
    headline: 'الأسئلة المتكررة',
    subheadline: 'كل ما تحتاج معرفته عن ليوباردو RH',
  },
  searchPlaceholder: 'ابحث عن سؤال…',
  searchLabel: 'ابحث في الأسئلة الشائعة',
  categories: {
    general: 'عام',
    pricing: 'التسعير',
    features: 'الميزات',
    security: 'الأمان',
    support: 'الدعم',
    integration: 'التكامل',
  },
  allCategory: 'الكل',
  noResults: 'لا توجد نتائج لهذا البحث.',
  cta: {
    headline: 'هل ما زال لديك أسئلة؟',
    subheadline: 'تواصل مع فريقنا للحصول على إجابة مخصصة',
    primary: 'اتصل بنا',
    secondary: 'تجربة مجانية',
  },
  items: [
    { category: 'general', question: 'ما هو ليوباردو RH؟', answer: 'ليوباردو RH هو منصة SaaS متعددة المستأجرين لإدارة الموارد البشرية تغطي الرواتب والإجازات والحضور والتوظيف والتدريب والمزيد. يتوفر كتطبيق ويب وتطبيقات جوال (Flutter) وأجهزة ZKTeco.' },
    { category: 'general', question: 'لأي نوع من الشركات صُمم ليوباردو RH؟', answer: 'صُمم ليوباردو RH للشركات الصغيرة والمتوسطة والشركات الناشئة والكبيرة في جميع القطاعات. توفر البنية متعددة المستأجرين عزلًا كاملاً للبيانات بين الشركات مع مشاركة البنية التحتية نفسها.' },
    { category: 'pricing', question: 'كيف يعمل التسعير؟', answer: 'نقدم خططًا شهرية وسنوية تتكيف مع حجم فريقك. الخطة المجانية مجانية حتى 5 موظفين، وتبدأ خطة Pilot من 29 يورو/شهر (30 موظفًا مشمولين)، وخطة Operations من 99 يورو/شهر (250 موظفًا مشمولًا)، وخطة Enterprise حسب الطلب مع موظفين غير محدودين وخيارات مخصصة.' },
    { category: 'pricing', question: 'هل توجد نسخة تجريبية مجانية؟', answer: 'نعم! نقدم نسخة تجريبية مجانية لمدة 14 يومًا بدون التزام وبدون بطاقة ائتمان. يمكنك الوصول إلى جميع ميزات الخطط المدفوعة خلال الفترة التجريبية.' },
    { category: 'features', question: 'كيف يعمل الحضور البيومتري؟', answer: 'يتكامل ليوباردو RH مع أجهزة ZKTeco للحضور عبر بصمة الإصبع أو التعرف على الوجه أو رمز QR. تتم مزامنة الحضور في الوقت الفعلي مع الخادم المركزي، مع دعم كامل للوضع دون اتصال.' },
    { category: 'features', question: 'هل يمكننا إنشاء قسائم رواتب متعددة البلدان؟', answer: 'نعم، يدعم ليوباردو RH الرواتب لعدة دول (فرنسا، الجزائر، تركيا، المغرب، تونس، السنغال، ساحل العاج…) بشرائح ضريبية واشتراكات اجتماعية خاصة بكل دولة. تُنشأ قسائم الراتب بصيغة PDF.' },
    { category: 'features', question: 'هل تطبيق الجوال متاح؟', answer: 'نعم، تطبيق ليوباردو RH متاح لنظامي iOS وAndroid. يمكن للموظفين تسجيل الحضور وعرض قسائم الرواتب وطلب الإجازات ومشاهدة الهيكل التنظيمي واستلام الإشعارات.' },
    { category: 'security', question: 'كيف تتم حماية البيانات؟', answer: 'جميع البيانات مشفرة أثناء النقل (TLS 1.3) وعند التخزين. تستخدم البنية متعددة المستأجرين مخططات PostgreSQL معزولة لكل شركة. نطبق أفضل ممارسات OWASP ونجري تدقيقات أمان منتظمة.' },
    { category: 'security', question: 'هل أنتم متوافقون مع GDPR؟', answer: 'نعم، ليوباردو RH متوافق مع اللائحة العامة لحماية البيانات GDPR. تُستضاف البيانات في أوروبا، ونوفر أدوات تصدير وحذف للبيانات الشخصية بما يتوافق مع المتطلبات التنظيمية.' },
    { category: 'support', question: 'ما هو الدعم المتاح؟', answer: 'تشمل الخطة المجانية دعم المجتمع. تشمل خطة Pilot دعم البريد الإلكتروني خلال 48 ساعة. تشمل خطة Operations الدعم ذا الأولوية بوقت استجابة أقل من 24 ساعة. تشمل خطة Enterprise مدير حساب مخصص ودعمًا على مدار الساعة.' },
    { category: 'integration', question: 'ما التكاملات المتاحة؟', answer: 'يتكامل ليوباردو RH مع Google Calendar وMicrosoft Outlook وأجهزة ZKTeco وFirebase Cloud Messaging للإشعارات، ويوفر واجهة REST API كاملة للتكاملات المخصصة.' },
    { category: 'integration', question: 'هل يمكننا تصدير البيانات إلى برامج المحاسبة؟', answer: 'نعم، يدعم ليوباردو RH تصدير الرواتب بصيغ SEPA XML وCCP DZ وCSV، المتوافقة مع معظم برامج المحاسبة. موصلات Sage وQuickBooks قادمة قريبًا.' },
  ],
}

const faqPageByLocale: Record<AppLocale, FaqPageContent> = { fr, en, tr, ar }

export function getFaqPageContent(locale: AppLocale): FaqPageContent {
  return faqPageByLocale[locale] ?? fr
}

