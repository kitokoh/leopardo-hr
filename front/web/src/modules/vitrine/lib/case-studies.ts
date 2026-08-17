/**
 * Études de cas indexées par slug, issues du contenu des modules vitrine
 * (content.ts). Chaque carte de module pointe vers /case-studies/<slug> ;
 * cette table garantit qu'aucun de ces liens ne mène à une 404.
 */

import { modulePageContent } from './content';
import type { AppLocale } from '@/lib/i18n';

export type CaseStudyModule = 'employes' | 'documents' | 'comptabilite' | 'marketing';

export type CaseStudy = {
  slug: string;
  title: string;
  description: string;
  industry: string;
  metrics: Array<{ label: string; value: string }>;
  module: CaseStudyModule;
  moduleLabel: string;
  moduleHref: string;
};

const moduleMeta: Record<CaseStudyModule, { label: string; href: string }> = {
  employes: { label: 'Gestion RH', href: '/employes' },
  documents: { label: 'Documents', href: '/documents' },
  comptabilite: { label: 'Paie & Comptabilité', href: '/comptabilite' },
  marketing: { label: 'Marketing', href: '/marketing' },
};

/**
 * #4703 (audit 360° 2026-08-16) : labels de module localisés — le détail
 * /case-studies/[slug] injecte `moduleLabel` dans des chaînes ui.* déjà
 * traduites ; un label FR cassait l'i18n des pages en/tr/ar.
 */
const moduleLabelsByLocale: Record<AppLocale, Record<CaseStudyModule, string>> = {
  fr: {
    employes: 'Gestion RH',
    documents: 'Documents',
    comptabilite: 'Paie & Comptabilité',
    marketing: 'Marketing',
  },
  en: {
    employes: 'HR Management',
    documents: 'Documents',
    comptabilite: 'Payroll & Accounting',
    marketing: 'Marketing',
  },
  tr: {
    employes: 'İK Yönetimi',
    documents: 'Belgeler',
    comptabilite: 'Maaş & Muhasebe',
    marketing: 'Pazarlama',
  },
  ar: {
    employes: 'إدارة الموارد البشرية',
    documents: 'المستندات',
    comptabilite: 'الرواتب والمحاسبة',
    marketing: 'التسويق',
  },
};

export function getModuleLabel(module: CaseStudyModule, locale: AppLocale): string {
  return moduleLabelsByLocale[locale]?.[module] ?? moduleLabelsByLocale.fr[module];
}

function toSlug(link: string): string {
  return link.replace(/^\/case-studies\//, '');
}

export function getAllCaseStudies(): CaseStudy[] {
  const studies: CaseStudy[] = [];

  (Object.keys(modulePageContent) as CaseStudyModule[]).forEach((module) => {
    const section = modulePageContent[module];
    const items = section?.caseStudies?.items ?? [];

    items.forEach((item) => {
      const slug = toSlug(item.link);
      if (!slug || !/^[a-z0-9-]+$/.test(slug)) return;

      studies.push({
        slug,
        title: item.title,
        description: item.description,
        industry: item.industry,
        metrics: item.metrics,
        module,
        moduleLabel: moduleMeta[module].label,
        moduleHref: moduleMeta[module].href,
      });
    });
  });

  return studies;
}

export function getCaseStudy(slug: string): CaseStudy | undefined {
  return getAllCaseStudies().find((study) => study.slug === slug);
}

export function getAllCaseStudySlugs(): string[] {
  return getAllCaseStudies().map((study) => study.slug);
}
