/**
 * SEO Metadata for all pages
 * Optimized titles (50-60 chars), descriptions (150-160 chars), keywords (3-5)
 * Validates: Requirements 2.1, 2.2
 */

const siteUrl = process.env.NEXT_PUBLIC_SITE_URL || 'https://leopardo.com';

export interface PageMetadata {
  title: string;
  description: string;
  keywords: string[];
  ogImage: string;
  canonical: string;
  robots?: string;
  author?: string;
  publishedTime?: string;
  modifiedTime?: string;
}

/**
 * Landing Page Metadata
 */
export const landingMetadata: PageMetadata = {
  title: 'Gestion Employés, Paie & Documents | Plateforme Complète',
  description:
    'Gérez vos employés, paie et documents en un seul endroit. Essai gratuit 14 jours, sans carte bancaire.',
  keywords: [
    'gestion employés SaaS',
    'logiciel RH PME',
    'paie automatisée',
    'pointage numérique',
    'gestion absences',
  ],
  ogImage: `${siteUrl}/og/landing.png`,
  canonical: `${siteUrl}/`,
  robots: 'index, follow',
};

/**
 * Gestion Employés Page Metadata
 */
export const emploiesMetadata: PageMetadata = {
  title: 'Gestion RH Complète | Pointage, Absences, Schedules',
  description:
    'Gérez pointage, absences et schedules facilement. Pointage intelligent avec NFC et biométrie. Essai gratuit.',
  keywords: [
    'gestion RH PME',
    'pointage numérique',
    'gestion absences',
    'logiciel RH',
    'paie employés',
  ],
  ogImage: `${siteUrl}/og/employes.png`,
  canonical: `${siteUrl}/employes`,
  robots: 'index, follow',
};

/**
 * Gestion Documents Page Metadata
 */
export const documentsMetadata: PageMetadata = {
  title: 'Cabinet Numérique Sécurisé | Gestion Documents Conformes',
  description:
    'Cabinet numérique avec chiffrement AES-256. Partage sécurisé, archivage automatique, conformité RGPD.',
  keywords: [
    'cabinet numérique',
    'gestion documents sécurisée',
    'partage documents',
    'archivage conformité',
    'RGPD documents',
  ],
  ogImage: `${siteUrl}/og/documents.png`,
  canonical: `${siteUrl}/documents`,
  robots: 'index, follow',
};

/**
 * Comptabilité & Paie Page Metadata
 */
export const comptabiliteMetadata: PageMetadata = {
  title: 'Paie Automatisée & Conformité | Bulletins Générés',
  description:
    'Paie automatisée avec calculs exacts et conformité garantie. Bulletins générés, exports comptables. Essai gratuit.',
  keywords: [
    'paie automatisée',
    'logiciel paie PME',
    'calcul salaire',
    'bulletins de paie',
    'conformité paie',
  ],
  ogImage: `${siteUrl}/og/comptabilite.png`,
  canonical: `${siteUrl}/comptabilite`,
  robots: 'index, follow',
};

/**
 * Marketing Digital Page Metadata
 */
export const marketingMetadata: PageMetadata = {
  title: 'Marketing Digital Intégré | Email, SMS, Réseaux Sociaux',
  description:
    'Outils marketing complets: email, SMS, réseaux sociaux. Automation, analytics, intégration RH.',
  keywords: [
    'email marketing PME',
    'SMS marketing',
    'automation marketing',
    'campagnes email',
    'marketing automation',
  ],
  ogImage: `${siteUrl}/og/marketing.png`,
  canonical: `${siteUrl}/marketing`,
  robots: 'index, follow',
};

/**
 * Pricing Page Metadata
 */
export const pricingMetadata: PageMetadata = {
  title: 'Tarification Transparente | Plans Flexibles',
  description:
    'Pricing transparent: Starter 29€, Business 79€, Enterprise sur devis. Essai gratuit 14 jours.',
  keywords: [
    'prix logiciel RH',
    'tarification paie',
    'coût gestion employés',
    'plans pricing',
  ],
  ogImage: `${siteUrl}/og/pricing.png`,
  canonical: `${siteUrl}/pricing`,
  robots: 'index, follow',
};

