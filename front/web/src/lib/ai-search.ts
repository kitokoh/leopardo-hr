/**
 * AI Search (GEO/AEO) — générateurs des fichiers `llms.txt` et
 * `llms-full.txt` servis par `src/app/llms.txt/route.ts` et
 * `src/app/llms-full.txt/route.ts`.
 *
 * Pourquoi des fichiers dédiés : les crawlers classiques lisent le HTML, mais
 * les moteurs de réponse (ChatGPT Search, Perplexity, Claude, Gemini,
 * Copilot) et les pipelines RAG consomment un inventaire markdown compact.
 * `llms.txt` est la convention émergente (llmstxt.org) : titre, résumé,
 * puis liens annotés. `llms-full.txt` ajoute le contenu de fond (FAQ
 * localisées, offres, articles) pour l'ingestion directe.
 *
 * Le contenu est LOCALISÉ selon la même règle que le reste de la vitrine
 * (?lang=fr|en|tr|ar, FR par défaut) — cohérent avec les alternates hreflang.
 *
 * Note i18n : ce module vit sous `src/lib/` (hors surface PA2-I18N-014) ; les
 * libellés utilisateur restent dans le catalogue partagé (`seo.llms.*`,
 * `seo.breadcrumb.*`) — ici uniquement la composition technique.
 */

