/**
 * Regression guards for #3807 (sitemap integrity, audit 2026-08-15).
 *
 * - /signup et /checkout sont noindex (pageMetadata) : ils ne doivent JAMAIS
 *   apparaître dans le sitemap (signaux contradictoires pour les crawlers).
 * - Les études de cas individuelles (/case-studies/{slug}) doivent être
 *   publiées (pages indexables, auparavant absentes).
 * - Les pages statiques ne doivent pas émettre de lastModified volatile
 *   (new Date() par requête = churn quotidien des lastmod).
 */
import { jest } from '@jest/globals';

// jest.doMock (non-hoisté) + import dynamique : déterministe quel que soit le
// transform (SWC) — les imports statiques ESM seraient évalués avant les mocks.
jest.doMock('@/lib/site', () => ({
  DEFAULT_SITE_URL: 'https://www.leopardo-rh.com',
  getSiteUrl: () => 'https://www.leopardo-rh.com',
}));

jest.doMock('@/modules/vitrine/lib/env', () => ({
  getEnvConfig: () => ({
    enableBlog: false,
    apiUrl: '/api/v1',
    gaId: '',
    mixpanelToken: '',
    formEndpoint: '/api/forms',
    siteUrl: 'https://www.leopardo-rh.com',
    siteName: 'Leopardo',
    enableAnalytics: false,
    enableForms: false,
  }),
}));

jest.doMock('@/modules/vitrine/data/blog', () => ({
  getBlogPosts: () => [],
}));

jest.doMock('@/modules/vitrine/lib/case-studies', () => ({
  getAllCaseStudySlugs: () => ['techcorp-algerie', 'pharmaplus-casablanca'],
}));

describe('sitemap integrity (#3807)', () => {
  let urls: string[];
  let entries: Array<{ url: string; lastModified?: unknown; alternates?: { languages?: Record<string, string> } }>;

  beforeAll(async () => {
    const { default: sitemap } = await import('../sitemap');
    entries = sitemap() as typeof entries;
    urls = entries.map((entry) => entry.url);
  });

  it("n'inclut aucune URL noindex (signup, checkout, offline)", () => {
    expect(urls.some((url) => url.includes('/signup'))).toBe(false);
    expect(urls.some((url) => url.includes('/checkout'))).toBe(false);
    // #4401 : /offline est robots:noindex (offline/layout.tsx) — retiré.
    expect(urls.some((url) => url.includes('/offline'))).toBe(false);
  });

  it('#4401 : /privacy et /terms n\'émettent pas de variantes ?lang fantômes', () => {
    const privacy = entries.find((entry) => entry.url.endsWith('/privacy'));
    const terms = entries.find((entry) => entry.url.endsWith('/terms'));
    expect(privacy?.alternates).toBeUndefined();
    expect(terms?.alternates).toBeUndefined();
  });

  it("ne publie pas /blog quand NEXT_PUBLIC_ENABLE_BLOG est off (#4467 — régression #2647/#2904)", () => {
    // NB : ce test mock enableBlog: false. Le comportement par défaut
    // (sans var d'env) est désormais enableBlog: true (#2906) —
    // testé via les routes blog directement (blog/layout.test.ts).
    expect(urls.some((url) => url.includes('/blog'))).toBe(false);
  });

  it('publie les études de cas individuelles', () => {
    expect(urls).toContain('https://www.leopardo-rh.com/case-studies/techcorp-algerie');
    expect(urls).toContain('https://www.leopardo-rh.com/case-studies/pharmaplus-casablanca');
  });

  it('ne publie pas de lastModified volatil sur les pages statiques', () => {
    const pricing = entries.find((entry) => entry.url.endsWith('/pricing'));
    expect(pricing?.lastModified).toBeUndefined();
  });

  it('chaque entrée porte des alternates hreflang localisées', () => {
    const pricing = entries.find((entry) => entry.url.endsWith('/pricing'));
    expect(pricing?.alternates?.languages).toEqual({
      fr: 'https://www.leopardo-rh.com/pricing',
      en: 'https://www.leopardo-rh.com/pricing?lang=en',
      tr: 'https://www.leopardo-rh.com/pricing?lang=tr',
      ar: 'https://www.leopardo-rh.com/pricing?lang=ar',
    });
  });
});