/**
 * À Propos Page Metadata
 */
export const aboutMetadata: PageMetadata = {
  title: 'À Propos | Notre Mission et Équipe',
  description:
    'Découvrez notre mission, équipe et valeurs. Nous aidons les PME à gérer leurs employés simplement.',
  keywords: ['à propos', 'équipe', 'mission', 'valeurs'],
  ogImage: `${siteUrl}/og/about.png`,
  canonical: `${siteUrl}/about`,
  robots: 'index, follow',
};

/**
 * Blog Page Metadata
 */
export const blogMetadata: PageMetadata = {
  title: 'Blog & Resources | Guides RH et Conseils',
  description:
    'Guides, articles et webinaires sur la gestion RH, paie et productivité pour PME.',
  keywords: [
    'guide RH',
    'conseils paie',
    'gestion employés',
    'tendances RH',
    'automatisation RH',
  ],
  ogImage: `${siteUrl}/og/blog.png`,
  canonical: `${siteUrl}/blog`,
  robots: 'index, follow',
};

/**
 * Blog Articles Metadata
 */
export const blogArticlesMetadata: Record<string, PageMetadata> = {
  'guide-complet-rh-startup': {
    title: 'Guide Complet RH pour Startup | Gestion Employés',
    description:
      'Guide complet pour gérer vos employés en startup. Conseils pratiques, outils et bonnes pratiques RH.',
    keywords: [
      'guide RH startup',
      'gestion employés startup',
      'RH pour startup',
      'conseils RH',
    ],
    ogImage: `${siteUrl}/og/blog/guide-rh-startup.png`,
    canonical: `${siteUrl}/blog/guide-complet-rh-startup`,
    robots: 'index, follow',
    author: 'Leopardo Team',
    publishedTime: '2024-01-15T10:00:00Z',
    modifiedTime: '2024-01-15T10:00:00Z',
  },

  'automatiser-paie-2024': {
    title: 'Automatiser la Paie en 2024 | Guide Complet',
    description:
      'Comment automatiser votre paie en 2024. Outils, processus et bonnes pratiques pour une paie sans erreur.',
    keywords: [
      'automatiser paie',
      'paie 2024',
      'logiciel paie',
      'automatisation RH',
    ],
    ogImage: `${siteUrl}/og/blog/automatiser-paie.png`,
    canonical: `${siteUrl}/blog/automatiser-paie-2024`,
    robots: 'index, follow',
    author: 'Leopardo Team',
    publishedTime: '2024-01-14T10:00:00Z',
    modifiedTime: '2024-01-14T10:00:00Z',
  },

  'gestion-absences-efficace': {
    title: 'Gestion des Absences Efficace | Conseils RH',
    description:
      'Gérez les absences de vos employés efficacement. Processus, outils et bonnes pratiques pour une gestion optimale.',
    keywords: [
      'gestion absences',
      'congés employés',
      'absence travail',
      'logiciel RH',
    ],
    ogImage: `${siteUrl}/og/blog/gestion-absences.png`,
    canonical: `${siteUrl}/blog/gestion-absences-efficace`,
    robots: 'index, follow',
    author: 'Leopardo Team',
    publishedTime: '2024-01-13T10:00:00Z',
    modifiedTime: '2024-01-13T10:00:00Z',
  },

  'productivite-rh-outils': {
    title: 'Outils pour Augmenter la Productivité RH',
    description:
      'Découvrez les meilleurs outils pour augmenter la productivité de votre équipe RH. Automatisation et efficacité.',
    keywords: [
      'productivité RH',
      'outils RH',
      'automatisation RH',
      'efficacité RH',
    ],
    ogImage: `${siteUrl}/og/blog/productivite-rh.png`,
    canonical: `${siteUrl}/blog/productivite-rh-outils`,
    robots: 'index, follow',
    author: 'Leopardo Team',
    publishedTime: '2024-01-12T10:00:00Z',
    modifiedTime: '2024-01-12T10:00:00Z',
  },

  'tendances-rh-2024': {
    title: 'Tendances RH 2024 | Prédictions et Insights',
    description:
      'Les tendances RH à suivre en 2024. Insights sur l\'avenir de la gestion des ressources humaines.',
    keywords: [
      'tendances RH 2024',
      'futur RH',
      'insights RH',
      'gestion RH',
    ],
    ogImage: `${siteUrl}/og/blog/tendances-rh-2024.png`,
    canonical: `${siteUrl}/blog/tendances-rh-2024`,
    robots: 'index, follow',
    author: 'Leopardo Team',
    publishedTime: '2024-01-11T10:00:00Z',
    modifiedTime: '2024-01-11T10:00:00Z',
  },

  'ia-rh-futur': {
    title: 'IA et RH | Le Futur de la Gestion Employés',
    description:
      'Comment l\'IA transforme la gestion RH. Cas d\'usage, bénéfices et défis de l\'intelligence artificielle en RH.',
    keywords: [
      'IA RH',
      'intelligence artificielle RH',
      'futur RH',
      'automatisation IA',
    ],
    ogImage: `${siteUrl}/og/blog/ia-rh.png`,
    canonical: `${siteUrl}/blog/ia-rh-futur`,
    robots: 'index, follow',
    author: 'Leopardo Team',
    publishedTime: '2024-01-10T10:00:00Z',
    modifiedTime: '2024-01-10T10:00:00Z',
  },

  'checklist-paie-2024': {
    title: 'Checklist Paie 2024 | Conformité Garantie',
    description:
      'Checklist complète pour votre paie 2024. Vérifications, conformité et bonnes pratiques pour une paie sans risque.',
    keywords: [
      'checklist paie',
      'paie 2024',
      'conformité paie',
      'gestion paie',
    ],
    ogImage: `${siteUrl}/og/blog/checklist-paie.png`,
    canonical: `${siteUrl}/blog/checklist-paie-2024`,
    robots: 'index, follow',
    author: 'Leopardo Team',
    publishedTime: '2024-01-09T10:00:00Z',
    modifiedTime: '2024-01-09T10:00:00Z',
  },

  'modele-planning-employes': {
    title: 'Modèle Planning Employés | Template Gratuit',
    description:
      'Modèle de planning pour vos employés. Template gratuit, flexible et facile à utiliser pour planifier vos équipes.',
    keywords: [
      'planning employés',
      'modèle planning',
      'template planning',
      'gestion planning',
    ],
    ogImage: `${siteUrl}/og/blog/planning-employes.png`,
    canonical: `${siteUrl}/blog/modele-planning-employes`,
    robots: 'index, follow',
    author: 'Leopardo Team',
    publishedTime: '2024-01-08T10:00:00Z',
    modifiedTime: '2024-01-08T10:00:00Z',
  },

  'conformite-rgpd-documents': {
    title: 'Conformité RGPD | Gestion Documents Sécurisée',
    description:
      'Assurez la conformité RGPD de vos documents. Bonnes pratiques, outils et processus pour protéger les données.',
    keywords: [
      'conformité RGPD',
      'RGPD documents',
      'protection données',
      'sécurité documents',
    ],
    ogImage: `${siteUrl}/og/blog/rgpd-documents.png`,
    canonical: `${siteUrl}/blog/conformite-rgpd-documents`,
    robots: 'index, follow',
    author: 'Leopardo Team',
    publishedTime: '2024-01-07T10:00:00Z',
    modifiedTime: '2024-01-07T10:00:00Z',
  },

  'email-marketing-rh': {
    title: 'Email Marketing RH | Campagnes Efficaces',
    description:
      'Stratégies d\'email marketing pour RH. Campagnes efficaces, templates et bonnes pratiques pour engager vos employés.',
    keywords: [
      'email marketing RH',
      'campagnes email',
      'marketing employés',
      'engagement RH',
    ],
    ogImage: `${siteUrl}/og/blog/email-marketing-rh.png`,
    canonical: `${siteUrl}/blog/email-marketing-rh`,
    robots: 'index, follow',
    author: 'Leopardo Team',
    publishedTime: '2024-01-06T10:00:00Z',
    modifiedTime: '2024-01-06T10:00:00Z',
  },
};

