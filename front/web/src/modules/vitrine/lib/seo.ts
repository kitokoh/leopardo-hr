import { Metadata } from "next";

import { SITE_URL as siteUrl } from '@/lib/site-url';
import { t } from '@/lib/i18n/locale-catalog';
const siteName = process.env.NEXT_PUBLIC_SITE_NAME || "Leopardo";
const supportedLocales = ["fr", "en", "tr", "ar"] as const;

export interface SEOMetadata {
  title: string;
  description: string;
  keywords?: string[];
  ogImage?: string;
  ogType?: "website" | "article";
  canonical?: string;
  robots?: string;
  author?: string;
  publishedTime?: string;
  modifiedTime?: string;
  /** Locale BCP-47 de la page (ex. "fr_FR", "en_US", "tr_TR", "ar_AR"). */
  locale?: string;
}

/** #3807 : mapping AppLocale → og:locale BCP-47 (évite le fr_FR codé en dur). */
export function ogLocaleFor(locale: string): string {
  const map: Record<string, string> = {
    fr: 'fr_FR',
    en: 'en_US',
    tr: 'tr_TR',
    ar: 'ar_AR',
  };
  return map[locale] ?? 'fr_FR';
}

/**
 * Generate Next.js Metadata object
 */
export function generateMetadata(seo: SEOMetadata): Metadata {
  const url = seo.canonical || siteUrl;
  const image = seo.ogImage || `${siteUrl}/og-image.png`;
  const path = (() => {
    try {
      const parsed = new URL(url, siteUrl);
      return parsed.pathname === "/" ? "/" : parsed.pathname;
    } catch {
      return "/";
    }
  })();
  const localizedAlternates = Object.fromEntries(
    supportedLocales.map((locale) => [
      locale,
      locale === "fr" ? url : `${siteUrl}${path === "/" ? "/" : path}?lang=${locale}`,
    ])
  );

  return {
    title: seo.title,
    description: seo.description,
    keywords: seo.keywords,
    authors: seo.author ? [{ name: seo.author }] : undefined,
    robots: seo.robots || "index, follow",
    openGraph: {
      title: seo.title,
      description: seo.description,
      url: url,
      siteName: siteName,
      ...(seo.locale && { locale: ogLocaleFor(seo.locale) }),
      images: [
        {
          url: image,
          width: 1200,
          height: 630,
          alt: seo.title,
        },
      ],
      type: seo.ogType || "website",
      ...(seo.publishedTime && { publishedTime: seo.publishedTime }),
      ...(seo.modifiedTime && { modifiedTime: seo.modifiedTime }),
    },
    twitter: {
      card: "summary_large_image",
      title: seo.title,
      description: seo.description,
      images: [image],
    },
    alternates: {
      canonical: url,
      languages: localizedAlternates,
    },
  };
}


/**
 * #4004 : résolution SSR de la locale vitrine (Accept-Language) — partagée
 * par le root layout, pricing et les layouts landing (métadonnées SEO).
 */
export function resolveSsrLang(acceptLanguage: string | null): "fr" | "en" | "tr" | "ar" {
  const base = (acceptLanguage ?? "")
    .split(",")[0]
    .trim()
    .toLowerCase()
    .slice(0, 2);
  return (["fr", "en", "ar", "tr"] as const).includes(base as "fr" | "en" | "ar" | "tr")
    ? (base as "fr" | "en" | "ar" | "tr")
    : "fr";
}

/**
 * SEO metadata for all pages
 * Optimized titles (50-60 chars), descriptions (150-160 chars), keywords (3-5)
 */