import type { AppLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';
import { SITE_URL } from '@/lib/site-url';
import {
  BRAND_ALTERNATE_NAMES,
  BRAND_NAME_BY_LOCALE,
  getPageMetadata,
} from '@/modules/vitrine/lib/seo';
import { getBlogPosts } from '@/modules/vitrine/data/blog';
import { getFaqPageContent } from '@/modules/vitrine/data/faq-page';
import { PRICING_CURRENCY } from '@/modules/vitrine/data/currency';
import { getPricingPlans, showsCurrency } from '@/modules/vitrine/data/pricing';

/**
 * `text/plain; charset=utf-8` — constante technique (le littéral est flaggé à
 * tort par la garde i18n lorsqu'il est écrit dans `src/app/**`, cf.
 * PA2-I18N-014 « false positive, adjust the literal »).
 */
export const LLMS_CONTENT_TYPE = 'text/plain; charset=utf-8';

/** Cache navigateur/CDN des fichiers AI-search (contenu quasi statique). */
export const LLMS_CACHE_CONTROL = 'public, max-age=3600, s-maxage=86400';

/** Dépôt public — unique profil de marque vérifié (schema.org `sameAs`). */
export const AI_SEARCH_REPOSITORY_URL = 'https://github.com/kitokoh/leopardo-hr';

/**
 * Chiffres publiés sur la vitrine. Sources : section « Conçu pour vos
 * secteurs » (catalogue de paie) et hero (essai/locales). ⚠️ Ne pas modifier
 * ici sans mettre à jour la page d'accueil (les deux affichages divergent
 * déjà : « 8 pays couverts » au hero vs « 21 pays au catalogue »).
 */
export const AI_SEARCH_FACTS = {
  payrollCountries: 21,
  productLanguages: 4,
  trialDays: 14,
} as const;

type LinkPage = { page: string; path: string };

const CORE_PAGES: LinkPage[] = [
  { page: 'landing', path: '/' },
  { page: 'employes', path: '/employes' },
  { page: 'documents', path: '/documents' },
  { page: 'comptabilite', path: '/comptabilite' },
  { page: 'marketing', path: '/marketing' },
  { page: 'integrations', path: '/integrations' },
  { page: 'pricing', path: '/pricing' },
  { page: 'demo', path: '/demo' },
  { page: 'faq', path: '/faq' },
];

const RESOURCE_PAGES: LinkPage[] = [
  { page: 'docs', path: '/docs' },
  { page: 'guideRhStartup', path: '/guides/rh-startup' },
  { page: 'guideChecklistPaie', path: '/guides/checklist-paie' },
  { page: 'guidePlanningEmployes', path: '/guides/planning-employes' },
  { page: 'download', path: '/download' },
  { page: 'mobile', path: '/mobile' },
  { page: 'videos', path: '/videos' },
];

const COMPANY_PAGES: LinkPage[] = [
  { page: 'about', path: '/about' },
  { page: 'caseStudies', path: '/case-studies' },
  { page: 'testimonials', path: '/testimonials' },
  { page: 'careers', path: '/careers' },
  { page: 'changelog', path: '/changelog' },
  { page: 'branding', path: '/branding' },
];

/**
 * URL d'une page dans la locale demandée — même convention que les
 * alternates hreflang du sitemap : FR sans query, autres locales en `?lang=`.
 */
export function localizedUrl(path: string, locale: AppLocale): string {
  const cleanPath = path === '/' ? '' : path.replace(/\/$/, '');
  const base = `${SITE_URL}${cleanPath || '/'}`;
  return locale === 'fr' ? base : `${base}?lang=${locale}`;
}

/** Libellé catalogue `seo.llms.*` pour la locale. */
function copy(locale: AppLocale, key: string): string {
  return t(locale, `seo.llms.${key}`);
}

function renderLinks(pages: LinkPage[], locale: AppLocale): string {
  return pages
    .map(({ page, path }) => {
      const meta = getPageMetadata(page, locale);
      return `- [${meta.title}](${localizedUrl(path, locale)}): ${meta.description}`;
    })
    .join('\n');
}

function renderBlogLinks(locale: AppLocale): string {
  const posts = getBlogPosts(locale);
  if (!posts.length) {
    return '';
  }
  return posts
    .map(
      (post) =>
        `- [${post.title}](${localizedUrl(`/blog/${post.slug}`, locale)}): ${post.excerpt}`,
    )
    .join('\n');
}

function renderFacts(locale: AppLocale): string {
  return [
    `${AI_SEARCH_FACTS.payrollCountries} ${copy(locale, 'factCountries')}`,
    `${AI_SEARCH_FACTS.productLanguages} ${copy(locale, 'factLanguages')}`,
    `${AI_SEARCH_FACTS.trialDays} ${copy(locale, 'factTrial')}`,
    copy(locale, 'factPlans'),
  ]
    .map((fact) => `- ${fact}`)
    .join('\n');
}

function renderHeader(locale: AppLocale): string {
  return [
    `# ${BRAND_NAME_BY_LOCALE[locale] ?? BRAND_NAME_BY_LOCALE.fr}`,
    `> ${copy(locale, 'summary')}`,
    `- ${copy(locale, 'aliasesLabel')}: ${BRAND_ALTERNATE_NAMES.join(', ')}`,
    `${copy(locale, 'factsLabel')}:\n${renderFacts(locale)}`,
  ].join('\n\n');
}

function renderContact(locale: AppLocale): string {
  const contactMeta = getPageMetadata('contact', locale);
  return [
    `## ${copy(locale, 'sectionContact')}`,
    `- [${contactMeta.title}](${localizedUrl('/contact', locale)}): ${contactMeta.description}`,
    `- ${copy(locale, 'contactLine')}`,
    `- GitHub: ${AI_SEARCH_REPOSITORY_URL}`,
  ].join('\n\n');
}

/** Contenu de `/llms.txt` — inventaire compact des pages publiques. */
export function buildLlmsTxt(locale: AppLocale): string {
  const sections: string[] = [
    renderHeader(locale),
    `## ${copy(locale, 'sectionCore')}\n${renderLinks(CORE_PAGES, locale)}`,
    `## ${copy(locale, 'sectionResources')}\n${renderLinks(RESOURCE_PAGES, locale)}`,
  ];

  const blog = renderBlogLinks(locale);
  if (blog) {
    sections.push(`## ${copy(locale, 'sectionBlog')}\n${blog}`);
  }

  sections.push(
    `## ${copy(locale, 'sectionCompany')}\n${renderLinks(COMPANY_PAGES, locale)}`,
    renderContact(locale),
  );

  return `${sections.join('\n\n')}\n`;
}

function renderPricing(locale: AppLocale): string {
  return getPricingPlans(locale)
    .map((plan) => {
      const price = showsCurrency(plan.price)
        ? `${plan.price} ${PRICING_CURRENCY}`
        : plan.price;
      const limit = plan.employeeLimit ? ` (${plan.employeeLimit})` : '';
      const features = plan.features?.length
        ? `\n${plan.features.map((feature) => `  - ${feature}`).join('\n')}`
        : '';
      return `### ${plan.name} — ${price}${limit}\n${plan.description}${features}`;
    })
    .join('\n\n');
}

function renderFaq(locale: AppLocale): string {
  const { items } = getFaqPageContent(locale);
  return items
    .map((item) => `### ${item.question}\n${item.answer}`)
    .join('\n\n');
}

/** Contenu de `/llms-full.txt` — version longue pour ingestion RAG. */
export function buildLlmsFullTxt(locale: AppLocale): string {
  const sections: string[] = [
    renderHeader(locale),
    `> ${copy(locale, 'fullIntro')}`,
    `## ${copy(locale, 'pricingSection')}\n${renderPricing(locale)}`,
    `## ${copy(locale, 'faqSection')}\n${renderFaq(locale)}`,
  ];

  const blog = renderBlogLinks(locale);
  if (blog) {
    sections.push(`## ${copy(locale, 'sectionBlog')}\n${blog}`);
  }

  sections.push(
    `## ${copy(locale, 'sectionCore')}\n${renderLinks(CORE_PAGES, locale)}`,
    `## ${copy(locale, 'sectionResources')}\n${renderLinks(RESOURCE_PAGES, locale)}`,
    `## ${copy(locale, 'sectionCompany')}\n${renderLinks(COMPANY_PAGES, locale)}`,
    renderContact(locale),
  );

  return `${sections.join('\n\n')}\n`;
}

/** Libellés de fil d'Ariane localisés (catalogue `seo.breadcrumb.*`). */
export function breadcrumbLabels(locale: AppLocale): {
  home: string;
  blog: string;
  caseStudies: string;
  guides: string;
} {
  return {
    home: t(locale, 'seo.breadcrumb.home'),
    blog: t(locale, 'seo.breadcrumb.blog'),
    caseStudies: t(locale, 'seo.breadcrumb.caseStudies'),
    guides: t(locale, 'seo.breadcrumb.guides'),
  };
}
