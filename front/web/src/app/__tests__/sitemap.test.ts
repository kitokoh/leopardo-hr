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

// RESTO-903 (#7748) : les slugs publics /restaurants/{slug} viennent de l'API
// publique — mockés ici (pas de réseau en test), avec un cas nominal.
jest.doMock('@/lib/restaurants-public-api', () => ({
  getAllPublicRestaurantSlugs: async () => ['chez-fatou', 'la-brasserie-du-port'],
}));

// BC-10 (#9220) : les portails carrières par tenant viennent de l'annuaire
// public — mocké ici (pas de réseau en test). Seules les entreprises ayant au
// moins une offre publiée y figurent.
jest.doMock('@/lib/careers-directory-api', () => ({
  getPublicCareersDirectory: async () => [
    { slug: 'techcorp-algerie', name: 'TechCorp', country: 'DZ', last_published_at: '2026-11-01T10:00:00Z' },
    { slug: 'pharmaplus-casablanca', name: 'PharmaPlus', country: 'MA', last_published_at: null },
  ],
}));

// BC-10 (#9245) : les pages de DÉTAIL d'offre viennent de l'API publique —
// mockées ici (pas de réseau en test). Le jeu de données couvre les deux
// populations : une offre du tenant (page chez nous, à déclarer) et une offre
// agrégée (renvoie chez le partenaire, à NE PAS déclarer).
jest.doMock('@/lib/careers-api', () => ({
  isExternalOffer: (job: { apply_url?: unknown }) => 'apply_url' in job,
  getPublicJobPostings: async (slug: string) => {
    if (slug === 'techcorp-algerie') {
      return [
        { id: 42, title: 'Ingénieur DevOps', created_at: '2026-11-02T09:00:00Z' },
        {
          id: 99,
          title: 'Offre agrégée',
          apply_url: 'https://partner.example/jobs/99',
          created_at: '2026-11-03T09:00:00Z',
        },
      ];
    }

    return [];
  },
}));

// #9357 : les fiches des verticales (`/boutiques/{slug}`, `/operateurs/{slug}`,
// `/entreprises/{slug}`, `/stay/{slug}`) viennent du contrat public de
// découverte — mocké ici (aucun réseau en test). Sans ce mock, le test tapait
// l'API réelle et le sitemap dépassait le budget de build.
jest.doMock('@/lib/public-discovery-api', () => ({
  collectVerticalProfilePages: async (siteUrl: string) => [
    { url: `${siteUrl}/boutiques/atelier-du-cuir`, changeFrequency: 'daily', priority: 0.6 },
    { url: `${siteUrl}/entreprises/transports-benali`, changeFrequency: 'daily', priority: 0.6 },
  ],
}));

describe('sitemap integrity (#3807)', () => {
  let urls: string[];
  let entries: Array<{ url: string; lastModified?: unknown; alternates?: { languages?: Record<string, string> } }>;

  beforeAll(async () => {
    const { default: sitemap } = await import('../sitemap');
    // RESTO-903 (#7748) : sitemap() est désormais async (slugs restaurants).
    entries = (await sitemap()) as typeof entries;
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
    expect(urls).toContain('https://www.leopardo-rh.com/business/case-studies/techcorp-algerie');
    expect(urls).toContain('https://www.leopardo-rh.com/business/case-studies/pharmaplus-casablanca');
  });

  it('RESTO-903 (#7748) : publie l’annuaire et les profils publics /restaurants/{slug}', () => {
    expect(urls).toContain('https://www.leopardo-rh.com/restaurants');
    expect(urls).toContain('https://www.leopardo-rh.com/restaurants/chez-fatou');
    expect(urls).toContain('https://www.leopardo-rh.com/restaurants/la-brasserie-du-port');
  });

  it('BC-10 (#9245) : publie les pages de DÉTAIL d’offre du tenant', () => {
    // C'est la page de détail qui porte le JSON-LD `JobPosting` lu par Google
    // Jobs : sans elle au sitemap, les annonces restaient hors du crawl.
    expect(urls).toContain('https://www.leopardo-rh.com/techcorp-algerie/careers/jobs/42');
  });

  it('BC-10 (#9245) : ne publie PAS les offres agrégées (pas de page chez nous)', () => {
    // Une offre agrégée renvoie chez le partenaire : l'inscrire enverrait les
    // robots vers une URL inexistante sur notre domaine.
    expect(urls.some((url) => url.includes('/careers/jobs/99'))).toBe(false);
  });

  it('ne publie pas de lastModified volatil sur les pages statiques', () => {
    const pricing = entries.find((entry) => entry.url.endsWith('/pricing'));
    expect(pricing?.lastModified).toBeUndefined();
  });

  it('#9357 : publie les fiches des verticales (auparavant orphelines)', () => {
    // `/boutiques/{slug}`, `/operateurs/{slug}`, `/entreprises/{slug}` et
    // `/stay/{slug}` étaient servies mais déclarées NULLE PART : invisibles
    // pour le crawl. Elles doivent désormais figurer au sitemap.
    expect(urls).toContain('https://www.leopardo-rh.com/boutiques/atelier-du-cuir');
    expect(urls).toContain('https://www.leopardo-rh.com/entreprises/transports-benali');
  });

  it('chaque entrée porte des alternates hreflang localisées', () => {
    const pricing = entries.find((entry) => entry.url.endsWith('/pricing'));
    expect(pricing?.alternates?.languages).toEqual({
      fr: 'https://www.leopardo-rh.com/business/pricing',
      en: 'https://www.leopardo-rh.com/business/pricing?lang=en',
      tr: 'https://www.leopardo-rh.com/business/pricing?lang=tr',
      ar: 'https://www.leopardo-rh.com/business/pricing?lang=ar',
      // #AI-SEO : variante de repli pour les langues non couvertes — doit
      // pointer la version FR (sans ?lang=), alignée sur seo.ts et le layout
      // racine.
      'x-default': 'https://www.leopardo-rh.com/business/pricing',
    });
  });
});