export const pageMetadata = {
  landing: {
    title: "Gestion Employés, Paie & Documents | Plateforme Complète",
    description:
      "Gérez vos employés, paie et documents en un seul endroit. Essai gratuit 14 jours, sans carte bancaire.",
    keywords: [
      "gestion employés SaaS",
      "logiciel RH PME",
      "paie automatisée",
      "pointage numérique",
      "gestion absences",
    ],
    ogImage: `${siteUrl}/og/default.png`,
  },

  employes: {
    title: "Gestion RH Complète | Pointage, Absences, Schedules",
    description:
      "Gérez pointage, absences et schedules facilement. Pointage intelligent avec NFC et biométrie. Essai gratuit.",
    keywords: [
      "gestion RH PME",
      "pointage numérique",
      "gestion absences",
      "logiciel RH",
      "paie employés",
    ],
    ogImage: `${siteUrl}/og/default.png`,
  },

  documents: {
    title: "Cabinet Numérique Sécurisé | Gestion Documents Conformes",
    description:
      "Cabinet numérique avec chiffrement AES-256. Partage sécurisé, archivage automatique, conformité RGPD.",
    keywords: [
      "cabinet numérique",
      "gestion documents sécurisée",
      "partage documents",
      "archivage conformité",
      "RGPD documents",
    ],
    ogImage: `${siteUrl}/og/default.png`,
  },

  comptabilite: {
    title: "Paie Automatisée & Conformité | Bulletins Générés",
    description:
      "Paie automatisée avec calculs exacts et conformité garantie. Bulletins générés, exports comptables. Essai gratuit.",
    keywords: [
      "paie automatisée",
      "logiciel paie PME",
      "calcul salaire",
      "bulletins de paie",
      "conformité paie",
    ],
    ogImage: `${siteUrl}/og/default.png`,
  },

  marketing: {
    title: "Marketing Digital Intégré | Email, SMS, Réseaux Sociaux",
    description:
      "Outils marketing complets: email, SMS, réseaux sociaux. Automation, analytics, intégration RH.",
    keywords: [
      "email marketing PME",
      "SMS marketing",
      "automation marketing",
      "campagnes email",
      "marketing automation",
    ],
    ogImage: `${siteUrl}/og/default.png`,
  },

  integrations: {
    title: "Integrations & Connecteurs | Leopardo RH",
    description:
      "Connecteurs comptables et API Leopardo RH : Sage, QuickBooks, API publique, webhooks. Intégrez la paie et les RH à votre stack.",
    keywords: [
      "integrations RH",
      "connecteurs comptables",
      "API paie",
      "webhooks RH",
      "Sage QuickBooks",
    ],
    ogImage: `${siteUrl}/og/default.png`,
  },

  pricing: {
    title: "Tarification Transparente | Plans Flexibles",
    description:
      // Issue #3487 : la locale n'est pas résolue ici (metadata statique du
      // module) — le layout /pricing lit ?lang= et appelle t(locale, ...).
      // Ce fallback FR ne sert que si la clé i18n manque.
      t('fr', 'seo.pricing.description', 'Tarification transparente : plans Pilot 29 €/mois, Operations 99 €/mois, Enterprise sur devis. Essai gratuit 14 jours.'),
    keywords: [
      "prix logiciel RH",
      "tarification paie",
      "coût gestion employés",
      "plans pricing",
    ],
    ogImage: `${siteUrl}/og/default.png`,
  },

  about: {
    title: "À Propos | Notre Mission et Équipe",
    description:
      "Découvrez notre mission, équipe et valeurs. Nous aidons les PME à gérer leurs employés simplement.",
    keywords: ["à propos", "équipe", "mission", "valeurs"],
    ogImage: `${siteUrl}/og/default.png`,
  },

  blog: {
    title: "Blog & Resources | Guides RH et Conseils",
    description:
      "Guides, articles et webinaires sur la gestion RH, paie et productivité pour PME.",
    keywords: [
      "guide RH",
      "conseils paie",
      "gestion employés",
      "tendances RH",
      "automatisation RH",
    ],
    ogImage: `${siteUrl}/og/default.png`,
  },

  changelog: {
    title: "Journal des versions | Leopardo RH",
    description:
      "Découvrez les dernieres evolutions produit : API, paie, monitoring et admin. Extrait du changelog officiel.",
    keywords: [
      "changelog Leopardo",
      "nouveautes RH",
      "releases logiciel paie",
      "notes de version",
    ],
    ogImage: `${siteUrl}/og/default.png`,
  },

  docs: {
    title: "Documentation API | Guides techniques Leopardo RH",
    description:
      "Documentation technique et guides d'intégration pour l'API Leopardo RH : authentification, webhooks, endpoints RH et paie.",
    keywords: [
      "documentation API RH",
      "intégration Leopardo",
      "webhooks paie",
      "API gestion employés",
    ],
    ogImage: `${siteUrl}/og/default.png`,
  },

  download: {
    title: "Télécharger Leopardo RH | Windows, macOS, Android, iOS",
    description:
      "Téléchargez le client desktop ZKTeco et les applications mobiles Leopardo RH pour Windows, macOS, Android et iOS.",
    keywords: [
      "télécharger Leopardo RH",
      "application pointage mobile",
      "client desktop ZKTeco",
      "app RH Android iOS",
    ],
    ogImage: `${siteUrl}/og/default.png`,
  },

  contact: {
    title: "Contactez-nous | Support et Ventes Leopardo RH",
    description:
      "Une question sur Leopardo RH ? Contactez notre équipe commerciale ou support par email, telephone ou formulaire.",
    keywords: [
      "contact Leopardo RH",
      "support RH SaaS",
      "demande commerciale",
      "assistance logiciel RH",
    ],
    ogImage: `${siteUrl}/og/default.png`,
  },

  guideRhStartup: {
    title: "Guide Complet RH pour Startup | Télécharger",
    description:
      "Guide complet RH pour startup. Conseils, templates et bonnes pratiques. Téléchargez gratuitement en PDF.",
    keywords: [
      "guide RH startup",
      "RH pour startup",
      "gestion RH",
      "conseils RH",
    ],
    ogImage: `${siteUrl}/og/guides-rh-startup.png`,
  },

  guidePlanningEmployes: {
    title: "Modèle Planning Employés | Télécharger Excel",
    description:
      "Modèle de planning pour vos employés. Template Excel gratuit, flexible et facile à utiliser.",
    keywords: [
      "planning employés",
      "modèle planning",
      "template Excel",
      "gestion planning",
    ],
    ogImage: `${siteUrl}/og/guides-planning-employes.png`,
  },

  guideChecklistPaie: {
    title: "Checklist Paie 2026 | Télécharger Gratuitement",
    description:
      "Checklist complète pour votre paie. Vérifications et conformité. Téléchargez gratuitement en PDF.",
    keywords: [
      "checklist paie",
      "paie",
      "conformité paie",
      "gestion paie",
    ],
    ogImage: `${siteUrl}/og/guides-checklist-paie.png`,
  },

  guides: {
    title: "Guides & Ressources RH | Téléchargements Gratuits",
    description:
      "Téléchargez nos guides gratuits : Guide RH Startup, Checklist Paie 2026, Modèle Planning Employés.",
    keywords: [
      "guides gratuits",
      "ressources RH",
      "templates RH",
      "téléchargements",
    ],
    ogImage: `${siteUrl}/og/guides.png`,
  },

  demo: {
    title: "Demander une Démo | Leopardo RH",
    description:
      "Planifiez une démo gratuite de Leopardo RH. Découvrez la gestion RH automatisée : paie multi-pays, pointage, absences, formations.",
    keywords: [
      "demo Leopardo RH",
      "démo logiciel RH",
      "planifier démo SaaS",
      "gestion RH automatisée",
    ],
    ogImage: `${siteUrl}/og/demo.png`,
  },

  faq: {
    title: "Questions Frequentes | FAQ Leopardo RH",
    description:
      "Reponses aux questions les plus posees sur Leopardo RH : tarifs, essai gratuit, sécurité, integrations et support.",
    keywords: [
      "FAQ Leopardo RH",
      "questions logiciel RH",
      "aide gestion employés",
      "support paie SaaS",
    ],
    ogImage: `${siteUrl}/og/default.png`,
  },

  testimonials: {
    title: "Témoignages Clients | Avis sur Leopardo RH",
    description:
      "Découvrez comment nos clients transforment leur gestion RH avec Leopardo RH : pointage, paie et absences simplifies.",
    keywords: [
      "témoignages Leopardo RH",
      "avis clients logiciel RH",
      "retours utilisateurs paie SaaS",
      "case success RH PME",
    ],
    ogImage: `${siteUrl}/og/default.png`,
  },

  caseStudies: {
    title: "Etudes de Cas | Success Stories Leopardo RH",
    description:
      "Etudes de cas detaillees d'entreprises ayant déployé Leopardo RH pour automatiser paie, pointage et absences.",
    keywords: [
      "etudes de cas RH",
      "success story paie SaaS",
      "cas client Leopardo RH",
      "ROI logiciel RH",
    ],
    ogImage: `${siteUrl}/og/default.png`,
  },

  videos: {
    title: "Videos & Demonstrations | Leopardo RH en Action",
    description:
      "Regardez nos tutoriels et demonstrations video : configuration ZKTeco, paie multi-pays et prise en main de Leopardo RH.",
    keywords: [
      "videos Leopardo RH",
      "demo logiciel RH",
      "tutoriel pointage biométrique",
      "demonstration paie SaaS",
    ],
    ogImage: `${siteUrl}/og/default.png`,
  },

  branding: {
    title: "Branding & Personnalisation | Leopardo RH Multi-Tenant",
    description:
      "Personnalisez Leopardo RH avec votre logo, vos couleurs et votre nom d'affichage sur web et mobile, isolation tenant garantie.",
    keywords: [
      "branding SaaS RH",
      "personnalisation multi-tenant",
      "logo entreprise application RH",
      "theme personnalise paie",
    ],
    ogImage: `${siteUrl}/og/default.png`,
  },

  careers: {
    title: "Carrieres | Rejoignez l'Équipe Leopardo RH",
    description:
      "Découvrez nos offres d'emploi et rejoignez l'équipe qui construit la plateforme RH de reference pour les PME.",
    keywords: [
      "carrieres Leopardo RH",
      "emploi logiciel RH",
      "recrutement startup SaaS",
      "offres emploi tech RH",
    ],
    ogImage: `${siteUrl}/og/default.png`,
  },

  mobile: {
    title: "Applications Mobiles | Leopardo RH sur Android et iOS",
    description:
      "Applications mobiles Leopardo RH pour employés, managers et administrateurs : pointage, absences et validation en mobilite.",
    keywords: [
      "application mobile RH",
      "pointage mobile Android iOS",
      "app manager RH",
      "app employé pointage",
    ],
    ogImage: `${siteUrl}/og/default.png`,
  },

  signup: {
    title: "Essai Guide Gratuit | Découvrez Leopardo RH",
    description:
      "Demandez votre essai guide gratuit de Leopardo RH : aucun mot de passe requis, un espace de demonstration provisionne automatiquement.",
    keywords: [
      "essai gratuit RH",
      "demo Leopardo RH",
      "sandbox logiciel RH",
      "inscription essai paie SaaS",
    ],
    ogImage: `${siteUrl}/og/default.png`,
    robots: "noindex, follow",
  },

  checkout: {
    title: "Choisissez votre Plan | Abonnement Leopardo RH",
    description:
      "Selectionnez et souscrivez au plan Leopardo RH adapte a votre entreprise : Pilot, Operations ou Enterprise.",
    keywords: [
      "abonnement Leopardo RH",
      "souscription plan RH",
      "checkout SaaS RH",
      "paiement plan paie",
    ],
    ogImage: `${siteUrl}/og/default.png`,
    robots: "noindex, follow",
  },
};

