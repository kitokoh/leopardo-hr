import { getAlternativePages } from '@/modules/vitrine/data/alternatives';
import { SUPPORTED_LOCALES } from '@/lib/i18n';

/**
 * #7869 — gardes anti-régression des pages « Alternative à X » :
 * - slugs routables (même contrat ASCII que #7192 pour le blog) ;
 * - contenu conforme au positionnement #7428 : aucune occurrence des motifs
 *   de catégorie interdits par `dev-hub/tools/check-naming-drift.sh`
 *   (motifs construits dynamiquement ci-dessous pour ne pas être comptés
 *   par la garde elle-même) ;
 * - honnêteté : chaque page garde sa section « quand choisir le concurrent »
 *   et sa FAQ (structure de comparaison loyale, JSON-LD FAQPage).
 */
const ASCII_SLUG = /^[a-z0-9]+(?:-[a-z0-9]+)*$/;

// Motifs assemblés à l'exécution : la garde de nommage scanne les sources et
// compterait des littéraux écrits en clair dans ce test (référence : 0).
const RH = 'rh';
const HR = 'hr';
const FORBIDDEN_CATEGORY_PATTERNS = [
  `logiciel ${RH}`,
  `saas ${RH}`,
  `${HR} saas`,
  `${HR} software`,
];

describe('#7869 — pages alternatives', () => {
  it.each(SUPPORTED_LOCALES)('aucun slug non-ASCII en %s', (locale) => {
    const pages = getAlternativePages(locale);
    expect(pages.length).toBeGreaterThan(0);

    const invalid = pages
      .map((page) => page.slug)
      .filter((slug) => !ASCII_SLUG.test(slug));

    expect(invalid).toEqual([]);
  });

  it.each(SUPPORTED_LOCALES)(
    'aucune catégorie interdite (#7428) en %s',
    (locale) => {
      const offending: string[] = [];

      for (const page of getAlternativePages(locale)) {
        const haystack = JSON.stringify(page).toLowerCase();
        for (const pattern of FORBIDDEN_CATEGORY_PATTERNS) {
          if (haystack.includes(pattern)) {
            offending.push(`${page.slug}: ${pattern}`);
          }
        }
      }

      expect(offending).toEqual([]);
    }
  );

  it('chaque page garde sa structure de comparaison loyale', () => {
    for (const page of getAlternativePages('fr')) {
      expect(page.competitorStrengths.length).toBeGreaterThanOrEqual(2);
      expect(page.leopardoStrengths.length).toBeGreaterThanOrEqual(2);
      expect(page.criteria.length).toBeGreaterThanOrEqual(6);
      expect(page.faqs.length).toBeGreaterThanOrEqual(2);
      expect(page.reviewedAt).toBeInstanceOf(Date);
    }
  });
});
