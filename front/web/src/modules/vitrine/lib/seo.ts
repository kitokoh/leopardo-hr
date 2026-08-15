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
      t('fr', 'seo.pricing.description', 'Tarification transparente : plans Starter 29 €/mois, Business 79 €/mois, Enterprise 199 €/mois. Essai gratuit 14 jours.'),
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
      "Selectionnez et souscrivez au plan Leopardo RH adapte a votre entreprise : Starter, Business ou Enterprise.",
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

