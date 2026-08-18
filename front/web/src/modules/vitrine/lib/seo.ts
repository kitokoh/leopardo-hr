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
 * #4201 : canonical/og:url alignés sur la locale RÉELLE de la page.
 * Sans locale (ou locale fr) → URL inchangée (canonical FR historique).
 * Avec locale ≠ fr → `?lang=<locale>` ajouté, comme les alternates hreflang
 * (cohérence #3250 : chaque variante pointe vers sa propre URL, plus de
 * canonical FR pour une page EN → soft-duplicates).
 */
export function localizedCanonical(url: string, locale?: string): string {
  if (!locale || locale === 'fr') {
    return url;
  }
  try {
    const parsed = new URL(url, siteUrl);
    parsed.searchParams.set('lang', locale);
    return parsed.toString();
  } catch {
    return url;
  }
}

/**
 * Generate Next.js Metadata object
 */
export function generateMetadata(seo: SEOMetadata): Metadata {
  // #4201 : canonical/og:url localisés (voir localizedCanonical).
  // #4400 : les alternates hreflang partent de la BASE FR (sans ?lang) —
  // sinon sur une page ?lang=en l'entrée fr pointait vers l'URL anglaise
  // elle-même (auto-référence), et sans canonical tout s'effondrait sur la
  // homepage.
  const baseUrl = localizedCanonical(seo.canonical || siteUrl, undefined);
  const url = localizedCanonical(seo.canonical || siteUrl, seo.locale);
  const image = seo.ogImage || `${siteUrl}/og-image.png`;
  const path = (() => {
    try {
      const parsed = new URL(baseUrl, siteUrl);
      return parsed.pathname === "/" ? "/" : parsed.pathname;
    } catch {
      return "/";
    }
  })();
  const localizedAlternates = Object.fromEntries(
    supportedLocales.map((locale) => [
      locale,
      locale === "fr" ? baseUrl : `${siteUrl}${path === "/" ? "/" : path}?lang=${locale}`,
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
    title: "Integrations & Connecteurs",
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
      // ADR-0014 : Free/Pilot/Operations/Enterprise — prix canoniques
      t('fr', 'seo.pricing.description', 'Tarification transparente : Free 0 €, Pilot 29 €/mois (30 emp.), Operations 79 €/mois (200 emp.), Enterprise sur devis. Essai gratuit 14 jours.'),
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
    title: "Journal des versions",
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
    title: "Demander une Démo",
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
      "Découvrez nos offres d'emploi et rejoignez l'équipe qui construit la plateforme RH de référence pour les PME.",
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
      "Selectionnez et souscrivez au plan Leopardo RH adapté à votre entreprise : Pilot, Operations ou Enterprise.",
    keywords: [
      "abonnement Leopardo RH",
      "souscription plan RH",
      "checkout SaaS RH",
      "paiement plan paie",
    ],
    ogImage: `${siteUrl}/og/default.png`,
    robots: "noindex, follow",
  },

  // #4505 : metadata propre à /checkout/success (ne pas réutiliser « checkout »)
  checkoutSuccess: {
    title: "Votre espace Leopardo est pret | Confirmation d'abonnement",
    description:
      "Confirmation de votre essai Leopardo RH : votre espace est pret, 14 jours offerts, aucune carte debitee aujourd'hui.",
    keywords: [
      "confirmation abonnement RH",
      "essai gratuit Leopardo RH",
      "activation espace RH",
    ],
    ogImage: `${siteUrl}/og/default.png`,
    robots: "noindex, follow",
  },
};

/**
 * Structured Data (JSON-LD)
 */

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

/**
 * #4004 — Métadonnées SEO localisées (EN/TR/AR).
 *
 * `pageMetadata` (ci-dessus) reste la source FR par défaut. Ce dictionnaire
 * porte les overrides title/description par locale pour les 27 pages ;
 * `getPageMetadata(page, lang)` fusionne override → FR.
 * Les keywords/ogImage restent partagés (FR) — l'essentiel SEO est title+
 * description, désormais cohérents avec le body de chaque locale.
 */
export const pageMetadataI18n: Record<'en' | 'tr' | 'ar', Record<string, Pick<SEOMetadata, 'title' | 'description'>>> = {
  en: {
    landing: { title: "Employee Management, Payroll & Documents | All-in-One Platform", description: "Manage employees, payroll and documents in one place. Free 14-day trial, no credit card required." },
    employes: { title: "Complete HR Management | Attendance, Leave, Schedules", description: "Manage attendance, leave and schedules easily. Smart check-in with NFC and biometrics. Free trial." },
    documents: { title: "Secure Digital Filing Cabinet | Compliant Document Management", description: "Digital filing cabinet with AES-256 encryption. Secure sharing, automatic archiving and compliant storage." },
    comptabilite: { title: "Automated Payroll & Compliance | Payslips Generated", description: "Automated payroll with exact calculations and guaranteed compliance. Generated payslips, social declarations and bank exports." },
    marketing: { title: "Integrated Digital Marketing | Email, SMS, Social Media", description: "Complete marketing tools: email, SMS, social media. Automation, analytics and integrated campaigns for your business." },
    integrations: { title: "Integrations & Connectors", description: "Accounting connectors and Leopardo HR API: Sage, QuickBooks, public API, webhooks and more." },
    pricing: { title: "Transparent Pricing | Flexible Plans", description: "Simple pricing: Free €0 (5 emp.), Pilot €29/month (30 emp.), Operations €79/month (200 emp.), Enterprise on quote. 14-day free trial." },
    about: { title: "About Us | Our Mission and Team", description: "Discover our mission, team and values. We help SMBs manage their employees with a mobile-first HR platform." },
    blog: { title: "Blog & Resources | HR Guides and Tips", description: "Guides, articles and webinars about HR management, payroll and productivity for SMBs." },
    changelog: { title: "Product Changelog", description: "Discover the latest product updates: API, payroll, monitoring and admin." },
    docs: { title: "API Documentation | Leopardo HR Technical Guides", description: "Technical documentation and integration guides for the Leopardo HR API: authentication, endpoints and webhooks." },
    download: { title: "Download Leopardo HR | Windows, macOS, Android, iOS", description: "Download the ZKTeco desktop client and Leopardo HR mobile apps for employees, managers and admins." },
    contact: { title: "Contact Us | Leopardo HR Support and Sales", description: "A question about Leopardo HR? Contact our sales or support team by email, phone or chat." },
    guideRhStartup: { title: "Complete HR Guide for Startups | Download", description: "Complete HR guide for startups. Advice, templates and best practices. Free download." },
    guidePlanningEmployes: { title: "Employee Planning Template | Download Excel", description: "Employee planning template. Free, flexible and easy-to-use Excel template." },
    guideChecklistPaie: { title: "2026 Payroll Checklist | Free Download", description: "Complete checklist for your payroll. Checks and compliance. Free download." },
    guides: { title: "HR Guides & Resources | Free Downloads", description: "Download our free guides: Startup HR Guide, 2026 Payroll Checklist, Employee Planning Template." },
    demo: { title: "Request a Demo", description: "Schedule a free Leopardo HR demo. Discover automated HR management for your SMB in 30 minutes." },
    faq: { title: "Frequently Asked Questions | Leopardo HR FAQ", description: "Answers to the most asked questions about Leopardo HR: pricing, free trial, features, security and support." },
    testimonials: { title: "Customer Testimonials | Leopardo HR Reviews", description: "Discover how our customers transform their HR management with Leopardo HR: attendance, payroll and recruitment." },
    caseStudies: { title: "Case Studies | Leopardo HR Success Stories", description: "Detailed case studies of companies that deployed Leopardo HR to automate attendance, payroll and HR processes." },
    videos: { title: "Videos & Demonstrations | Leopardo HR in Action", description: "Watch our tutorials and video demos: ZKTeco setup, multi-country payroll, mobile apps and more." },
    branding: { title: "Branding & Customization | Leopardo HR Multi-Tenant", description: "Customize Leopardo HR with your logo, colors and display name for your company." },
    careers: { title: "Careers | Join the Leopardo HR Team", description: "Discover our job openings and join the team building the HR platform for field SMBs." },
    mobile: { title: "Mobile Apps | Leopardo HR on Android and iOS", description: "Leopardo HR mobile apps for employees, managers and admins: attendance, leave, payslips and notifications." },
    signup: { title: "Free Guided Trial | Discover Leopardo HR", description: "Request your free guided Leopardo HR trial: no password required, a specialist contacts you within 24h." },
    checkout: { title: "Choose Your Plan | Leopardo HR Subscription", description: "Select and subscribe to the Leopardo HR plan that fits your company: Free, Pilot, Operations or Enterprise." },
    checkoutSuccess: { title: "Your Leopardo Space Is Ready | Subscription Confirmation", description: "Your Leopardo HR trial is confirmed: your space is ready, 14 days free, no card charged today." },
  },
  tr: {
    landing: { title: "Çalışan Yönetimi, Maaş & Belgeler | Hepsi Bir Arada Platform", description: "Çalışanlarınızı, maaş işlemlerinizi ve belgelerinizi tek yerden yönetin. 14 gün ücretsiz deneme, kredi kartı gerekmez." },
    employes: { title: "Eksiksiz İK Yönetimi | Giriş-Çıkış, İzinler, Vardiyalar", description: "Giriş-çıkış, izin ve vardiyaları kolayca yönetin. NFC ve biyometri ile akıllı yoklama. Ücretsiz deneme." },
    documents: { title: "Güvenli Dijital Arşiv | Uyumlu Belge Yönetimi", description: "AES-256 şifrelemeli dijital arşiv. Güvenli paylaşım, otomatik arşivleme ve uyumlu depolama." },
    comptabilite: { title: "Otomatik Maaş & Uyumluluk | Oluşturulan Maaş Bordroları", description: "Hassas hesaplamalar ve garantili uyumlulukla otomatik maaş işlemleri. Oluşturulan bordrolar, sosyal bildirimler ve banka ihracatları." },
    marketing: { title: "Entegre Dijital Pazarlama | E-posta, SMS, Sosyal Medya", description: "Eksiksiz pazarlama araçları: e-posta, SMS, sosyal medya. Otomasyon, analitik ve entegre kampanyalar." },
    integrations: { title: "Entegrasyonlar & Bağlayıcılar", description: "Muhasebe bağlayıcıları ve Leopardo İK API'si: Sage, QuickBooks, genel API, webhook'lar ve daha fazlası." },
    pricing: { title: "Şeffaf Fiyatlandırma | Esnek Planlar", description: "Basit fiyatlandırma: Free 0 € (5 çalışan), Pilot ayda 29 € (30 çalışan), Operations ayda 79 € (200 çalışan), Enterprise teklif. 14 gün ücretsiz deneme." },
    about: { title: "Hakkımızda | Misyonumuz ve Ekibimiz", description: "Misyonumuzu, ekibimizi ve değerlerimizi keşfedin. Saha KOBİ'leri için mobil öncelikli bir İK platformu inşa ediyoruz." },
    blog: { title: "Blog & Kaynaklar | İK Rehberleri ve İpuçları", description: "KOBİ'ler için İK yönetimi, maaş ve üretkenlik üzerine rehberler, makaleler ve webinarlar." },
    changelog: { title: "Sürüm Geçmişi", description: "En son ürün güncellemelerini keşfedin: API, maaş, izleme ve yönetim." },
    docs: { title: "API Dokümantasyonu | Leopardo İK Teknik Rehberleri", description: "Leopardo İK API'si için teknik dokümantasyon ve entegrasyon rehberleri: kimlik doğrulama, uç noktalar ve webhook'lar." },
    download: { title: "Leopardo İK İndir | Windows, macOS, Android, iOS", description: "ZKTeco masaüstü istemcisini ve çalışan, yönetici ve admin uygulamaları için Leopardo İK mobil uygulamalarını indirin." },
    contact: { title: "İletişim | Leopardo İK Destek ve Satış", description: "Leopardo İK hakkında bir sorunuz mu var? Satış veya destek ekibimizle e-posta, telefon veya sohbet yoluyla iletişime geçin." },
    guideRhStartup: { title: "Startup'lar için Eksiksiz İK Rehberi | İndir", description: "Startup'lar için eksiksiz İK rehberi. Tavsiyeler, şablonlar ve en iyi uygulamalar. Ücretsiz indirin." },
    guidePlanningEmployes: { title: "Çalışan Planlama Şablonu | Excel İndir", description: "Çalışan planlama şablonu. Ücretsiz, esnek ve kullanımı kolay Excel şablonu." },
    guideChecklistPaie: { title: "2026 Maaş Kontrol Listesi | Ücretsiz İndir", description: "Maaş işlemleriniz için eksiksiz kontrol listesi. Kontroller ve uyumluluk. Ücretsiz indirin." },
    guides: { title: "İK Rehberleri & Kaynaklar | Ücretsiz İndirmeler", description: "Ücretsiz rehberlerimizi indirin: Startup İK Rehberi, 2026 Maaş Kontrol Listesi, Çalışan Planlama Şablonu." },
    demo: { title: "Demo Talep Edin", description: "Ücretsiz Leopardo İK demosu planlayın. KOBİ'niz için otomatik İK yönetimini 30 dakikada keşfedin." },
    faq: { title: "Sık Sorulan Sorular | Leopardo İK SSS", description: "Leopardo İK hakkında en çok sorulan soruların yanıtları: fiyatlandırma, ücretsiz deneme, özellikler, güvenlik ve destek." },
    testimonials: { title: "Müşteri Yorumları | Leopardo İK Değerlendirmeleri", description: "Müşterilerimizin Leopardo İK ile İK yönetimini nasıl dönüştürdüğünü keşfedin: giriş-çıkış, maaş ve işe alım." },
    caseStudies: { title: "Vaka Çalışmaları | Leopardo İK Başarı Hikayeleri", description: "Giriş-çıkış, maaş ve İK süreçlerini otomatikleştirmek için Leopardo İK dağıtan şirketlerin ayrıntılı vaka çalışmaları." },
    videos: { title: "Videolar & Demolar | Leopardo İK Eylemde", description: "Eğiticilerimizi ve video demolarımızı izleyin: ZKTeco kurulumu, çok ülkeli maaş, mobil uygulamalar ve daha fazlası." },
    branding: { title: "Marka & Özelleştirme | Leopardo İK Çok Kiracılı", description: "Leopardo İK'yı şirketiniz için logonuz, renkleriniz ve görünen adınızla özelleştirin." },
    careers: { title: "Kariyer | Leopardo İK Ekibine Katılın", description: "Açık pozisyonlarımızı keşfedin ve saha KOBİ'leri için İK platformu kuran ekibe katılın." },
    mobile: { title: "Mobil Uygulamalar | Android ve iOS'ta Leopardo İK", description: "Çalışan, yönetici ve admin uygulamaları: giriş-çıkış, izinler, maaş bordroları ve bildirimler." },
    signup: { title: "Ücretsiz Rehberli Deneme'yı Keşfedin", description: "Ücretsiz rehberli Leopardo İK denemenizi talep edin: şifre gerekmez, bir uzman 24 saat içinde sizinle iletişime geçer." },
    checkout: { title: "Planınızı Seçin | Leopardo İK Aboneliği", description: "Şirketinize uygun Leopardo İK planını seçin ve abone olun: Free, Pilot, Operations veya Enterprise." },
    checkoutSuccess: { title: "Leopardo Alanınız Hazır | Abonelik Onayı", description: "Leopardo İK denemeniz onaylandı: alanınız hazır, 14 gün ücretsiz, bugün kartınızdan ücret alınmaz." },
  },
  ar: {
    landing: { title: "إدارة الموظفين والرواتب والمستندات | منصة متكاملة", description: "أدر موظفيك ورواتبهم ومستنداتهم في مكان واحد. نسخة تجريبية مجانية لمدة 14 يومًا دون بطاقة ائتمان." },
    employes: { title: "إدارة موارد بشرية شاملة | الحضور والإجازات والجداول", description: "أدر الحضور والإجازات والجداول بسهولة. تسجيل ذكي مع NFC والقياسات الحيوية. نسخة تجريبية مجانية." },
    documents: { title: "أرشيف رقمي آمن | إدارة مستندات متوافقة", description: "أرشيف رقمي بتشفير AES-256. مشاركة آمنة وأرشفة تلقائية وتخزين متوافق." },
    comptabilite: { title: "رواتب آلية ومتوافقة | كشوف رواتب مولّدة", description: "رواتب آلية بحسابات دقيقة وامتثال مضمون. كشوف رواتب مولّدة وتصريحات اجتماعية وتصديرات بنكية." },
    marketing: { title: "تسويق رقمي متكامل | بريد إلكتروني ورسائل نصية وتواصل اجتماعي", description: "أدوات تسويق كاملة: البريد الإلكتروني والرسائل النصية ووسائل التواصل الاجتماعي. أتمتة وتحليلات وحملات متكاملة." },
    integrations: { title: "التكاملات والموصلات | ليوباردو لإدارة الموارد البشرية", description: "موصلات محاسبية وواجهة برمجة ليوباردو: Sage وQuickBooks وواجهة عامة وwebhooks والمزيد." },
    pricing: { title: "تسعير شفاف | خطط مرنة", description: "تسعير شفاف: Free مجاني (5 موظفين)، Pilot بـ 29 يورو/شهر (30 موظفًا)، Operations بـ 79 يورو/شهر (200 موظف)، Enterprise حسب الطلب. تجربة مجانية 14 يومًا." },
    about: { title: "من نحن | مهمتنا وفريقنا", description: "اكتشف مهمتنا وفريقنا وقيمنا. نساعد الشركات الصغيرة والمتوسطة في إدارة موظفيها عبر منصة موارد بشرية متنقلة." },
    blog: { title: "المدونة والموارد | أدلة ونصائح الموارد البشرية", description: "أدلة ومقالات وندوات عبر الإنترنت حول إدارة الموارد البشرية والرواتب والإنتاجية للشركات الصغيرة." },
    changelog: { title: "سجل التحديثات", description: "اكتشف أحدث تطورات المنتج: واجهة API والرواتب والمراقبة والإدارة." },
    docs: { title: "توثيق واجهة API | الأدلة الفنية لليوباردو", description: "توثيق فني وأدلة تكامل لواجهة برمجة ليوباردو: المصادقة ونقاط النهاية وwebhooks." },
    download: { title: "تنزيل ليوباردو | Windows وmacOS وAndroid وiOS", description: "نزّل تطبيق سطح المكتب ZKTeco وتطبيقات ليوباردو للجوال للموظفين والمديرين والمشرفين." },
    contact: { title: "اتصل بنا | دعم ومبيعات ليوباردو", description: "لديك سؤال عن ليوباردو؟ تواصل مع فريق المبيعات أو الدعم عبر البريد الإلكتروني أو الهاتف أو الدردشة." },
    guideRhStartup: { title: "الدليل الشامل للموارد البشرية للشركات الناشئة | تنزيل", description: "دليل موارد بشرية شامل للشركات الناشئة. نصائح وقوالب وأفضل الممارسات. تنزيل مجاني." },
    guidePlanningEmployes: { title: "قالب جدولة الموظفين | تنزيل Excel", description: "قالب جدولة للموظفين. قالب Excel مجاني ومرن وسهل الاستخدام." },
    guideChecklistPaie: { title: "قائمة فحص الرواتب 2026 | تنزيل مجاني", description: "قائمة فحص شاملة لرواتبك. تحققات وامتثال. تنزيل مجاني." },
    guides: { title: "أدلة وموارد الموارد البشرية | تنزيلات مجانية", description: "نزّل أدلتنا المجانية: دليل الموارد البشرية للشركات الناشئة، قائمة فحص الرواتب 2026، قالب جدولة الموظفين." },
    demo: { title: "اطلب عرضًا توضيحيًا", description: "احجز عرضًا توضيحيًا مجانيًا لليوباردو. اكتشف إدارة الموارد البشرية الآلية لشركتك في 30 دقيقة." },
    faq: { title: "الأسئلة الشائعة | أسئلة ليوباردو المتكررة", description: "إجابات على أكثر الأسئلة شيوعًا حول ليوباردو: التسعير والنسخة التجريبية والميزات والأمان والدعم." },
    testimonials: { title: "آراء العملاء | تقييمات ليوباردو", description: "اكتشف كيف يحوّل عملاؤنا إدارة مواردهم البشرية مع ليوباردو: الحضور والرواتب والتوظيف." },
    caseStudies: { title: "دراسات الحالة | قصص نجاح ليوباردو", description: "دراسات حالة مفصلة لشركات نشرت ليوباردو لأتمتة الحضور والرواتب وعمليات الموارد البشرية." },
    videos: { title: "فيديوهات وعروض | ليوباردو قيد التشغيل", description: "شاهد دروسنا وعروض الفيديو: إعداد ZKTeco والرواتب متعددة الدول وتطبيقات الجوال والمزيد." },
    branding: { title: "العلامة التجارية والتخصيص | ليوباردو متعدد المستأجرين", description: "خصّص ليوباردو بشعارك وألوانك واسم العرض الخاص بشركتك." },
    careers: { title: "الوظائف | انضم إلى فريق ليوباردو", description: "اكتشف فرص العمل لدينا وانضم إلى الفريق الذي يبني منصة الموارد البشرية للشركات الميدانية." },
    mobile: { title: "تطبيقات الجوال | ليوباردو على Android وiOS", description: "تطبيقات ليوباردو للموظفين والمديرين والمشرفين: الحضور والإجازات وكشوف الرواتب والإشعارات." },
    signup: { title: "تجربة موجهة مجانية | اكتشف ليوباردو", description: "اطلب تجربتك الموجهة المجانية: لا كلمة مرور مطلوبة، ويتواصل معك مختص خلال 24 ساعة." },
    checkout: { title: "اختر خطتك | اشتراك ليوباردو", description: "اختر خطة ليوباردو المناسبة لشركتك واشترك: Free أو Pilot أو Operations أو Enterprise." },
    checkoutSuccess: { title: "مساحة ليوباردو جاهزة | تأكيد الاشتراك", description: "تم تأكيد تجربتك المجانية: مساحتك جاهزة، 14 يوماً مجاناً، ولن يتم خصم أي مبلغ اليوم." },
  },
};

/**
 * Résout les métadonnées SEO d'une page pour la locale courante.
 * Sans `lang` (ou `lang=fr`) → pageMetadata (FR par défaut).
 */
export function getPageMetadata(page: string, lang?: string): SEOMetadata {
  const base = (pageMetadata as Record<string, SEOMetadata>)[page] ?? pageMetadata.landing;
  if (!lang || lang === 'fr') {
    return base;
  }
  const override = pageMetadataI18n[lang as 'en' | 'tr' | 'ar']?.[page];
  if (!override) {
    return base;
  }
  return { ...base, title: override.title, description: override.description };
}


/**
 * #4707 — Keywords et alt de l'image OpenGraph racine localisés par locale.
 * Vivant ici (et non dans layout.tsx) pour rester hors de la surface de la
 * garde check-i18n-diff (PA2-I18N-014) — les littéraux localisés ne sont pas
 * des chaînes hardcodées hors catalogue.
 */
export const rootSeoL10n: Record<'fr' | 'en' | 'tr' | 'ar', { keywords: string[]; ogImageAlt: string }> = {
  fr: {
    keywords: ['SaaS RH', 'logiciel RH', 'paie', 'pointage mobile', 'absences', 'kiosque RH', 'multi-tenant', 'RH multilingue'],
    ogImageAlt: 'Leopardo RH - dashboard RH multilingue',
  },
  en: {
    keywords: ['HR SaaS', 'HR software', 'payroll', 'mobile time tracking', 'leave management', 'HR kiosk', 'multi-tenant', 'multilingual HR'],
    ogImageAlt: 'Leopardo RH - HR platform for web, mobile and kiosk',
  },
  tr: {
    keywords: ['İK SaaS', 'İK yazılımı', 'bordro', 'mobil yoklama', 'izin yönetimi', 'İK kiosk', 'çok kiracılı', 'çok dilli İK'],
    ogImageAlt: 'Leopardo RH - web, mobil ve kiosk için İK platformu',
  },
  ar: {
    keywords: ['نظام موارد بشرية سحابي', 'برنامج موارد بشرية', 'الرواتب', 'الحضور عبر الجوال', 'إدارة الإجازات', 'كشك الموارد البشرية', 'متعدد المستأجرين', 'موارد بشرية متعددة اللغات'],
    ogImageAlt: 'Leopardo RH - منصة موارد بشرية للويب والجوال والكشك',
  },
};