/**
 * #4004 : métadonnées localisées (title + description) — la FR reste la
 * valeur par défaut (pageMetadata) ; les clés absentes retombent sur la FR.
 * Les keywords/ogImage restent partagés (FR) pour ne pas dupliquer les assets.
 */
export const pageMetadataLocalized: Record<
  "en" | "tr" | "ar",
  Record<string, { title: string; description: string }>
> = {
  en: {
    landing: { title: "Employee Management, Payroll & Documents | All-in-One Platform", description: "Manage employees, payroll and documents in one place. Free 14-day trial, no credit card required." },
    employes: { title: "Complete HR Management | Time Tracking, Absences, Schedules", description: "Track time, manage absences and schedules for your whole team." },
    documents: { title: "Secure Digital Cabinet | Compliant Document Management", description: "Store HR documents securely with role-based access and audit trail." },
    comptabilite: { title: "Automated Payroll & Compliance | Generated Payslips", description: "Multi-country payroll with automated tax and social contributions." },
    marketing: { title: "Integrated Digital Marketing | Email, SMS, Social Media", description: "Plan and publish your marketing campaigns in one click." },
    integrations: { title: "Integrations & Connectors | Leopardo HR", description: "Connect Leopardo HR with your tools and APIs." },
    pricing: { title: "Transparent Pricing | Flexible Plans", description: "Simple monthly plans for 5 to 250 employees, with a 30-day trial." },
    about: { title: "About Us | Our Mission and Team", description: "Discover the team behind the mobile-first HR platform for field SMEs." },
    blog: { title: "Blog & Resources | HR Guides and Tips", description: "Practical HR advice for field teams and growing companies." },
    changelog: { title: "Version History | Leopardo HR", description: "All product updates, fixes and new features." },
    docs: { title: "API Documentation | Leopardo HR Technical Guides", description: "Technical documentation for developers and partners." },
    download: { title: "Download Leopardo HR | Windows, macOS, Android, iOS", description: "Get the Leopardo HR apps on all your devices." },
    contact: { title: "Contact Us | Leopardo HR Sales & Support", description: "Our team answers within 24 hours." },
    guideRhStartup: { title: "Complete HR Guide for Startups | Download", description: "A practical HR guide for startups, free to download." },
    guidePlanningEmployes: { title: "Employee Planning Template | Download Excel", description: "Free Excel template to plan your employees' schedules." },
    guideChecklistPaie: { title: "Payroll Checklist 2026 | Free Download", description: "The essential payroll checklist for 2026." },
    guides: { title: "Guides & HR Resources | Free Downloads", description: "Guides, templates and resources for HR teams." },
    demo: { title: "Request a Leopardo HR Demo", description: "Book a free demo. Discover automated HR: multi-country payroll, time tracking, absences, training." },
    faq: { title: "Frequently Asked Questions | Leopardo HR FAQ", description: "Answers on pricing, free trial, security, integrations and support." },
    testimonials: { title: "Customer Testimonials | Leopardo HR Reviews", description: "What HR managers and business owners say about Leopardo HR." },
    caseStudies: { title: "Case Studies | Leopardo HR Success Stories", description: "Real examples of companies using Leopardo HR in the field." },
    videos: { title: "Videos & Demos | Leopardo HR in Action", description: "Tutorials and demos: ZKTeco setup, multi-country payroll." },
    branding: { title: "Branding & Customization | Leopardo HR Multi-Tenant", description: "Customize colors and logos for each tenant." },
    careers: { title: "Careers | Join the Leopardo HR Team", description: "Work with us on the mobile-first Company OS." },
    mobile: { title: "Mobile Apps | Leopardo HR on Android and iOS", description: "Employee, manager and platform admin apps." },
    signup: { title: "Free Guided Trial | Discover Leopardo HR", description: "Start your guided trial — no credit card required." },
    checkout: { title: "Choose Your Plan | Leopardo HR Subscription", description: "Select the plan that fits your company." },
  },
  tr: {
    landing: { title: "Calisan Yonetimi, Bordro ve Belgeler | Tek Platform", description: "Calisanlari, bordroyu ve belgeleri tek yerden yonetin. 14 gun ucretsiz deneme, kredi karti gerekmez." },
    employes: { title: "Kapsamli IK Yonetimi | Devam Takibi, Izinler, Planlar", description: "Ekip genelinde devam takibi, izin ve plan yonetimi." },
    documents: { title: "Guvenli Dijital Dolap | Uyumlu Belge Yonetimi", description: "Belgeleri rol bazli erisim ve denetim kaydiyla guvenle saklayin." },
    comptabilite: { title: "Otomatik Bordro ve Uyumluluk | Olusturulan Bordrolar", description: "Vergi ve sosyal guvenlik kesintileri otomatik cok ulkeli bordro." },
    marketing: { title: "Entegre Dijital Pazarlama | E-posta, SMS, Sosyal Medya", description: "Pazarlama kampanyalarinizi tek tikla planlayin ve yayinlayin." },
    integrations: { title: "Entegrasyonlar ve Baglayicilar | Leopardo HR", description: "Leopardo HR'i araclar ve API'lerle baglayin." },
    pricing: { title: "Sefaf Fiyatlandirma | Esnek Planlar", description: "5-250 calisan icin basit aylik planlar, 30 gun deneme." },
    about: { title: "Hakkimizda | Misyonumuz ve Ekibimiz", description: "Saha KOBİ'leri icin mobil oncelikli IK platformunun ekibini kesfedin." },
    blog: { title: "Blog ve Kaynaklar | IK Rehberleri ve Ipuclari", description: "Saha ekipleri icin pratik IK tavsiyeleri." },
    changelog: { title: "Surum Gecmisi | Leopardo HR", description: "Tum urun guncellemeleri, duzeltmeler ve yeni ozellikler." },
    docs: { title: "API Dokumantasyonu | Leopardo HR Teknik Rehberler", description: "Gelistiriciler ve is ortaklari icin teknik dokumantasyon." },
    download: { title: "Leopardo HR'yi Indir | Windows, macOS, Android, iOS", description: "Leopardo HR uygulamalarini tum cihazlariniza alin." },
    contact: { title: "Bize Ulasin | Leopardo HR Satis ve Destek", description: "Ekibimiz 24 saat icinde yanit verir." },
    guideRhStartup: { title: "Startup'lar icin Kapsamli IK Rehberi | Indir", description: "Startup'lar icin pratik IK rehberi, ucretsiz indirin." },
    guidePlanningEmployes: { title: "Calisan Planlama Sablonu | Excel Indir", description: "Calisan planlariniz icin ucretsiz Excel sablonu." },
    guideChecklistPaie: { title: "Bordro Kontrol Listesi 2026 | Ucretsiz Indir", description: "2026 icin temel bordro kontrol listesi." },
    guides: { title: "Rehberler ve IK Kaynaklari | Ucretsiz Indir", description: "IK ekipleri icin rehberler, sablonlar ve kaynaklar." },
    demo: { title: "Leopardo HR Demosu Talep Edin", description: "Ucretsiz demo planlayin. Otomatik IK: cok ulkeli bordro, devam takibi, izinler, egitim." },
    faq: { title: "Sik Sorulan Sorular | Leopardo HR SSS", description: "Fiyatlandirma, ucretsiz deneme, guvenlik, entegrasyon ve destek." },
    testimonials: { title: "Musteri Yorumlari | Leopardo HR Degerlendirmeleri", description: "IK yoneticileri ve isletme sahipleri Leopardo HR hakkinda ne diyor." },
    caseStudies: { title: "Basari Hikayeleri | Leopardo HR Ornekleri", description: "Sahada Leopardo HR kullanan sirketlerden gercek ornekler." },
    videos: { title: "Videolar ve Demolar | Leopardo HR Calismada", description: "Egitimler ve demolar: ZKTeco kurulumu, cok ulkeli bordro." },
    branding: { title: "Markalama ve Ozellestirme | Leopardo HR Cok Kiraci", description: "Her kiraci icin renk ve logolari ozellestirin." },
    careers: { title: "Kariyer | Leopardo HR Ekibine Katilin", description: "Mobil oncelikli Company OS uzerinde bizimle calisin." },
    mobile: { title: "Mobil Uygulamalar | Leopardo HR Android ve iOS", description: "Calisan, yonetici ve platform admin uygulamalari." },
    signup: { title: "Ucretsiz Rehberli Deneme | Leopardo HR'yi Kesfedin", description: "Rehberli denemenize baslayin — kredi karti gerekmez." },
    checkout: { title: "Planinizi Secin | Leopardo HR Abonelik", description: "Sirketinize uygun plani secin." },
  },
  ar: {
    landing: { title: "إدارة الموظفين والرواتب والمستندات | منصة متكاملة", description: "أدر موظفيك ورواتبك ومستنداتك في مكان واحد. تجربة مجانية لمدة 14 يوماً دون بطاقة ائتمانية." },
    employes: { title: "إدارة موارد بشرية متكاملة | تتبع الوقت والغياب والجداول", description: "تتبع أوقات الحضور وإدارة الغياب والجداول لفريقك بالكامل." },
    documents: { title: "خزانة رقمية آمنة | إدارة مستندات متوافقة", description: "خزّن مستندات الموارد البشرية بأمان مع صلاحيات وصول وسجل تدقيق." },
    comptabilite: { title: "رواتب آلية ومتوافقة | كشوف رواتب مولّدة", description: "رواتب متعددة الدول مع خصومات ضريبية واجتماعية آلية." },
    marketing: { title: "تسويق رقمي متكامل | البريد الإلكتروني والرسائل ووسائل التواصل", description: "خطط وانشر حملاتك التسويقية بنقرة واحدة." },
    integrations: { title: "التكاملات والموصلات | ليوباردو HR", description: "اربط ليوباردو HR بأدواتك وواجهات برمجية API." },
    pricing: { title: "أسعار شفافة | خطط مرنة", description: "خطط شهرية بسيطة لـ5 إلى 250 موظفاً مع تجربة 30 يوماً." },
    about: { title: "من نحن | مهمتنا وفريقنا", description: "تعرّف على الفريق وراء منصة الموارد البشرية للشركات الصغيرة." },
    blog: { title: "المدونة والموارد | أدلة ونصائح الموارد البشرية", description: "نصائح عملية في الموارد البشرية للفرق الميدانية والشركات النامية." },
    changelog: { title: "سجل الإصدارات | ليوباردو HR", description: "كل تحديثات المنتج والإصلاحات والميزات الجديدة." },
    docs: { title: "توثيق API | أدلة ليوباردو HR التقنية", description: "توثيق تقني للمطورين والشركاء." },
    download: { title: "حمّل ليوباردو HR | ويندوز وماك وأندرويد وiOS", description: "احصل على تطبيقات ليوباردو HR على جميع أجهزتك." },
    contact: { title: "اتصل بنا | مبيعات ودعم ليوباردو HR", description: "فريقنا يرد خلال 24 ساعة." },
    guideRhStartup: { title: "دليل الموارد البشرية الكامل للشركات الناشئة | تحميل", description: "دليل عملي مجاني للموارد البشرية للشركات الناشئة." },
    guidePlanningEmployes: { title: "قالب تخطيط الموظفين | تحميل Excel", description: "قالب Excel مجاني لتخطيط جداول موظفيك." },
    guideChecklistPaie: { title: "قائمة رواتب 2026 | تحميل مجاني", description: "قائمة التحقق الأساسية للرواتب لعام 2026." },
    guides: { title: "أدلة وموارد الموارد البشرية | تحميلات مجانية", description: "أدلة وقوالب وموارد لفرق الموارد البشرية." },
    demo: { title: "اطلب عرضاً توضيحياً لليوباردو HR", description: "احجز عرضاً مجانياً. اكتشف إدارة الموارد البشرية الآلية: رواتب متعددة الدول، تتبع الوقت، الغياب، التدريب." },
    faq: { title: "الأسئلة الشائعة | أسئلة ليوباردو HR", description: "إجابات حول الأسعار والتجربة المجانية والأمان والتكاملات والدعم." },
    testimonials: { title: "آراء العملاء | تقييمات ليوباردو HR", description: "ماذا يقول مدراء الموارد البشرية وأصحاب الأعمال عن ليوباردو HR." },
    caseStudies: { title: "دراسات الحالة | قصص نجاح ليوباردو HR", description: "أمثلة واقعية لشركات تستخدم ليوباردو HR في الميدان." },
    videos: { title: "فيديوهات وعروض | ليوباردو HR عملياً", description: "دروس وعروض: إعداد ZKTeco، الرواتب متعددة الدول." },
    branding: { title: "العلامة التجارية والتخصيص | ليوباردو HR متعدد المستأجرين", description: "خصص الألوان والشعارات لكل مستأجر." },
    careers: { title: "الوظائف | انضم إلى فريق ليوباردو HR", description: "اعمل معنا على نظام التشغيل المؤسسي للهاتف المحمول." },
    mobile: { title: "تطبيقات الجوال | ليوباردو HR على أندرويد وiOS", description: "تطبيقات الموظف والمدير ومسؤول المنصة." },
    signup: { title: "تجربة مجانية موجّهة | اكتشف ليوباردو HR", description: "ابدأ تجربتك الموجّهة دون بطاقة ائتمانية." },
    checkout: { title: "اختر خطتك | اشتراك ليوباردو HR", description: "اختر الخطة المناسبة لشركتك." },
  },
};

