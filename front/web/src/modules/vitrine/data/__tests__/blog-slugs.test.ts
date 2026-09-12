import { getBlogPosts } from '@/modules/vitrine/data/blog';
import { SUPPORTED_LOCALES } from '@/lib/i18n';

/**
 * #7192 — garde anti-régression : un slug de blog doit rester routable.
 *
 * Constaté en live (audit 2026-09-10) : le sitemap publiait
 * `/blog/gestion-absences-congés-efficace` (slug **accentué**) — cette URL
 * répondait 404 (encodée ou non) alors que le post existait et que ses
 * métadonnées se résolvaient. Le slug a été corrigé, mais rien n'empêchait la
 * réintroduction d'un slug non-ASCII : ce test l'interdit explicitement.
 *
 * Contrat : slug = minuscules ASCII, chiffres et tirets simples
 * (`^[a-z0-9]+(?:-[a-z0-9]+)*$`), identique pour les 4 locales.
 */
const ASCII_SLUG = /^[a-z0-9]+(?:-[a-z0-9]+)*$/;

describe('#7192 — slugs de blog routables (ASCII)', () => {
  it.each(SUPPORTED_LOCALES)(
    'aucun slug non-ASCII en %s',
    (locale) => {
      const posts = getBlogPosts(locale);
      expect(posts.length).toBeGreaterThan(0);

      const invalid = posts
        .map((post) => post.slug)
        .filter((slug) => !ASCII_SLUG.test(slug));

      expect(invalid).toEqual([]);
    },
  );

  it('le slug historique « gestion-absences-conges-efficace » est bien routable', () => {
    const slugs = new Set(getBlogPosts('fr').map((post) => post.slug));
    expect(slugs.has('gestion-absences-conges-efficace')).toBe(true);
  });
});