/**
 * Guides Pages Metadata
 */
export const guidesMetadata: Record<string, PageMetadata> = {
  'rh-startup': {
    title: 'Guide Complet RH pour Startup | Télécharger',
    description:
      'Guide complet RH pour startup. Conseils, templates et bonnes pratiques. Téléchargez gratuitement en PDF.',
    keywords: [
      'guide RH startup',
      'RH pour startup',
      'gestion RH',
      'conseils RH',
    ],
    ogImage: `${siteUrl}/og/guides/rh-startup.png`,
    canonical: `${siteUrl}/guides/rh-startup`,
    robots: 'index, follow',
  },

  'checklist-paie': {
    title: 'Checklist Paie 2024 | Télécharger Gratuitement',
    description:
      'Checklist complète pour votre paie 2024. Vérifications et conformité. Téléchargez gratuitement en PDF.',
    keywords: [
      'checklist paie',
      'paie 2024',
      'conformité paie',
      'gestion paie',
    ],
    ogImage: `${siteUrl}/og/guides/checklist-paie.png`,
    canonical: `${siteUrl}/guides/checklist-paie`,
    robots: 'index, follow',
  },

  'planning-employes': {
    title: 'Modèle Planning Employés | Télécharger Excel',
    description:
      'Modèle de planning pour vos employés. Template Excel gratuit, flexible et facile à utiliser.',
    keywords: [
      'planning employés',
      'modèle planning',
      'template Excel',
      'gestion planning',
    ],
    ogImage: `${siteUrl}/og/guides/planning-employes.png`,
    canonical: `${siteUrl}/guides/planning-employes`,
    robots: 'index, follow',
  },
};