/** Localise title/description d'une page (fallback FR). */
export function localizedPageMetadata(
  key: keyof typeof pageMetadata,
  lang?: string | null,
): { title: string; description: string } {
  if (lang && lang !== "fr" && pageMetadataLocalized[lang as "en" | "tr" | "ar"]?.[key as string]) {
    return pageMetadataLocalized[lang as "en" | "tr" | "ar"][key as string] as { title: string; description: string };
  }
  const base = pageMetadata[key];
  return { title: base.title, description: base.description };
}

/**
 * Structured Data (JSON-LD)
 */

export function generateOrganizationSchema() {
  return {
    "@context": "https://schema.org",
    "@type": "Organization",
    name: siteName,
    url: siteUrl,
    logo: `${siteUrl}/logo.png`,
    description: "Plateforme complète de gestion RH pour PME et startups",
    sameAs: [
      "https://x.com/leopardo_hr",
      "https://linkedin.com/company/leopardo",
      "https://www.facebook.com/leopardo_hr",
    ],
    contactPoint: {
      "@type": "ContactPoint",
      contactType: "Customer Support",
      email: "support@leopardo-rh.com",
      availableLanguage: ["fr", "en"],
    },
  };
}

export function generateProductSchema(productName: string, description: string) {
  return {
    "@context": "https://schema.org",
    "@type": "SoftwareApplication",
    name: productName,
    description: description,
    applicationCategory: "BusinessApplication",
    operatingSystem: "Web",
    offers: {
      "@type": "Offer",
      price: "29",
      priceCurrency: "EUR",
      availability: "https://schema.org/InStock",
    },
    aggregateRating: {
      "@type": "AggregateRating",
      ratingValue: "4.9",
      ratingCount: "500",
    },
  };
}

export function generateFAQSchema(
  faqs: Array<{ question: string; answer: string }>
) {
  return {
    "@context": "https://schema.org",
    "@type": "FAQPage",
    mainEntity: faqs.map((faq) => ({
      "@type": "Question",
      name: faq.question,
      acceptedAnswer: {
        "@type": "Answer",
        text: faq.answer,
      },
    })),
  };
}

export function generateReviewSchema(
  author: string,
  rating: number,
  reviewText: string
) {
  return {
    "@context": "https://schema.org",
    "@type": "Review",
    reviewRating: {
      "@type": "Rating",
      ratingValue: rating.toString(),
      bestRating: "5",
    },
    author: {
      "@type": "Person",
      name: author,
    },
    reviewBody: reviewText,
  };
}

export function generateBreadcrumbSchema(
  items: Array<{ name: string; url: string }>
) {
  return {
    "@context": "https://schema.org",
    "@type": "BreadcrumbList",
    itemListElement: items.map((item, index) => ({
      "@type": "ListItem",
      position: index + 1,
      name: item.name,
      item: item.url,
    })),
  };
}

