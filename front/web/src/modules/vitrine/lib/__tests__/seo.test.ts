import { pageMetadata, generateMetadata } from '@/modules/vitrine/lib/seo';

/**
 * Regression guard for issue #1331 (SEO/Analytics vitrine).
 *
 * Every top-level marketing page must have a `pageMetadata` entry so its
 * `layout.tsx` can call `generateMetadata()` and produce real
 * title/description/OpenGraph/Twitter tags instead of falling back to the
 * generic root `layout.tsx` metadata.
 */
describe('pageMetadata coverage', () => {
  const expectedKeys = [
    'landing',
    'employes',
    'documents',
    'comptabilite',
    'marketing',
    'pricing',
    'about',
    'blog',
    'changelog',
    'docs',
    'download',
    'contact',
    'faq',
    'testimonials',
    'caseStudies',
    'videos',
    'branding',
    'careers',
    'mobile',
    'signup',
    'checkout',
  ] as const;

  it.each(expectedKeys)('has a pageMetadata entry for "%s"', (key) => {
    const entry = pageMetadata[key];
    expect(entry).toBeDefined();
    expect(entry.title.length).toBeGreaterThan(0);
    expect(entry.description.length).toBeGreaterThan(0);
  });

  it('signup and checkout are marked noindex (conversion pages, not for SEO)', () => {
    expect(pageMetadata.signup.robots).toBe('noindex, follow');
    expect(pageMetadata.checkout.robots).toBe('noindex, follow');
  });

  it('generateMetadata produces OpenGraph + Twitter tags for every entry', () => {
    for (const key of expectedKeys) {
      const entry = pageMetadata[key];
      const metadata = generateMetadata({
        title: entry.title,
        description: entry.description,
        keywords: entry.keywords,
        ogImage: entry.ogImage,
      });

      expect(metadata.title).toBe(entry.title);
      expect(metadata.openGraph?.title).toBe(entry.title);
      expect(metadata.twitter?.title).toBe(entry.title);
    }
  });
});

describe('og:locale (#3807)', () => {
  it('mappe les locales AppLocale vers og:locale BCP-47', () => {
    const { ogLocaleFor } = require('@/modules/vitrine/lib/seo');
    expect(ogLocaleFor('fr')).toBe('fr_FR');
    expect(ogLocaleFor('en')).toBe('en_US');
    expect(ogLocaleFor('tr')).toBe('tr_TR');
    expect(ogLocaleFor('ar')).toBe('ar_AR');
  });

  it('generateMetadata pose openGraph.locale quand locale est fournie', () => {
    const { generateMetadata } = require('@/modules/vitrine/lib/seo');
    const metadata = generateMetadata({
      title: 'Test',
      description: 'Desc',
      locale: 'ar',
    });
    expect(metadata.openGraph?.locale).toBe('ar_AR');
  });

  it('generateMetadata ne pose pas de locale quand elle est absente (héritage racine)', () => {
    const { generateMetadata } = require('@/modules/vitrine/lib/seo');
    const metadata = generateMetadata({ title: 'Test', description: 'Desc' });
    expect(metadata.openGraph?.locale).toBeUndefined();
  });
});