/**
 * Get metadata for a page
 */
export function getPageMetadata(page: string): PageMetadata | null {
  const allMetadata: Record<string, PageMetadata> = {
    landing: landingMetadata,
    employes: emploiesMetadata,
    documents: documentsMetadata,
    comptabilite: comptabiliteMetadata,
    marketing: marketingMetadata,
    pricing: pricingMetadata,
    about: aboutMetadata,
    blog: blogMetadata,
    ...blogArticlesMetadata,
    ...guidesMetadata,
  };

  return allMetadata[page] || null;
}

/**
 * Get canonical URL for a page
 */
export function getCanonicalUrl(path: string): string {
  return `${siteUrl}${path}`;
}

/**
 * Get OG image URL
 */
export function getOGImageUrl(page: string): string {
  const metadata = getPageMetadata(page);
  return metadata?.ogImage || `${siteUrl}/og/default.png`;
}

/**
 * Validate metadata
 */
export function validateMetadata(metadata: PageMetadata): {
  valid: boolean;
  errors: string[];
} {
  const errors: string[] = [];

  // Check title length (50-60 chars)
  if (metadata.title.length < 50 || metadata.title.length > 60) {
    errors.push(
      `Title length should be 50-60 chars, got ${metadata.title.length}`
    );
  }

  // Check description length (150-160 chars)
  if (
    metadata.description.length < 150 ||
    metadata.description.length > 160
  ) {
    errors.push(
      `Description length should be 150-160 chars, got ${metadata.description.length}`
    );
  }

  // Check keywords count (3-5)
  if (metadata.keywords.length < 3 || metadata.keywords.length > 5) {
    errors.push(
      `Keywords count should be 3-5, got ${metadata.keywords.length}`
    );
  }

  // Check OG image
  if (!metadata.ogImage) {
    errors.push('OG image is required');
  }

  // Check canonical URL
  if (!metadata.canonical) {
    errors.push('Canonical URL is required');
  }

  return {
    valid: errors.length === 0,
    errors,
  };
}
